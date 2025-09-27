<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus;

use CubicMushroom\Cqrs\Bus\Id\CommandId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\Exception\MessageIdNotFoundException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Throwable;

/**
 * Symfony Messenger implementation of the command bus.
 *
 * This implementation uses Symfony Messenger to dispatch commands asynchronously
 * and provides logging and metrics collection as per global defaults.
 * Message identification is handled via MessageIdStamp on the envelope.
 */
final readonly class SymfonyCommandBus implements CommandBusInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }


    /**
     * @inheritDoc
     *
     * @throws ExceptionInterface if something goes wrong with the message dispatching.
     * @throws MessageIdNotFoundException if the message ID stamp is not found on the envelope.
     * @throws Throwable if anything else goes wrong.
     */
    public function dispatch(CommandInterface $command, array $stamps = []): CommandId
    {
        // Dispatch the command asynchronously with stamps
        $envelope = $this->messageBus->dispatch($command, [...$stamps, new DispatchAfterCurrentBusStamp()]);

        $messageId = MessageIdStamp::getMessageId($envelope);

        // Wrap the string ID in a CommandId for type safety
        return new CommandId($messageId);
    }
}
