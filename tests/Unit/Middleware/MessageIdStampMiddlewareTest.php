<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Middleware;

use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\StampFactory\Exception\MessageIdStampAlreadyExistsException;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactoryInterface;
use CubicMushroom\Cqrs\Middleware\MessageIdStampMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class MessageIdStampMiddlewareTest extends TestCase
{
    private MessageIdStampFactoryInterface|MockObject $messageIdStampFactory;

    private StackInterface|MockObject $stack;

    private MiddlewareInterface|MockObject $nextMiddleware;

    private MessageIdStampMiddleware $middleware;


    protected function setUp(): void
    {
        parent::setUp();

        $this->messageIdStampFactory = $this->createMock(MessageIdStampFactoryInterface::class);
        $this->stack = $this->createMock(StackInterface::class);
        $this->nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $this->middleware = new MessageIdStampMiddleware($this->messageIdStampFactory);
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


    public function test_handle_adds_message_id_stamp_and_calls_next_middleware(): void
    {
        $message = new stdClass();
        $envelope = new Envelope($message);
        $expectedStamps = [new MessageIdStamp('test-id-123')];

        // Expect the factory to be called with the current stamps
        $this->messageIdStampFactory->expects($this->once())
            ->method('attachStamp')
            ->with($envelope->all())
            ->willReturn($expectedStamps);

        // Set up the stack to return the next middleware
        $this->stack->expects($this->once())
            ->method('next')
            ->willReturn($this->nextMiddleware);

        // The next middleware should be called with an envelope containing the new stamp
        $this->nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->callback(function (Envelope $envelope) use ($message, $expectedStamps) {
                    $this->assertSame($message, $envelope->getMessage());
                    $this->assertCount(1, $envelope->all(MessageIdStamp::class));
                    $this->assertSame(
                        $expectedStamps[0]->messageId,
                        $envelope->last(MessageIdStamp::class)->messageId,
                    );

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
        $expectedStamps = [$existingStamp, new MessageIdStamp('test-id-456')];

        $this->messageIdStampFactory->expects($this->once())
            ->method('attachStamp')
            ->with($envelope->all())
            ->willReturn($expectedStamps);

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


    public function test_handle_propagates_message_id_stamp_already_exists_exception(): void
    {
        $envelope = new Envelope(new stdClass());
        $exception = new MessageIdStampAlreadyExistsException(new MessageIdStamp('existing-id'));

        $this->messageIdStampFactory->expects($this->once())
            ->method('attachStamp')
            ->willThrowException($exception);

        $this->stack->expects($this->never())
            ->method('next');

        $this->expectExceptionObject(
            new UnrecoverableMessageHandlingException('Unable to attach MessageIdStamp to envelope.', 0, $exception),
        );

        $this->middleware->handle($envelope, $this->stack);
    }


    public function test_handle_propagates_other_exceptions(): void
    {
        $envelope = new Envelope(new stdClass());
        $exception = new RuntimeException('Something went wrong');

        $this->messageIdStampFactory->expects($this->once())
            ->method('attachStamp')
            ->willThrowException($exception);

        $this->stack->expects($this->never())
            ->method('next');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Something went wrong');

        $this->middleware->handle($envelope, $this->stack);
    }
}
