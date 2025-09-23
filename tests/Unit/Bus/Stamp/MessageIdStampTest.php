<?php /** @noinspection PhpConditionAlreadyCheckedInspection */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus\Stamp;

use CubicMushroom\Cqrs\Bus\Id\CommandId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Tests\Dummy\Id\DummyMessageId;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Unit tests for MessageIdStamp.
 */
final class MessageIdStampTest extends TestCase
{
    public function test_implements_stamp_interface(): void
    {
        $stamp = new MessageIdStamp('test-message-id');

        $this->assertInstanceOf(StampInterface::class, $stamp);
    }


    public function test_is_readonly(): void
    {
        $stamp = new MessageIdStamp('test-message-id');

        $reflection = new ReflectionClass($stamp);
        $this->assertTrue($reflection->isReadOnly());
    }


    public function test_constructor_sets_message_id_property(): void
    {
        $messageId = 'test-message-id-123';
        $stamp = new MessageIdStamp($messageId);

        $this->assertEquals($messageId, $stamp->messageId);
    }


    public function test_accepts_empty_string_message_id(): void
    {
        $stamp = new MessageIdStamp('');

        $this->assertEquals('', $stamp->messageId);
    }


    public function test_accepts_ulid_format_message_id(): void
    {
        $ulid = '01K5K6P33FP68YWPEY8CB89J1J';
        $stamp = new MessageIdStamp($ulid);

        $this->assertEquals($ulid, $stamp->messageId);
    }


    public function test_accepts_uuid_format_message_id(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $stamp = new MessageIdStamp($uuid);

        $this->assertEquals($uuid, $stamp->messageId);
    }


    public function test_accepts_custom_string_format_message_id(): void
    {
        $customId = 'msg_2023_001_abc123';
        $stamp = new MessageIdStamp($customId);

        $this->assertEquals($customId, $stamp->messageId);
    }


    public function test_message_id_property_is_public(): void
    {
        $messageId = 'public-property-test';
        $stamp = new MessageIdStamp($messageId);

        // Should be able to access the property directly
        $this->assertEquals($messageId, $stamp->messageId);
    }


    public function test_message_id_property_is_readonly(): void
    {
        $stamp = new MessageIdStamp('test-id');

        $reflection = new ReflectionClass($stamp);
        $property = $reflection->getProperty('messageId');

        $this->assertTrue($property->isReadOnly());
    }


    public function test_equality_comparison(): void
    {
        $messageId = 'same-message-id';
        $stamp1 = new MessageIdStamp($messageId);
        $stamp2 = new MessageIdStamp($messageId);
        $stamp3 = new MessageIdStamp('different-message-id');

        // Test message ID comparison
        $this->assertEquals($stamp1->messageId, $stamp2->messageId);
        $this->assertNotEquals($stamp1->messageId, $stamp3->messageId);
    }


    public function test_can_be_used_in_arrays(): void
    {
        $stamp1 = new MessageIdStamp('id-1');
        $stamp2 = new MessageIdStamp('id-2');

        $stamps = [$stamp1, $stamp2];

        $this->assertCount(2, $stamps);
        $this->assertEquals('id-1', $stamps[0]->messageId);
        $this->assertEquals('id-2', $stamps[1]->messageId);
    }


    public function test_can_be_used_as_array_values(): void
    {
        $stamp1 = new MessageIdStamp('key1-id');
        $stamp2 = new MessageIdStamp('key2-id');

        $stampMap = [
            'key1' => $stamp1,
            'key2' => $stamp2,
        ];

        $this->assertEquals('key1-id', $stampMap['key1']->messageId);
        $this->assertEquals('key2-id', $stampMap['key2']->messageId);
    }


    public function test_different_instances_with_same_id_are_not_identical(): void
    {
        $messageId = 'same-id';
        $stamp1 = new MessageIdStamp($messageId);
        $stamp2 = new MessageIdStamp($messageId);

        // Same message ID but different object instances
        $this->assertEquals($stamp1->messageId, $stamp2->messageId);
        $this->assertNotSame($stamp1, $stamp2);
    }


    public function test_handles_special_characters_in_message_id(): void
    {
        $specialId = 'msg-with-special_chars.123@domain.com';
        $stamp = new MessageIdStamp($specialId);

        $this->assertEquals($specialId, $stamp->messageId);
    }


    public function test_handles_long_message_id(): void
    {
        $longId = str_repeat('a', 1000);
        $stamp = new MessageIdStamp($longId);

        $this->assertEquals($longId, $stamp->messageId);
        $this->assertEquals(1000, strlen((string)$stamp->messageId));
    }


    public function test_message_id_immutability(): void
    {
        $originalId = 'immutable-test-id';
        $stamp = new MessageIdStamp($originalId);

        $retrievedId = $stamp->messageId;

        // Multiple accesses should return the same value
        $this->assertEquals($originalId, $retrievedId);
        $this->assertEquals($retrievedId, $stamp->messageId);
        $this->assertEquals($originalId, $stamp->messageId);
    }


    public function test_can_be_serialized_and_unserialized(): void
    {
        $messageId = 'serialization-test-id';
        $originalStamp = new MessageIdStamp($messageId);

        $serialized = serialize($originalStamp);
        $unserializedStamp = unserialize($serialized);

        $this->assertInstanceOf(MessageIdStamp::class, $unserializedStamp);
        $this->assertEquals($messageId, $unserializedStamp->messageId);
        $this->assertEquals($originalStamp->messageId, $unserializedStamp->messageId);
    }


    public function test_works_with_json_encoding(): void
    {
        $messageId = 'json-test-id';
        $stamp = new MessageIdStamp($messageId);

        // Create an array that includes the stamp's data
        $data = ['messageId' => $stamp->messageId];
        $json = json_encode($data);
        $decoded = json_decode($json, true);

        $this->assertEquals($messageId, $decoded['messageId']);
    }
}
