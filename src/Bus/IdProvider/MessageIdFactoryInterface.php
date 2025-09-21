<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\IdProvider;

/**
 * Interface for a message ID provider.
 *
 * This interface defines the contract for generating unique message IDs.
 */
interface MessageIdFactoryInterface
{
    /**
     * Generate the next message ID.
     */
    public function nextId(): string;
}