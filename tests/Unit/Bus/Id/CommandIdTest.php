<?php /** @noinspection PhpConditionAlreadyCheckedInspection */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus\Id;

use CubicMushroom\Cqrs\Bus\Id\CommandId;
use CubicMushroom\Cqrs\Bus\Id\MessageIdInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * Unit tests for CommandId.
 */
final class CommandIdTest extends TestCase
{
    public function test_implements_message_id_interface(): void
    {
        $commandId = new CommandId('test-command-id');

        $this->assertInstanceOf(MessageIdInterface::class, $commandId);
    }


    public function test_implements_stringable(): void
    {
        $commandId = new CommandId('test-command-id');

        $this->assertInstanceOf(Stringable::class, $commandId);
    }


    public function test_constructor_sets_id_property(): void
    {
        $id = 'test-command-id-123';
        $commandId = new CommandId($id);

        $this->assertEquals($id, $commandId->id);
    }


    public function test_to_string_returns_id(): void
    {
        $id = 'test-command-id-456';
        $commandId = new CommandId($id);

        $this->assertEquals($id, (string)$commandId);
        $this->assertEquals($id, $commandId->__toString());
    }


    public function test_accepts_empty_string(): void
    {
        $commandId = new CommandId('');

        $this->assertEquals('', $commandId->id);
        $this->assertEquals('', (string)$commandId);
    }


    public function test_accepts_ulid_format(): void
    {
        $ulid = '01K5K6P33FP68YWPEY8CB89J1J';
        $commandId = new CommandId($ulid);

        $this->assertEquals($ulid, $commandId->id);
        $this->assertEquals($ulid, (string)$commandId);
    }


    public function test_accepts_uuid_format(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $commandId = new CommandId($uuid);

        $this->assertEquals($uuid, $commandId->id);
        $this->assertEquals($uuid, (string)$commandId);
    }


    public function test_accepts_custom_string_format(): void
    {
        $customId = 'cmd_2023_001_abc123';
        $commandId = new CommandId($customId);

        $this->assertEquals($customId, $commandId->id);
        $this->assertEquals($customId, (string)$commandId);
    }


    public function test_is_readonly(): void
    {
        $commandId = new CommandId('test-id');

        // This test verifies that the class is readonly by checking reflection
        $reflection = new ReflectionClass($commandId);
        $this->assertTrue($reflection->isReadOnly());
    }


    public function test_equality_comparison(): void
    {
        $id = 'same-command-id';
        $commandId1 = new CommandId($id);
        $commandId2 = new CommandId($id);
        $commandId3 = new CommandId('different-command-id');

        // Test string comparison
        $this->assertEquals((string)$commandId1, (string)$commandId2);
        $this->assertNotEquals((string)$commandId1, (string)$commandId3);

        // Test property comparison
        $this->assertEquals($commandId1->id, $commandId2->id);
        $this->assertNotEquals($commandId1->id, $commandId3->id);
    }


    public function test_can_be_used_in_string_context(): void
    {
        $id = 'context-test-id';
        $commandId = new CommandId($id);

        $message = "Command ID: $commandId";
        $this->assertEquals("Command ID: $id", $message);

        $concatenated = 'prefix-' . $commandId . '-suffix';
        $this->assertEquals("prefix-$id-suffix", $concatenated);
    }
}
