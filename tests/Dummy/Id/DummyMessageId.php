<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Dummy\Id;

use CubicMushroom\Cqrs\Bus\Id\MessageIdInterface;
use JsonSerializable;

final readonly class DummyMessageId implements MessageIdInterface, JsonSerializable
{
    public function __construct(
        private(set) string $id,
    ) {
    }


    public function __toString(): string
    {
        return $this->id;
    }


    public function jsonSerialize(): string
    {
        return $this->id;
    }
}