<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Dummy;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class DummyStamp implements StampInterface
{
}