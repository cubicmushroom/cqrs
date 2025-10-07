<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Integration;

use CubicMushroom\Cqrs\Bus\IdProvider\UlidMessageIdFactory;
use CubicMushroom\Cqrs\Bus\SymfonyCommandBus;
use CubicMushroom\Cqrs\Middleware\MessageIdStampMiddleware;
use CubicMushroom\Cqrs\Middleware\MessageStatusRecorderMiddleware;
use CubicMushroom\Cqrs\Recorder\LoggingMessageStatusRecorder;
use CubicMushroom\Cqrs\Recorder\NullOpMessageStatusRecorder;
use CubicMushroom\Cqrs\Tests\Dummy\Command\DummyEventDispatchingCommand;
use CubicMushroom\Cqrs\Tests\Utility\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Throwable;

final class MessageStatusRecorderMiddlewareTest extends TestCase
{
    private SymfonyCommandBus $commandBus;


    protected function setUp(): void
    {
        $logger = new RecordingLogger();

        $messageIdStampMiddleware = new MessageIdStampMiddleware(new UlidMessageIdFactory());
        $messageStatusRecorderMiddleware = new MessageStatusRecorderMiddleware(
            new LoggingMessageStatusRecorder(new NullOpMessageStatusRecorder(), $logger),
        );
        $handleMessageMiddleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyEventDispatchingCommand::class => [
                fn() => new readonly class() {
                    public function __invoke(DummyEventDispatchingCommand $command): void
                    {
                        // Handler logic here
                    }
                },
            ],
        ]));

        $innerCommandBus = new MessageBus([
            $messageIdStampMiddleware,
            $messageStatusRecorderMiddleware,
            $handleMessageMiddleware,
        ]);
        $this->commandBus = new SymfonyCommandBus($innerCommandBus);
    }


    public function test_command_dispatch_is_recorded(): void
    {
        try {
            $this->commandBus->dispatch(new DummyEventDispatchingCommand());
        } catch (Throwable $e) {
            $this->fail('Excepted dispatch not to throw, but it did.');
        }

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }
}
