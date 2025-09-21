<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus;

use CubicMushroom\Cqrs\Bus\Id\DomainEventId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactoryInterface;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Throwable;

use function assert;

/**
 * Symfony Messenger implementation of the event bus.
 *
 * This implementation uses Symfony Messenger to dispatch domain events asynchronously
 * and provides logging and metrics collection as per global defaults.
 */
final class SymfonyDomainEventBus implements DomainEventBusInterface
{
    public function __construct(
        private readonly MessageIdStampFactoryInterface $idStampProvider,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }


    /**
     * @param StampInterface[] $stamps
     *
     * @throws Throwable
     * @throws ExceptionInterface
     */
    public function dispatch(DomainEventInterface $event, array $stamps = []): DomainEventId
    {
        $stamps = $this->idStampProvider->attachStamp(
            $stamps,
            fn(string $messageId) => new DomainEventId($messageId),
        );

        // Add the DispatchAfterCurrentBusStamp
        $stamps = [...$stamps, new DispatchAfterCurrentBusStamp()];

        $envelope = new Envelope($event, $stamps);
        $messageIdStamp = $envelope->last(MessageIdStamp::class);
        assert($messageIdStamp instanceof MessageIdStamp);
        $eventId = $messageIdStamp->messageId;
        assert($eventId instanceof DomainEventId);

        try {
            // Log the event dispatch for audit purposes
            $this->logger->info('Dispatching event', [
                'message_id' => $eventId,
                'event_type' => $event::class,
                'occurred_at' => $event->occurredAt->format(DateTimeInterface::ATOM),
            ]);

            // Dispatch the event asynchronously to allow multiple handlers
            $this->messageBus->dispatch($envelope);

            // Log successful dispatch
            $this->logger->info('Event dispatched successfully', [
                'message_id' => $eventId,
                'event_type' => $event::class,
            ]);

            return $eventId;
        } catch (Throwable $exception) {
            // Log the error for debugging and monitoring
            $this->logger->error('Failed to dispatch event', [
                'message_id' => $eventId,
                'event_type' => $event::class,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
