<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus;

use CubicMushroom\Cqrs\Bus\Id\DomainEventId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Throwable;

use function assert;

/**
 * Symfony Messenger implementation of the event bus.
 *
 * This implementation uses Symfony Messenger to dispatch domain events asynchronously
 * and provides logging and metrics collection as per global defaults.
 */
final readonly class SymfonyDomainEventBus implements DomainEventBusInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }


    /**
     * @inheritDoc
     *
     * @throws ExceptionInterface if something goes wrong with the message dispatching.
     * @throws Throwable if anything else goes wrong.
     */
    public function dispatch(DomainEventInterface $event, array $stamps = []): DomainEventId
    {
        // Dispatch the event asynchronously to allow multiple handlers
        $envelope = $this->messageBus->dispatch($event, [...$stamps, new DispatchAfterCurrentBusStamp()]);

        $messageIdStamp = $envelope->last(MessageIdStamp::class);
        assert($messageIdStamp instanceof MessageIdStamp);

        return new DomainEventId($messageIdStamp->messageId);
    }
}
