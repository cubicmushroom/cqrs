<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\DomainEvent;

use DateTimeImmutable;

/**
 * Base interface for all domain events in the CQRS system.
 *
 * Domain events represent something that has happened in the domain
 * and are used to communicate changes between bounded contexts.
 *
 * Message identification is now handled by the MessageIdStamp on the envelope,
 * keeping the event focused purely on business data.
 *
 * Uses modern PHP 8.4 readonly properties instead of getter methods.
 */
interface DomainEventInterface
{
    /**
     * The timestamp when this event occurred.
     */
    public DateTimeImmutable $occurredAt {
        get;
    }
}
