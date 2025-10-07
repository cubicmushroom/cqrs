<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Integration\Middleware;

use Closure;
use Countable;
use CubicMushroom\Cqrs\Bus\IdProvider\MessageIdFactoryInterface;
use CubicMushroom\Cqrs\Bus\Stamp\CausedByMessageStamp;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
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
use RuntimeException;
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
use Symfony\Contracts\Service\ServiceLocatorTrait;

use function array_reverse;
use function array_shift;

/**
 * Integration tests to ensure that the BlameMiddleware works as expected.
 */
final class BlameMiddlewareTest extends TestCase
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
        $sequenceMessageIdFactory = new SequenceMessageIdFactory(
            ['cmd-1', 'event-1', 'cmd-2', 'query-1'],
        );

        $messageIdStampMiddleware = new MessageIdStampMiddleware($sequenceMessageIdFactory);

        $blameMiddleware = new BlameMiddleware();

        $commandCaptureMiddleware = new CapturingMiddleware();
        $eventCaptureMiddleware = new CapturingMiddleware();
        $queryCaptureMiddleware = new CapturingMiddleware();

        $sender = new class() implements SenderInterface {
            public array $queue = [];


            public function send(Envelope $envelope): Envelope
            {
                $this->queue[] = $envelope;

                return $envelope->with(new SentStamp($this::class . 'async'));
            }
        };

        $sendMessageMiddleware = new SendMessageMiddleware(
            new SendersLocator(
                ['*' => ['async']],
                new class(['async' => fn() => $sender]) implements ContainerInterface {
                    use ServiceLocatorTrait;
                },
            ),
        );

        $queryBus = new MessageBus([$messageIdStampMiddleware, $blameMiddleware, $queryCaptureMiddleware]);

        $container = new class([
            'bus.query' => fn(ContainerInterface $container) => $queryBus,
            'bus.event' => fn(ContainerInterface $container) => new MessageBus([
                $messageIdStampMiddleware,
                $blameMiddleware,
                $eventCaptureMiddleware,
                new HandleMessageMiddleware(new HandlersLocator([
                    TriggeredEvent::class => [
                        fn(TriggeredEvent $event) => $container->get('bus.command')->dispatch(new TriggerCommand(2)),
                    ],
                ])),
            ]),
            'bus.command' => fn(ContainerInterface $container) => new MessageBus([
                $messageIdStampMiddleware,
                $blameMiddleware,
                $commandCaptureMiddleware,
                $sendMessageMiddleware,
                new HandleMessageMiddleware(new HandlersLocator([
                    TriggerCommand::class => [
                        fn(TriggerCommand $command) => match ($command->id) {
                            1 => $container->get('bus.event')->dispatch(new TriggeredEvent()),
                            2 => $container->get('bus.query')->dispatch(new TriggerQuery()),
                        },
                    ],
                ])),
            ]),
        ]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get('bus.command');

        // Dispatch the first command.  This should do nothing, initially, as
        // it's dispatched asynchronously.
        // ---------------------------------------------------------------------

        $envelope = $commandBus->dispatch(new TriggerCommand(1));

        $this->assertCount(1, $envelope->all(MessageIdStamp::class));
        $this->assertSame('cmd-1', $envelope->last(MessageIdStamp::class)?->messageId);

        $commandEnvelope = $commandCaptureMiddleware->requireLastEnvelope();
        $this->assertCount(0, $commandEnvelope->all(CausedByMessageStamp::class));

        $this->assertCount(1, $commandCaptureMiddleware);
        $this->assertCount(0, $eventCaptureMiddleware);
        $this->assertCount(0, $queryCaptureMiddleware);
        $this->assertCount(1, $sender->queue);

        $commandCaptureMiddleware->reset();
        $eventCaptureMiddleware->reset();
        $queryCaptureMiddleware->reset();

        // Now process the initial command by simulating receiving it from the
        // queue.
        // ---------------------------------------------------------------------

        $messageQueue = &$sender->queue;
        $envelope = array_shift($messageQueue);
        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertCount(0, $sender->queue);
        $commandBus->dispatch($envelope->with(new ReceivedStamp('in-memory'), new ConsumedByWorkerStamp()));

        // The command capture will capture the first command, as well as the
        // command dispatched by the event listener.
        $this->assertCount(2, $commandCaptureMiddleware);
        $this->assertCount(1, $eventCaptureMiddleware);
        $this->assertCount(0, $queryCaptureMiddleware);
        // Command dispatched by the event handler should be queued.
        $this->assertCount(1, $messageQueue);

        // Check the CausedByStamp on the event…
        $eventEnvelope = $eventCaptureMiddleware->requireLastEnvelope();
        $eventStamps = $eventEnvelope->all(CausedByMessageStamp::class);
        $eventStamp = array_shift($eventStamps);
        $this->assertInstanceOf(CausedByMessageStamp::class, $eventStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $eventStamp->messageType);
        $this->assertSame('cmd-1', $eventStamp->messageId);
        $this->assertEmpty($eventStamps);
        // … and the second command…
        $commandEnvelope = $commandCaptureMiddleware->requireLastEnvelope();
        $commandStamps = $commandEnvelope->all(CausedByMessageStamp::class);
        $commandStamp = array_shift($commandStamps);
        $this->assertInstanceOf(CausedByMessageStamp::class, $commandStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $commandStamp->messageType);
        $this->assertSame('cmd-1', $commandStamp->messageId);
        $commandStamp = array_shift($commandStamps);
        $this->assertInstanceOf(CausedByMessageStamp::class, $commandStamp);
        $this->assertSame(MessageTypeEnum::DOMAIN_EVENT, $commandStamp->messageType);
        $this->assertSame('event-1', $commandStamp->messageId);
        $this->assertEmpty($commandStamps);

        $commandCaptureMiddleware->reset();
        $eventCaptureMiddleware->reset();
        $queryCaptureMiddleware->reset();

        // Now process the second command by simulating receiving it from the
        // queue.
        // ---------------------------------------------------------------------

        $messageQueue = &$sender->queue;
        $envelope = array_shift($messageQueue);
        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertCount(0, $sender->queue);
        $commandBus->dispatch($envelope->with(new ReceivedStamp('in-memory'), new ConsumedByWorkerStamp()));

        // The command capture will capture the received command, which is the
        // one we dispatched from the queue
        $this->assertCount(1, $commandCaptureMiddleware);
        $this->assertCount(0, $eventCaptureMiddleware);
        $this->assertCount(1, $queryCaptureMiddleware);

        // Check the CausedByStamp on the event…
        $commandEnvelope = $commandCaptureMiddleware->requireLastEnvelope();
        $this->assertSame($commandEnvelope->getMessage(), $envelope->getMessage());

        // Check the CausedByStamp on the query…
        $queryEnvelope = $queryCaptureMiddleware->requireLastEnvelope();
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
    ): SymfonyCommandBus {
        $commandHandler = function (TriggerCommand $command) use ($handler): void {
            $handler($command);
        };

        $messageBus = new MessageBus(array_filter([
            $messageIdStampMiddleware,
            $blameMiddleware,
            $capture,
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
        $eventHandler = function (/* TriggeredEvent $event */) use ($handler): void {
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
        $queryHandler = static function (/* TriggerQuery $query */): string {
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
    private array $envelopes = [];


    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->envelopes[] = $envelope;

        return $stack->next()->handle($envelope, $stack);
    }


    public function requireLastEnvelope(): Envelope
    {
        return array_reverse(array_values($this->envelopes))[0] ?? throw new RuntimeException('Envelope was not captured.');
    }


    public function count(): int
    {
        return count($this->envelopes);
    }


    public function reset(): void
    {
        $this->envelopes = [];
    }
}
