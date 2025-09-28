<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Functional\Middleware;

use Closure;
use Countable;
use CubicMushroom\Cqrs\Bus\CommandBusInterface;
use CubicMushroom\Cqrs\Bus\IdProvider\MessageIdFactoryInterface;
use CubicMushroom\Cqrs\Bus\Stamp\CausedByMessageStamp;
use CubicMushroom\Cqrs\Bus\SymfonyCommandBus;
use CubicMushroom\Cqrs\Bus\SymfonyDomainEventBus;
use CubicMushroom\Cqrs\Bus\SymfonyQueryBus;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\DomainEvent\AbstractDomainEvent;
use CubicMushroom\Cqrs\MessageTypeEnum;
use CubicMushroom\Cqrs\Middleware\BlameMiddleware;
use CubicMushroom\Cqrs\Middleware\MessageIdStampMiddleware;
use CubicMushroom\Cqrs\Query\QueryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionObject;
use RuntimeException;
use SplObjectStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

use function array_shift;

final class BlameMiddlewareFunctionalTest extends TestCase
{
    public function test_command_dispatch_propagates_causes_to_event_and_query(): void
    {
        $sequenceIdFactory = new SequenceMessageIdFactory([
            'cmd-1',
            'event-1',
            'query-1',
        ]);

        $messageIdMiddleware = new MessageIdStampMiddleware($sequenceIdFactory);

        $blameMiddleware = new BlameMiddleware();

        $commandCapture = new CapturingMiddleware();
        $eventCapture = new CapturingMiddleware();
        $queryCapture = new CapturingMiddleware();

        $queryBus = $this->createQueryBus(
            $messageIdMiddleware,
            $blameMiddleware,
            $queryCapture,
        );

        $eventBus = $this->createDomainEventBus(
            $messageIdMiddleware,
            $blameMiddleware,
            $eventCapture,
            fn() => $queryBus->dispatch(new TriggerQuery()),
        );

        $commandBus = $this->createCommandBus(
            $messageIdMiddleware,
            $blameMiddleware,
            $commandCapture,
            fn() => $eventBus->dispatch(new TriggeredEvent()),
        );

        $commandBus->dispatch(new TriggerCommand());

        $commandEnvelope = $commandCapture->requireLastEnvelope();
        $this->assertCount(0, $commandEnvelope->all(CausedByMessageStamp::class));

        $eventEnvelope = $eventCapture->requireLastEnvelope();
        $eventStamps = $eventEnvelope->all(CausedByMessageStamp::class);
        $this->assertCount(1, $eventStamps);
        $eventStamp = $eventStamps[0];
        $this->assertInstanceOf(CausedByMessageStamp::class, $eventStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $eventStamp->messageType);
        $this->assertSame('cmd-1', $eventStamp->messageId);

        $queryEnvelope = $queryCapture->requireLastEnvelope();
        $queryStamps = $queryEnvelope->all(CausedByMessageStamp::class);
        $this->assertCount(2, $queryStamps);

        $firstStamp = $queryStamps[0];
        $this->assertInstanceOf(CausedByMessageStamp::class, $firstStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $firstStamp->messageType);
        $this->assertSame('cmd-1', $firstStamp->messageId);

        $secondStamp = $queryStamps[1];
        $this->assertInstanceOf(CausedByMessageStamp::class, $secondStamp);
        $this->assertSame(MessageTypeEnum::DOMAIN_EVENT, $secondStamp->messageType);
        $this->assertSame('event-1', $secondStamp->messageId);
    }


