<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus;

use CubicMushroom\Cqrs\Bus\Id\DomainEventId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactoryInterface;
use CubicMushroom\Cqrs\Bus\SymfonyDomainEventBus;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use CubicMushroom\Cqrs\Tests\Dummy\DummyStamp;
use CubicMushroom\Cqrs\Tests\Dummy\Event\DummyDomainEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

use function array_keys;

/**
 * Unit tests for SymfonyDomainEventBus.
 */
final class SymfonyDomainEventBusTest extends TestCase
{

    private MessageBusInterface $messageBus;

    private LoggerInterface $logger;

    private MessageIdStamp $messageIdStamp;


    protected function setUp(): void
    {
        $messageIdStampFactory = $this->createMock(MessageIdStampFactoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventBus = new SymfonyDomainEventBus(
            $messageIdStampFactory,
            $this->messageBus,
            $this->logger,
        );

        $this->messageIdStamp = new MessageIdStamp(new DomainEventId('01K5K6P33FP68YWPEY8CB89J1J'));
        $messageIdStampFactory
            ->method('attachStamp')
            ->with($this->isArray(), $this->isCallable())
            ->willReturnCallback(fn(array $stamps) => [...$stamps, $this->messageIdStamp]);
    }


    public function test_dispatch_domain_event_adds_message_id(): void
    {
        $domainEvent = $this->createMock(DomainEventInterface::class);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    fn(Envelope $envelope) => $envelope->all(MessageIdStamp::class) === [$this->messageIdStamp],
                ),
            )
            ->willReturnArgument(0);

        $this->eventBus->dispatch($domainEvent);
    }


    public function test_dispatch_domain_event_adds_DispatchAfterCurrentBusStamp(): void
    {
        $domainEvent = $this->createMock(DomainEventInterface::class);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    fn(Envelope $envelope) => $envelope->last(DispatchAfterCurrentBusStamp::class)
                        instanceof
                        DispatchAfterCurrentBusStamp,
                ),
            )
            ->willReturnArgument(0);

        $this->eventBus->dispatch($domainEvent);
    }


    public function test_dispatch_domain_event_passes_on_stamps_provided(): void
    {
        $domainEvent = $this->createMock(DomainEventInterface::class);

        $providedStamps = [
            new DelayStamp(100),
            new DummyStamp(),
        ];

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    function (Envelope $envelope) use ($providedStamps) {
                        $dispatchedStamps = $envelope->all();

                        $this->assertArrayHasKey(DelayStamp::class, $dispatchedStamps);
                        $this->assertEquals([$providedStamps[0]], $dispatchedStamps[DelayStamp::class]);
                        $this->assertArrayHasKey(DummyStamp::class, $dispatchedStamps);
                        $this->assertEquals([$providedStamps[1]], $dispatchedStamps[DummyStamp::class]);

                        return true;
                    },
                ),
            )
            ->willReturnArgument(0);

        $this->eventBus->dispatch($domainEvent, $providedStamps);
    }


    public function test_dispatch_returns_the_message_id(): void
    {
        $domainEvent = $this->createMock(DomainEventInterface::class);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(Envelope::class))
            ->willReturnArgument(0);

        $result = $this->eventBus->dispatch($domainEvent);

        $this->assertEquals($this->messageIdStamp->messageId, $result->id);
    }


    public function test_dispatch_logs_messages_on_success(): void
    {
        $occurredAt = '2025-09-21T10:34:00+01:00';
        $domainEvent = new DummyDomainEvent(new DateTimeImmutable($occurredAt));

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(Envelope::class))
            ->willReturnArgument(0);

        $logCalls = [];
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$logCalls) {
                $logCalls[] = [$message, $context];
            });

        $this->eventBus->dispatch($domainEvent);

        // Verify the log calls were made in the correct order
        $this->assertCount(2, $logCalls);
        $this->assertEquals('Dispatching event', $logCalls[0][0]);
        $this->assertEquals([
            'message_id' => $this->messageIdStamp->messageId,
            'occurred_at' => $occurredAt,
            'event_type' => $domainEvent::class,
        ], $logCalls[0][1]);
        $this->assertEquals('Event dispatched successfully', $logCalls[1][0]);
        $this->assertEquals([
            'message_id' => $this->messageIdStamp->messageId,
            'event_type' => DummyDomainEvent::class,
        ], $logCalls[1][1]);
    }


    public function test_dispatch_query_logs_error_on_exception(): void
    {
        $occurredAt = '2025-09-21T10:34:00+01:00';
        $domainEvent = new DummyDomainEvent(new DateTimeImmutable($occurredAt));

        $exception = new RuntimeException('Test exception');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Dispatching event',
                [
                    'message_id' => $this->messageIdStamp->messageId,
                    'event_type' => $domainEvent::class,
                    'occurred_at' => $occurredAt,
                ],
            );

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to dispatch event',
                $this->callback(
                    function (array $context) use ($domainEvent) {
                        $this->assertEquals(['message_id', 'event_type', 'error', 'trace'], array_keys($context));
                        $this->assertEquals($this->messageIdStamp->messageId, $context['message_id']);
                        $this->assertEquals($domainEvent::class, $context['event_type']);
                        $this->assertEquals('Test exception', $context['error']);

                        return true;
                    },
                ),
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->eventBus->dispatch($domainEvent);
    }
}