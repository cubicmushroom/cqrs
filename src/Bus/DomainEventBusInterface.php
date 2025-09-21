<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus;

use CubicMushroom\Cqrs\Bus\Id\DomainEventId;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use Exception;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Interface for the event bus in the CQRS system.
 *
 * The event bus is responsible for dispatching domain events to their subscribers.
 * Events should be dispatched asynchronously to allow for better system decoupling
 * and performance.
 */
interface DomainEventBusInterface
{
    /**
     * Dispatch a domain event to all registered subscribers.
     *
     * Domain events are dispatched asynchronously to allow multiple handlers
     * to process the same event independently. This supports the event-driven
     * architecture pattern.
     *
     * @param DomainEventInterface $event The domain event to dispatch.
     * @param StampInterface[] $stamps Optional stamps to attach to the event.
     *
     * @return DomainEventId ID that can be used to track the event processing.
     *
     * @throws Exception When the event cannot be dispatched.
     */
    public function dispatch(DomainEventInterface $event, array $stamps = []): DomainEventId;
}
