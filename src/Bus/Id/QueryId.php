<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\Id;

/**
 * A unique identifier for a query.
 */
final readonly class QueryId implements MessageIdInterface
{
    use StringIdTrait;
}