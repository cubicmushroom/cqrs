<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Middleware;

use CubicMushroom\Cqrs\Bus\Stamp\CausedByMessageStamp;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\MessageStatusEnum;
use CubicMushroom\Cqrs\MessageTypeEnum;
use CubicMushroom\Cqrs\Middleware\MessageStatusRecorderMiddleware;
use CubicMushroom\Cqrs\Recorder\MessageStatusRecorderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class MessageStatusRecorderMiddlewareTest extends TestCase
{
    private MessageStatusRecorderInterface&MockObject $recorder;

    private StackInterface&MockObject $stack;

    private MessageStatusRecorderMiddleware $middleware;


    protected function setUp(): void
    {
        $this->recorder = $this->createMock(MessageStatusRecorderInterface::class);
        $this->stack = $this->createMock(StackInterface::class);
        $this->nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $this->middleware = new MessageStatusRecorderMiddleware($this->recorder);

        $this->stack
            ->method('next')
            ->with()
            ->willReturn($this->nextMiddleware);

        $this->nextMiddleware
            ->method('handle')
            ->with($this->isInstanceOf(Envelope::class))
            ->willReturnArgument(0);
    }


    public function test_command_dispatch_is_recorded(): void
    {
        $this->recorder
            ->expects($this->once())
            ->method('recordStatus')
            ->with(
                MessageTypeEnum::COMMAND,
                'test-id',
                MessageStatusEnum::DISPATCHED,
                [],
                null,
                null,
            );

        $this->recorder
            ->expects($this->never())
            ->method('recordDependency');

        $envelope = new Envelope($this->createMock(CommandInterface::class), [new MessageIdStamp('test-id')]);

        $this->middleware->handle($envelope, $this->stack);
    }


    public function test_command_dispatch_with_dependencies_records_both_status_and_dependencies(): void
    {
        $parentMessageId = 'parent-message-id';
        $childMessageId = 'child-message-id';

        $this->recorder
            ->expects($this->once())
            ->method('recordStatus')
            ->with(
                MessageTypeEnum::COMMAND,
                $childMessageId,
                MessageStatusEnum::DISPATCHED,
                [$parentMessageId],
                null,
                null,
            );

        $this->recorder
            ->expects($this->once())
            ->method('recordDependency')
            ->with($childMessageId, $parentMessageId, null);

        $envelope = new Envelope(
            $this->createMock(CommandInterface::class),
            [
                new MessageIdStamp($childMessageId),
                new CausedByMessageStamp(MessageTypeEnum::COMMAND, $parentMessageId),
            ],
        );

        $this->middleware->handle($envelope, $this->stack);
    }
}
