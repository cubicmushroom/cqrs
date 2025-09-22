<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Event;

use CubicMushroom\Cqrs\DomainEvent\AbstractDomainEvent;
use CubicMushroom\Cqrs\DomainEvent\DomainEventTrait;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbstractDomainEvent.
 */
final class AbstractDomainEventTest extends TestCase
{
    public function test_event_has_occurred_at_timestamp(): void
    {
        $now = new DateTimeImmutable();
        $event = new TestDomainEvent($now);

        $occurredAt = $event->occurredAt;

        $this->assertInstanceOf(\DateTimeImmutable::class, $occurredAt);
        $this->assertEquals($now, $occurredAt);
    }


    public function test_event_properties_are_immutable(): void
    {
        $now = new DateTimeImmutable();
        $event = new TestDomainEvent($now);

        $originalOccurredAt = $event->occurredAt;

        // Call multiple times to ensure they don't change
        $this->assertSame($originalOccurredAt, $event->occurredAt);
        $this->assertSame($originalOccurredAt, $event->occurredAt);
    }
}

/**
 * Test implementation of AbstractDomainEvent for testing purposes.
 */
final readonly class TestDomainEvent extends AbstractDomainEvent
{
}
