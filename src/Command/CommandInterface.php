<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Command;

/**
 * Base interface for all commands in the CQRS system.
 *
 * Commands represent the intention to change state in the system.
 * They should be immutable and contain all the data needed to perform the action.
 */
interface CommandInterface
{
}
