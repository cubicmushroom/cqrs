<?php /** @noinspection PhpConditionAlreadyCheckedInspection */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus\Id;

use CubicMushroom\Cqrs\Bus\Id\CommandId;
use CubicMushroom\Cqrs\Bus\Id\DomainEventId;
use CubicMushroom\Cqrs\Bus\Id\MessageIdInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * Unit tests for DomainEventId.
 */
final class DomainEventIdTest extends TestCase
{
    public function test_implements_message_id_interface(): void
    {
        $domainEventId = new DomainEventId('test-event-id');

        $this->assertInstanceOf(MessageIdInterface::class, $domainEventId);
    }


    public function test_implements_stringable(): void
    {
        $domainEventId = new DomainEventId('test-event-id');

        $this->assertInstanceOf(Stringable::class, $domainEventId);
    }


    public function test_constructor_sets_id_property(): void
    {
        $id = 'test-event-id-123';
        $domainEventId = new DomainEventId($id);

        $this->assertEquals($id, $domainEventId->id);
    }


    public function test_to_string_returns_id(): void
    {
        $id = 'test-event-id-456';
        $domainEventId = new DomainEventId($id);

        $this->assertEquals($id, (string)$domainEventId);
        $this->assertEquals($id, $domainEventId->__toString());
    }


    public function test_accepts_empty_string(): void
    {
        $domainEventId = new DomainEventId('');

        $this->assertEquals('', $domainEventId->id);
        $this->assertEquals('', (string)$domainEventId);
    }


    public function test_accepts_ulid_format(): void
    {
        $ulid = '01K5K6P33FP68YWPEY8CB89J1J';
        $domainEventId = new DomainEventId($ulid);

        $this->assertEquals($ulid, $domainEventId->id);
        $this->assertEquals($ulid, (string)$domainEventId);
    }


    public function test_accepts_uuid_format(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $domainEventId = new DomainEventId($uuid);

        $this->assertEquals($uuid, $domainEventId->id);
        $this->assertEquals($uuid, (string)$domainEventId);
    }


    public function test_accepts_custom_string_format(): void
    {
        $customId = 'evt_2023_001_abc123';
        $domainEventId = new DomainEventId($customId);

        $this->assertEquals($customId, $domainEventId->id);
        $this->assertEquals($customId, (string)$domainEventId);
    }


    public function test_is_readonly(): void
    {
        $domainEventId = new DomainEventId('test-id');

        // This test verifies that the class is readonly by checking reflection
        $reflection = new ReflectionClass($domainEventId);
        $this->assertTrue($reflection->isReadOnly());
    }


    public function test_equality_comparison(): void
    {
        $id = 'same-event-id';
        $domainEventId1 = new DomainEventId($id);
        $domainEventId2 = new DomainEventId($id);
        $domainEventId3 = new DomainEventId('different-event-id');

        // Test string comparison
        $this->assertEquals((string)$domainEventId1, (string)$domainEventId2);
        $this->assertNotEquals((string)$domainEventId1, (string)$domainEventId3);

        // Test property comparison
        $this->assertEquals($domainEventId1->id, $domainEventId2->id);
        $this->assertNotEquals($domainEventId1->id, $domainEventId3->id);
    }


    public function test_can_be_used_in_string_context(): void
    {
        $id = 'context-test-id';
        $domainEventId = new DomainEventId($id);

        $message = "Domain Event ID: $domainEventId";
        $this->assertEquals("Domain Event ID: $id", $message);

        $concatenated = 'prefix-' . $domainEventId . '-suffix';
        $this->assertEquals("prefix-$id-suffix", $concatenated);
    }


    public function test_different_types_with_same_id_are_not_equal(): void
    {
        $id = 'same-id-value';
        $domainEventId = new DomainEventId($id);
        $commandId = new CommandId($id);

        // While the string values are the same, the objects are different types
        $this->assertEquals((string)$domainEventId, (string)$commandId);
        $this->assertNotEquals($domainEventId::class, $commandId::class);
    }
}
