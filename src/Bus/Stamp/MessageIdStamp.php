<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\Stamp;

use CubicMushroom\Cqrs\Bus\Id\MessageIdInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp that carries a unique message identifier.
 *
 * This stamp is applied to the message envelope to provide tracking,
 * logging, and correlation capabilities without polluting the message
 * itself with infrastructure concerns.
 */
final readonly class MessageIdStamp implements StampInterface
{
    public function __construct(
        public MessageIdInterface $messageId,
    ) {
    }
}
