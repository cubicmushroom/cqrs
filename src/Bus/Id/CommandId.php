<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\Id;

/**
 * ID for a command.
 */
final readonly class CommandId implements MessageIdInterface
{
    use StringIdTrait;
}