    public function test_command_dispatch_propagates_causes_across_async_handling(): void
    {
        $sequenceIdFactory = new SequenceMessageIdFactory([
            'cmd-1',
            'event-1',
            'cmd-2',
            'query-1',
        ]);

        $messageIdStampMiddlewareFactory = new MessageIdStampMiddleware($sequenceIdFactory);

        $blameMiddleware = new BlameMiddleware();

        $commandCapture = new CapturingMiddleware();
        $eventCapture = new CapturingMiddleware();
        $queryCapture = new CapturingMiddleware();

        $queue = new SplObjectStorage();
        $sendMessageMiddleware = new SendMessageMiddleware(
            new SendersLocator(
                [
                    '*' => ['async'],
                ],
                new readonly class($queue) implements ContainerInterface {
                    public function __construct(private SplObjectStorage $queue)
                    {
                    }


                    public function get(string $id): SenderInterface
                    {
                        return match ($id) {
                            'async' => new readonly class($this->queue) implements SenderInterface {
                                public function __construct(
                                    private SplObjectStorage $queue,
                                ) {
                                }


                                public function send(Envelope $envelope): Envelope
                                {
                                    $this->queue->attach($envelope);

                                    return $envelope->with(new SentStamp($this::class . 'async'));
                                }
                            },
                        };
                    }


                    public function has(string $id): bool
                    {
                        return 'async' === $id;
                    }
                },
            ),
        );

        $queryBus = $this->createQueryBus(
            $messageIdStampMiddlewareFactory,
            $blameMiddleware,
            $queryCapture,
        );

        $commandBusWrapper = new class {
            public CommandBusInterface $commandBus;
        };

        $eventBus = $this->createDomainEventBus(
            $messageIdStampMiddlewareFactory,
            $blameMiddleware,
            $eventCapture,
            fn() => $commandBusWrapper->commandBus->dispatch(new TriggerCommand(2)),
        );

        $commandBus = $this->createCommandBus(
            $messageIdStampMiddlewareFactory,
            $blameMiddleware,
            $commandCapture,
            fn(TriggerCommand $command) => match ($command->id) {
                1 => $eventBus->dispatch(new TriggeredEvent()),
                2 => $queryBus->dispatch(new TriggerQuery()),
            },
            $sendMessageMiddleware,
        );
        $commandBusWrapper->commandBus = $commandBus;

        $commandBus->dispatch(new TriggerCommand(1));

        $commandEnvelope = $commandCapture->requireLastEnvelope();
        $this->assertCount(0, $commandEnvelope->all(CausedByMessageStamp::class));

        $this->assertCount(1, $commandCapture);
        $this->assertCount(0, $eventCapture);
        $this->assertCount(0, $queryCapture);

        $this->assertCount(1, $queue);

        $queue->rewind();

        // Simulate the worker consuming the message
        $envelope = $queue->current();
        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->getCommandBusMessageBus($commandBus)
            ->dispatch($envelope->with(new ReceivedStamp('in-memory'), new ConsumedByWorkerStamp()));

        // The command capture will be captures the first command both when it's
        // dispatched and when it's received from the queue + the new command.
        $this->assertCount(3, $commandCapture);
        $this->assertCount(1, $eventCapture);
        $this->assertCount(0, $queryCapture);

        $eventEnvelope = $eventCapture->requireLastEnvelope();
        $eventStamps = $eventEnvelope->all(CausedByMessageStamp::class);
        $this->assertCount(1, $eventStamps);
        $eventStamp = $eventStamps[0];
        $this->assertInstanceOf(CausedByMessageStamp::class, $eventStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $eventStamp->messageType);
        $this->assertSame('cmd-1', $eventStamp->messageId);

        $this->assertCount(2, $queue);

        $commandEnvelope = $commandCapture->requireLastEnvelope();
        $commandStamps = $commandEnvelope->all(CausedByMessageStamp::class);
        $this->assertCount(2, $commandStamps);
        $commandStamp = $commandStamps[0];
        $this->assertInstanceOf(CausedByMessageStamp::class, $commandStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $commandStamp->messageType);
        $this->assertSame('cmd-1', $commandStamp->messageId);
        $commandStamp = $commandStamps[1];
        $this->assertInstanceOf(CausedByMessageStamp::class, $commandStamp);
        $this->assertSame(MessageTypeEnum::DOMAIN_EVENT, $commandStamp->messageType);
        $this->assertSame('event-1', $commandStamp->messageId);

        $this->assertCount(2, $queue);

        // Simulate the worker consuming the message
        $queue->next();
        $envelope = $queue->current();
        $queue->detach($envelope);
        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->getCommandBusMessageBus($commandBus)
            ->dispatch($envelope->with(new ReceivedStamp('in-memory'), new ConsumedByWorkerStamp()));

        // The command capture will be captures the first command both when it's
        // dispatched and when it's received from the queue + the new command.
        $this->assertCount(4, $commandCapture);
        $this->assertCount(1, $eventCapture);
        $this->assertCount(1, $queryCapture);

        $commandEnvelope = $commandCapture->requireLastEnvelope();
        $commandStamps = $commandEnvelope->all(CausedByMessageStamp::class);
        $this->assertCount(2, $commandStamps);
        $commandStamp = $commandStamps[0];
        $this->assertInstanceOf(CausedByMessageStamp::class, $commandStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $commandStamp->messageType);
        $this->assertSame('cmd-1', $commandStamp->messageId);
        $commandStamp = $commandStamps[1];
        $this->assertInstanceOf(CausedByMessageStamp::class, $commandStamp);
        $this->assertSame(MessageTypeEnum::DOMAIN_EVENT, $commandStamp->messageType);
        $this->assertSame('event-1', $commandStamp->messageId);

        $queryEnvelope = $queryCapture->requireLastEnvelope();
        $queryStamps = $queryEnvelope->all(CausedByMessageStamp::class);
        $this->assertCount(3, $queryStamps);
        $queryStamp = array_shift($queryStamps);
        $this->assertInstanceOf(CausedByMessageStamp::class, $queryStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $queryStamp->messageType);
        $this->assertSame('cmd-1', $queryStamp->messageId);
        $queryStamp = array_shift($queryStamps);
        $this->assertInstanceOf(CausedByMessageStamp::class, $queryStamp);
        $this->assertSame(MessageTypeEnum::DOMAIN_EVENT, $queryStamp->messageType);
        $this->assertSame('event-1', $queryStamp->messageId);
        $queryStamp = array_shift($queryStamps);
        $this->assertInstanceOf(CausedByMessageStamp::class, $queryStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $queryStamp->messageType);
        $this->assertSame('cmd-2', $queryStamp->messageId);
    }


