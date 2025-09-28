<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\DomainEvent;

use DateTimeImmutable;
use Symfony\Component\Clock\DatePoint;

/**
 * Abstract base class for domain events providing common functionality.
 *
 * This class provides default implementations for timestamp tracking and event naming
 * using modern PHP 8.4 readonly properties with property hooks.
 * Message identification is now handled by the MessageIdStamp on the envelope,
 * keeping events focused purely on business data and logic.
 */
abstract readonly class AbstractDomainEvent implements DomainEventInterface
{
    public function __construct(
        public DateTimeImmutable $occurredAt = new DatePoint(),
    ) {
    }
}
