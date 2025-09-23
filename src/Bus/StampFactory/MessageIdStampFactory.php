<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\StampFactory;

use CubicMushroom\Cqrs\Bus\IdProvider\MessageIdFactoryInterface;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\StampFactory\Exception\MessageIdStampAlreadyExistsException;
use stdClass;
use Symfony\Component\Messenger\Envelope;

final readonly class MessageIdStampFactory implements MessageIdStampFactoryInterface
{
    public function __construct(
        private(set) MessageIdFactoryInterface $messageIdProvider,
    ) {
    }


    /**
     * @inheritDoc
     *
     * @throws MessageIdStampAlreadyExistsException If the array already contains a MessageIdStamp.
     */
    public function attachStamp(array $stamps): array
    {
        // Use Envelop class to check if the message already has a MessageIdStamp
        $envelope = new Envelope(new stdClass(), $stamps);

        $existingIdStamp = $envelope->last(MessageIdStamp::class);
        if (!$existingIdStamp instanceof MessageIdStamp) {
            return [...$stamps, new MessageIdStamp($this->messageIdProvider->nextId())];
        }

        throw new MessageIdStampAlreadyExistsException($existingIdStamp);
    }
}
