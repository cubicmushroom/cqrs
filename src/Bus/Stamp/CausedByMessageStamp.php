<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\Stamp;

use CubicMushroom\Cqrs\MessageTypeEnum;
use Stringable;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp identifying a message responsible for dispatching the current one.
 */
final readonly class CausedByMessageStamp implements StampInterface, Stringable
{
    public function __construct(
        public MessageTypeEnum $messageType,
        public string $messageId,
    ) {
    }


    public function __toString(): string
    {
        return $this->messageType->name . ':' . $this->messageId;
    }
}