    private function createCommandBus(
        MessageIdStampMiddleware $messageIdStampMiddleware,
        BlameMiddleware $blameMiddleware,
        CapturingMiddleware $capture,
        Closure $handler,
        ?SendMessageMiddleware $sendMessageMiddleware = null,
    ): SymfonyCommandBus {
        $commandHandler = function (TriggerCommand $command) use ($handler): void {
            $handler($command);
        };

        $messageBus = new MessageBus(array_filter([
            $messageIdStampMiddleware,
            $blameMiddleware,
            $capture,
            $sendMessageMiddleware,
            new HandleMessageMiddleware(new HandlersLocator([
                TriggerCommand::class => [$commandHandler],
            ])),
        ]));

        return new SymfonyCommandBus($messageBus);
    }


    private function createDomainEventBus(
        MessageIdStampMiddleware $messageIdStampMiddleware,
        BlameMiddleware $blameMiddleware,
        CapturingMiddleware $capture,
        Closure $handler,
    ): SymfonyDomainEventBus {
        $eventHandler = function (TriggeredEvent $event) use ($handler): void {
            $handler();
        };

        $messageBus = new MessageBus([
            $messageIdStampMiddleware,
            $blameMiddleware,
            $capture,
            new HandleMessageMiddleware(new HandlersLocator([
                TriggeredEvent::class => [$eventHandler],
            ])),
        ]);

        return new SymfonyDomainEventBus($messageBus);
    }


    private function createQueryBus(
        MessageIdStampMiddleware $messageIdStampMiddleware,
        BlameMiddleware $blameMiddleware,
        CapturingMiddleware $capture,
    ): SymfonyQueryBus {
        $queryHandler = static function (TriggerQuery $query): string {
            return 'result';
        };

        $messageBus = new MessageBus([
            $messageIdStampMiddleware,
            $blameMiddleware,
            $capture,
            new HandleMessageMiddleware(new HandlersLocator([
                TriggerQuery::class => [$queryHandler],
            ])),
        ]);

        return new SymfonyQueryBus($messageBus);
    }


    private function getCommandBusMessageBus(SymfonyCommandBus $commandBus): MessageBusInterface
    {
        return new ReflectionObject($commandBus)->getProperty('messageBus')->getValue($commandBus);
    }
}

final readonly class TriggerCommand implements CommandInterface
{
    public function __construct(
        public int $id = 1,
    ) {
    }
}

final readonly class TriggeredEvent extends AbstractDomainEvent
{
}

final readonly class TriggerQuery implements QueryInterface
{
}

final class SequenceMessageIdFactory implements MessageIdFactoryInterface
{
    /**
     * @param string[] $ids
     */
    public function __construct(private array $ids)
    {
    }


    public function nextId(): string
    {
        $next = array_shift($this->ids);

        if ($next === null) {
            throw new RuntimeException('SequenceMessageIdFactory ran out of IDs.');
        }

        return $next;
    }
}

final class CapturingMiddleware implements MiddlewareInterface, Countable
{
    private int $count = 0;

    private ?Envelope $lastEnvelope = null;


    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->lastEnvelope = $envelope;
        ++$this->count;

        return $stack->next()->handle($envelope, $stack);
    }


    public function requireLastEnvelope(): Envelope
    {
        return $this->lastEnvelope ?? throw new RuntimeException('Envelope was not captured.');
    }


    public function count(): int
    {
        return $this->count;
    }
}
