<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Middleware;

use CubicMushroom\Cqrs\Bus\StampFactory\Exception\MessageIdStampAlreadyExistsException;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class MessageIdStampMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageIdStampFactoryInterface $messageIdStampFactory,
    ) {
    }


    /**
     * @inheritDoc
     *
     * @throws ExceptionInterface if an exception is thrown by the stack.
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            // Let MessageIdStampFactory handle the check for existing stamps
            $stamps = $this->messageIdStampFactory->attachStamp($envelope->all());
        } catch (MessageIdStampAlreadyExistsException $e) {
            throw new UnrecoverableMessageHandlingException('Unable to attach MessageIdStamp to envelope.', 0, $e);
        }

        // Create a new envelope with the added stamp
        $envelope = new Envelope($envelope->getMessage(), $stamps);

        return $stack->next()->handle($envelope, $stack);
    }
}
