<?php /** @noinspection PhpConditionAlreadyCheckedInspection */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus\Id;

use CubicMushroom\Cqrs\Bus\Id\CommandId;
use CubicMushroom\Cqrs\Bus\Id\MessageIdInterface;
use CubicMushroom\Cqrs\Bus\Id\QueryId;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * Unit tests for QueryId.
 */
final class QueryIdTest extends TestCase
{
    public function test_implements_message_id_interface(): void
    {
        $queryId = new QueryId('test-query-id');

        $this->assertInstanceOf(MessageIdInterface::class, $queryId);
    }


    public function test_implements_stringable(): void
    {
        $queryId = new QueryId('test-query-id');

        $this->assertInstanceOf(Stringable::class, $queryId);
    }


    public function test_constructor_sets_id_property(): void
    {
        $id = 'test-query-id-123';
        $queryId = new QueryId($id);

        $this->assertEquals($id, $queryId->id);
    }


    public function test_to_string_returns_id(): void
    {
        $id = 'test-query-id-456';
        $queryId = new QueryId($id);

        $this->assertEquals($id, (string)$queryId);
        $this->assertEquals($id, $queryId->__toString());
    }


    public function test_accepts_empty_string(): void
    {
        $queryId = new QueryId('');

        $this->assertEquals('', $queryId->id);
        $this->assertEquals('', (string)$queryId);
    }


    public function test_accepts_ulid_format(): void
    {
        $ulid = '01K5K6P33FP68YWPEY8CB89J1J';
        $queryId = new QueryId($ulid);

        $this->assertEquals($ulid, $queryId->id);
        $this->assertEquals($ulid, (string)$queryId);
    }


    public function test_accepts_uuid_format(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $queryId = new QueryId($uuid);

        $this->assertEquals($uuid, $queryId->id);
        $this->assertEquals($uuid, (string)$queryId);
    }


    public function test_accepts_custom_string_format(): void
    {
        $customId = 'qry_2023_001_abc123';
        $queryId = new QueryId($customId);

        $this->assertEquals($customId, $queryId->id);
        $this->assertEquals($customId, (string)$queryId);
    }


    public function test_is_readonly(): void
    {
        $queryId = new QueryId('test-id');

        // This test verifies that the class is readonly by checking reflection
        $reflection = new ReflectionClass($queryId);
        $this->assertTrue($reflection->isReadOnly());
    }


    public function test_equality_comparison(): void
    {
        $id = 'same-query-id';
        $queryId1 = new QueryId($id);
        $queryId2 = new QueryId($id);
        $queryId3 = new QueryId('different-query-id');

        // Test string comparison
        $this->assertEquals((string)$queryId1, (string)$queryId2);
        $this->assertNotEquals((string)$queryId1, (string)$queryId3);

        // Test property comparison
        $this->assertEquals($queryId1->id, $queryId2->id);
        $this->assertNotEquals($queryId1->id, $queryId3->id);
    }


    public function test_can_be_used_in_string_context(): void
    {
        $id = 'context-test-id';
        $queryId = new QueryId($id);

        $message = "Query ID: $queryId";
        $this->assertEquals("Query ID: $id", $message);

        $concatenated = 'prefix-' . $queryId . '-suffix';
        $this->assertEquals("prefix-$id-suffix", $concatenated);
    }


    public function test_different_types_with_same_id_are_not_equal(): void
    {
        $id = 'same-id-value';
        $queryId = new QueryId($id);
        $commandId = new CommandId($id);

        // While the string values are the same, the objects are different types
        $this->assertEquals((string)$queryId, (string)$commandId);
        $this->assertNotEquals($queryId::class, $commandId::class);
    }


    public function test_can_be_used_as_array_key(): void
    {
        $queryId1 = new QueryId('key1');
        $queryId2 = new QueryId('key2');

        $array = [
            (string)$queryId1 => 'value1',
            (string)$queryId2 => 'value2',
        ];

        $this->assertEquals('value1', $array[(string)$queryId1]);
        $this->assertEquals('value2', $array[(string)$queryId2]);
    }
}
