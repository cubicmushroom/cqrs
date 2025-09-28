<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Middleware;

use CubicMushroom\Cqrs\Bus\IdProvider\MessageIdFactoryInterface;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class MessageIdStampMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageIdFactoryInterface $messageIdFactory,
    ) {
    }


    /**
     * @inheritDoc
     *
     * @throws ExceptionInterface if an exception is thrown by the stack.
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(MessageIdStamp::class)) {
            $envelope = $envelope->with(new MessageIdStamp($this->messageIdFactory->nextId()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
