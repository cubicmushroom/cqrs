<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\Id;

use Stringable;

/**
 * Interface for a message ID.
 */
interface MessageIdInterface extends Stringable
{
}