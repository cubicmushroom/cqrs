<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\StampFactory;

use CubicMushroom\Cqrs\Bus\StampFactory\Exception\MessageIdStampAlreadyExistsException;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Interface for a message ID stamp provider.
 *
 * This interface defines the contract for generating unique message IDs.
 * The stamp factory generates string IDs that are wrapped in specific
 * ID classes by the individual buses for type safety.
 */
interface MessageIdStampFactoryInterface
{
    /**
     * Attach an ID stamp to the array of stamps.
     *
     * If the array already contains a MessageIdStamp, throw a LogicException.
     *
     * @param StampInterface[] $stamps The array of stamps to attach the ID stamp to.
     *
     * @return StampInterface[]
     *
     * @throws MessageIdStampAlreadyExistsException If the array already contains a MessageIdStamp.
     */
    public function attachStamp(array $stamps): array;
}
