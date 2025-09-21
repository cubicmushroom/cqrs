<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\Id;

trait StringIdTrait
{
    public function __construct(
        public readonly string $id,
    ) {
    }


    public function __toString(): string
    {
        return $this->id;
    }
}