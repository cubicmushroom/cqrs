<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\IdProvider;

use Symfony\Component\Uid\Ulid;

/**
 * Implementation of MessageIdFactoryInterface that generates ULID IDs.
 */
final readonly class UlidMessageIdFactory implements MessageIdFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function nextId(): string
    {
        return (string)new Ulid();
    }
}