<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\Stamp;

use CubicMushroom\Cqrs\Exception\MessageIdNotFoundException;
use Symfony\Component\Messenger\Envelope;
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
    /**
     * Helper method to retrieve the message ID from the envelope.
     *
     * @throws MessageIdNotFoundException if the message ID stamp is not found on the envelope.
     */
    public static function getMessageId(Envelope $envelope): string
    {
        return $envelope->last(self::class)?->messageId
            ?? throw new MessageIdNotFoundException();
    }


    public function __construct(
        public string $messageId,
    ) {
    }
}
