<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus;

use CubicMushroom\Cqrs\Bus\Id\CommandId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\SymfonyCommandBus;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\Tests\Dummy\DummyStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Throwable;

/**
 * Unit tests for SymfonyCommandBus.
 */
final class SymfonyCommandBusTest extends TestCase
{
    private MessageBusInterface $messageBus;

    private SymfonyCommandBus $commandBus;


    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->commandBus = new SymfonyCommandBus($this->messageBus);
    }


    /**
     * @throws Throwable
     */
    public function test_dispatch_command_with_stamps(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $originalStamps = [
            new DelayStamp(100),
            new DummyStamp(),
        ];

        $dispatchedStamps = [...$originalStamps, new DispatchAfterCurrentBusStamp()];
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $command,
                $dispatchedStamps,
            )
            ->willReturn(
                new Envelope($command, [...$dispatchedStamps, new MessageIdStamp('01K5K6P33FP68YWPEY8CB89J1J')]),
            );

        $this->commandBus->dispatch($command, $originalStamps);
    }


    public function test_dispatch_command_adds_DispatchAfterCurrentBusStamp(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $command,
                $this->containsEqual(new DispatchAfterCurrentBusStamp()),
            )
            ->willReturn(
                new Envelope(
                    $command,
                    [new DispatchAfterCurrentBusStamp(), new MessageIdStamp('01K5K6P33FP68YWPEY8CB89J1J')],
                ),
            );

        $this->commandBus->dispatch($command);
    }


    public function test_dispatch_command_returns_request_id(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($command, $this->isArray())
            ->willReturn(
                new Envelope(
                    $command,
                    [new DispatchAfterCurrentBusStamp(), new MessageIdStamp('01K5K6P33FP68YWPEY8CB89J1J')],
                ),
            );

        $requestId = $this->commandBus->dispatch($command);

        $this->assertEquals(new CommandId('01K5K6P33FP68YWPEY8CB89J1J'), $requestId);
    }
}
