<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\Id;

/**
 * ID for a domain event.
 */
final readonly class DomainEventId implements MessageIdInterface
{
    use StringIdTrait;
}