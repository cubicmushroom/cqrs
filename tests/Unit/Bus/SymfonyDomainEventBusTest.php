<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus;

use CubicMushroom\Cqrs\Bus\Id\DomainEventId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\SymfonyDomainEventBus;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use CubicMushroom\Cqrs\Tests\Dummy\DummyStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Unit tests for SymfonyDomainEventBus.
 */
final class SymfonyDomainEventBusTest extends TestCase
{
    private MessageBusInterface $messageBus;

    private SymfonyDomainEventBus $eventBus;


    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->eventBus = new SymfonyDomainEventBus($this->messageBus);
    }


    public function test_dispatch_domain_event_adds_DispatchAfterCurrentBusStamp(): void
    {
        $domainEvent = $this->createMock(DomainEventInterface::class);

        $dispatchedStamps = [new DispatchAfterCurrentBusStamp()];
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($domainEvent, $dispatchedStamps)
            ->willReturn(new Envelope($domainEvent, [...$dispatchedStamps, new MessageIdStamp('test-id')]));

        $this->eventBus->dispatch($domainEvent);
    }


    public function test_dispatch_domain_event_passes_on_stamps_provided(): void
    {
        $domainEvent = $this->createMock(DomainEventInterface::class);

        $originalStamps = [
            new DelayStamp(100),
            new DummyStamp(),
        ];

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(

                $domainEvent,
                $this->logicalAnd(
                    ...array_map(fn(StampInterface $stamp) => $this->containsEqual($stamp), $originalStamps),
                ),
            )
            ->willReturn(
                new Envelope(
                    $domainEvent,
                    [...$originalStamps, new DispatchAfterCurrentBusStamp(), new MessageIdStamp('test-id')],
                ),
            );

        $this->eventBus->dispatch($domainEvent, $originalStamps);
    }


    public function test_dispatch_returns_the_message_id(): void
    {
        $domainEvent = $this->createMock(DomainEventInterface::class);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($domainEvent, $this->isArray())
            ->willReturn(new Envelope($domainEvent, [new MessageIdStamp('test-id')]));

        $result = $this->eventBus->dispatch($domainEvent);

        $this->assertEquals(new DomainEventId('test-id'), $result->id);
    }
}
