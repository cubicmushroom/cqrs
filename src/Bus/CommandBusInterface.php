<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus;

use CubicMushroom\Cqrs\Bus\Id\CommandId;
use CubicMushroom\Cqrs\Command\CommandInterface;
use Exception;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Interface for the command bus in the CQRS system.
 *
 * The command bus is responsible for dispatching commands to their appropriate handlers.
 * Commands should be dispatched asynchronously where possible as per global defaults.
 */
interface CommandBusInterface
{
    /**
     * Dispatch a command for processing.
     *
     * Commands are dispatched asynchronously by default to improve system performance
     * and allow for better scalability. The command will be queued and processed
     * by the appropriate handler.
     *
     * @param CommandInterface $command The command to dispatch
     * @param StampInterface[] $stamps Optional stamps to attach to the command
     *
     * @return CommandId ID that can be used to track the command processing
     *
     * @throws Exception When the command cannot be dispatched
     */
    public function dispatch(CommandInterface $command, array $stamps = []): CommandId;
}
