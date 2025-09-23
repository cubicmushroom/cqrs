<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus;

use CubicMushroom\Cqrs\Bus\Id\CommandId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactoryInterface;
use CubicMushroom\Cqrs\Command\CommandInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
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
final class SymfonyCommandBus implements CommandBusInterface
{
    public function __construct(
        private readonly MessageIdStampFactoryInterface $idStampFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }


    /**
     * @inheritDoc
     */
    public function dispatch(CommandInterface $command, array $stamps = []): CommandId
    {
        $stamps = $this->idStampFactory->attachStamp($stamps);

        // Add the DispatchAfterCurrentBusStamp
        $stamps = [...$stamps, new DispatchAfterCurrentBusStamp()];

        $envelope = new Envelope($command, $stamps);
        $messageIdStamp = $envelope->last(MessageIdStamp::class);
        assert($messageIdStamp instanceof MessageIdStamp);

        // Wrap the string ID in a CommandId for type safety
        $commandId = new CommandId($messageIdStamp->messageId);

        try {
            // Log the command dispatch for audit purposes
            $this->logger->info('Dispatching command', [
                'message_id' => (string)$commandId,
                'command_type' => $command::class,
            ]);

            // Dispatch the command asynchronously with stamps
            $this->messageBus->dispatch($envelope);

            // Log successful dispatch
            $this->logger->info('Command dispatched successfully', [
                'message_id' => (string)$commandId,
                'command_type' => $command::class,
            ]);

            return $commandId;
        } catch (Throwable $exception) {
            // Log the error for debugging and monitoring
            $this->logger->error('Failed to dispatch command', [
                'message_id' => (string)$commandId,
                'command_type' => $command::class,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
