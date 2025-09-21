<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\StampFactory;

use CubicMushroom\Cqrs\Bus\Id\MessageIdInterface;
use LogicException;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Interface for a message ID stamp provider.
 *
 * This interface defines the contract for generating unique message IDs.
 */
interface MessageIdStampFactoryInterface
{
    /**
     * Attach an ID stamp to the array of stamps.
     *
     * If the array already contains a MessageIdStamp, throw a LogicException.
     *
     * @param StampInterface[] $stamps The array of stamps to attach the ID stamp to.
     * @param callable(string): MessageIdInterface $idFactory A callable to generate the correct type of message ID.
     *
     * @return StampInterface[]
     *
     * @throws LogicException If the array already contains a MessageIdStamp
     */
    public function attachStamp(array $stamps, callable $idFactory): array;
}