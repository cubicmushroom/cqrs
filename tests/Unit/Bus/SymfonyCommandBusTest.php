<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus;

use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactoryInterface;
use CubicMushroom\Cqrs\Bus\SymfonyCommandBus;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\Tests\Dummy\DummyStamp;
use PHPUnit\Framework\MockObject\Stub\ReturnCallback;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Throwable;

use function array_any;

/**
 * Unit tests for SymfonyCommandBus.
 */
final class SymfonyCommandBusTest extends TestCase
{
    private MessageIdStampFactoryInterface $idStampFactory;

    private MessageBusInterface $messageBus;

    private LoggerInterface $logger;

    private SymfonyCommandBus $commandBus;

    private MessageIdStamp $messageIdStamp;


    protected function setUp(): void
    {
        $this->idStampFactory = $this->createMock(MessageIdStampFactoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->commandBus = new SymfonyCommandBus($this->idStampFactory, $this->messageBus, $this->logger);

        $this->messageIdStamp = new MessageIdStamp('01K5K6P33FP68YWPEY8CB89J1J');
    }


    /**
     * @throws Throwable
     */
    public function test_dispatch_command_with_stamps(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $passedStamps = [
            new DelayStamp(100),
            new DummyStamp(),
        ];

        $messageIdStamp = new MessageIdStamp('01K5K6P33FP68YWPEY8CB89J1J');
        $this->idStampFactory
            ->expects($this->once())
            ->method('attachStamp')
            ->with($passedStamps)
            ->willReturn([...$passedStamps, $messageIdStamp]);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    function (Envelope $envelope) use ($passedStamps, $messageIdStamp) {
                        // If any of the passed stamps are not present in the dispatched stamps, return false
                        return !array_any(
                            $passedStamps,
                            fn(StampInterface $stamp) => !array_any(
                                $envelope->all($stamp::class),
                                fn(StampInterface $dispatchedStamp) => $dispatchedStamp == $stamp,
                            ),
                        );
                    },
                ),
            )
            ->willReturn(new Envelope($command));

        $this->commandBus->dispatch($command, $passedStamps);
    }


    public function test_dispatch_command_adds_DispatchAfterCurrentBusStamp(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $this->idStampFactory
            ->expects($this->once())
            ->method('attachStamp')
            ->with([])
            ->willReturn([$this->messageIdStamp]);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    fn(Envelope $envelope) => null !== $envelope->last(DispatchAfterCurrentBusStamp::class),
                ),
            )
            ->willReturn(new Envelope($command));

        $this->commandBus->dispatch($command);
    }


    public function test_dispatch_command_adds_MessageIdStamp_and_returns_request_id(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $this->idStampFactory
            ->expects($this->once())
            ->method('attachStamp')
            ->with([])
            ->willReturn([$this->messageIdStamp]);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    fn(Envelope $envelope) => $envelope->all(MessageIdStamp::class) === [$this->messageIdStamp],
                ),
            )
            ->willReturn(new Envelope($command));

        $requestId = $this->commandBus->dispatch($command);

        $this->assertEquals($this->messageIdStamp->messageId, $requestId);
    }


    public function test_dispatch_logs_messages_on_success(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $this->idStampFactory
            ->expects($this->once())
            ->method('attachStamp')
            ->with([])
            ->willReturn([$this->messageIdStamp]);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($command));

        $logCalls = [];
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$logCalls) {
                $logCalls[] = [$message, $context];
            });

        $this->commandBus->dispatch($command);

        // Verify the log calls were made in the correct order
        $this->assertCount(2, $logCalls);
        $this->assertEquals('Dispatching command', $logCalls[0][0]);
        $this->assertEquals([
            'message_id' => '01K5K6P33FP68YWPEY8CB89J1J',
            'command_type' => $command::class,
        ], $logCalls[0][1]);
        $this->assertEquals('Command dispatched successfully', $logCalls[1][0]);
        $this->assertEquals([
            'message_id' => '01K5K6P33FP68YWPEY8CB89J1J',
            'command_type' => $command::class,
        ], $logCalls[1][1]);
    }


    public function test_dispatch_command_logs_error_on_exception(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $this->idStampFactory
            ->expects($this->once())
            ->method('attachStamp')
            ->with([])
            ->willReturn([$this->messageIdStamp]);

        $exception = new RuntimeException('Test exception');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Dispatching command', $this->isType('array'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to dispatch command', $this->isType('array'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->commandBus->dispatch($command);
    }


    public function test_dispatch_returns_unique_request_ids(): void
    {
        $command1 = $this->createMock(CommandInterface::class);

        $command2 = $this->createMock(CommandInterface::class);

        $this->idStampFactory
            ->expects($this->exactly(2))
            ->method('attachStamp')
            ->with($this->isArray())
            ->willReturnOnConsecutiveCalls(
                new ReturnCallback(
                    fn(array $stamps) => [...$stamps, new MessageIdStamp('01K5K6P33FP68YWPEY8CB89J1J')],
                ),
                new ReturnCallback(
                    fn(array $stamps) => [...$stamps, new MessageIdStamp('01K5KANAS49AP7T8MFN9RCVCT8')],
                ),
            );

        $this->messageBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnArgument(0);

        $requestId1 = $this->commandBus->dispatch($command1);
        $requestId2 = $this->commandBus->dispatch($command2);

        $this->assertNotEquals($requestId1->id, $requestId2->id);
    }
}
