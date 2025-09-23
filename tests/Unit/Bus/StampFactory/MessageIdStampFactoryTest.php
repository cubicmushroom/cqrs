<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus\StampFactory;

use CubicMushroom\Cqrs\Bus\IdProvider\MessageIdFactoryInterface;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\StampFactory\Exception\MessageIdStampAlreadyExistsException;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactory;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactoryInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MessageIdStampFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        $this->messageIdFactory = $this->createMock(MessageIdFactoryInterface::class);
        $this->factory = new MessageIdStampFactory($this->messageIdFactory);
    }


    public function test_implements_message_id_stamp_factory_interface(): void
    {
        $this->assertInstanceOf(MessageIdStampFactoryInterface::class, $this->factory);
    }


    public function test_is_readonly(): void
    {
        $reflection = new ReflectionClass($this->factory);
        $this->assertTrue($reflection->isReadOnly());
    }


    public function test_attach_stamp_attaches_a_message_id_stamp(): void
    {
        $this->messageIdFactory
            ->expects($this->once())
            ->method('nextId')
            ->willReturn('12345');

        $result = $this->factory->attachStamp([]);

        $this->assertCount(1, $result);
        $stamp = array_values($result)[0];
        $this->assertInstanceOf(MessageIdStamp::class, $stamp);
        $this->assertEquals('12345', $stamp->messageId);
    }


    public function test_attach_stamp_throws_exception_if_stamp_already_exists(): void
    {
        $this->expectException(MessageIdStampAlreadyExistsException::class);
        $this->expectExceptionMessage('MessageIdStamp with ID 12345 already exists.');

        $this->factory->attachStamp([new MessageIdStamp('12345')]);
    }


    public function test_attach_stamp_throws_exception_if_message_id_stamp_already_provided_multiple_times(): void
    {
        $this->expectException(MessageIdStampAlreadyExistsException::class);
        $this->expectExceptionMessage('MessageIdStamp with ID 67890 already exists.');

        $this->factory->attachStamp([new MessageIdStamp('12345'), new MessageIdStamp('67890')]);
    }
}
