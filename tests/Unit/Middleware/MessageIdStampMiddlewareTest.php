<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Middleware;

use CubicMushroom\Cqrs\Bus\IdProvider\MessageIdFactoryInterface;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Middleware\MessageIdStampMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class MessageIdStampMiddlewareTest extends TestCase
{
    private StackInterface|MockObject $stack;

    private MiddlewareInterface|MockObject $nextMiddleware;

    private MessageIdStampMiddleware $middleware;


    protected function setUp(): void
    {
        parent::setUp();

        $messageIdFactory = $this->createMock(MessageIdFactoryInterface::class);
        $this->stack = $this->createMock(StackInterface::class);
        $this->nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $this->middleware = new MessageIdStampMiddleware($messageIdFactory);

        $messageIdFactory->method('nextId')->with()->willReturn('test-id-123');

        $this->stack->method('next')->with()->willReturn($this->nextMiddleware);
    }


    public function test_class_implements_middleware_interface(): void
    {
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(MiddlewareInterface::class, $this->middleware);
    }


    public function test_class_is_readonly(): void
    {
        $this->assertTrue(new ReflectionClass(MessageIdStampMiddleware::class)->isReadOnly());
    }


    public function test_handle_adds_message_id_stamp_if_not_present(): void
    {
        $this->nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->callback(function (Envelope $envelope) {
                    $this->assertCount(1, $envelope->all(MessageIdStamp::class));
                    $this->assertEquals('test-id-123', $envelope->last(MessageIdStamp::class)->messageId);

                    return true;
                }),
                $this->identicalTo($this->stack),
            )
            ->willReturnCallback(fn(Envelope $envelope) => $envelope);

        $envelope = new Envelope(new stdClass());

        $this->middleware->handle($envelope, $this->stack);
    }


    public function test_handle_does_not_adds_message_id_stamp_if_present(): void
    {
        $this->nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->callback(function (Envelope $envelope) {
                    $this->assertCount(1, $envelope->all(MessageIdStamp::class));
                    $this->assertEquals('test-id-456', $envelope->last(MessageIdStamp::class)->messageId);

                    return true;
                }),
                $this->identicalTo($this->stack),
            )
            ->willReturnCallback(fn(Envelope $envelope) => $envelope);

        $envelope = new Envelope(new stdClass(), [new MessageIdStamp('test-id-456')]);

        $this->middleware->handle($envelope, $this->stack);
    }


    public function test_handle_adds_message_id_stamp_and_calls_next_middleware(): void
    {
        $message = new stdClass();
        $envelope = new Envelope($message);

        // Set up the stack to return the next middleware
        $this->stack->expects($this->once())
            ->method('next')
            ->willReturn($this->nextMiddleware);

        // The next middleware should be called with an envelope containing the new stamp
        $this->nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->callback(function (Envelope $envelope) use ($message) {
                    $this->assertSame($message, $envelope->getMessage());
                    $this->assertCount(1, $envelope->all(MessageIdStamp::class));
                    $this->assertSame('test-id-123', $envelope->last(MessageIdStamp::class)->messageId);

                    return true;
                }),
                $this->identicalTo($this->stack),
            )
            ->willReturn($envelope);

        $result = $this->middleware->handle($envelope, $this->stack);
        $this->assertSame($envelope, $result);
    }


    public function test_handle_preserves_existing_stamps(): void
    {
        $message = new stdClass();
        $existingStamp = $this->createMock(StampInterface::class);
        $envelope = new Envelope($message, [$existingStamp]);

        $this->stack->expects($this->once())
            ->method('next')
            ->willReturn($this->nextMiddleware);

        $this->nextMiddleware->expects($this->once())
            ->method('handle')
            ->with(
                $this->callback(function (Envelope $envelope) use ($existingStamp) {
                    $this->assertCount(1, $envelope->all(MessageIdStamp::class));
                    $this->assertCount(1, $envelope->all(get_class($existingStamp)));

                    return true;
                }),
                $this->identicalTo($this->stack),
            )
            ->willReturn($envelope);

        $this->middleware->handle($envelope, $this->stack);
    }
}
