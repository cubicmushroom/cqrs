<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Middleware;

use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use CubicMushroom\Cqrs\Middleware\LoggingMiddleware;
use CubicMushroom\Cqrs\Query\QueryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionObject;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Unit tests for LoggingMiddleware.
 */
final class LoggingMiddlewareTest extends TestCase
{
    private LoggerInterface $logger;

    private LoggingMiddleware $loggingMiddleware;

    private StackInterface $stack;

    private MiddlewareInterface $middleware;


    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->loggingMiddleware = new LoggingMiddleware($this->logger);
        $this->stack = $this->createMock(StackInterface::class);
        $this->middleware = $this->createMock(MiddlewareInterface::class);

        $this->stack
            ->method('next')
            ->willReturn($this->middleware);
    }


    public function test_logs_command_processing(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $envelope = new Envelope($command, [new MessageIdStamp('test-command-id')]);

        $this->middleware
            ->expects($this->once())
            ->method('handle')
            ->with($envelope)
            ->willReturn($handledEnvelope = $envelope->with(new HandledStamp(null, 'DummyHandler')));

        $logCalls = [];
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$logCalls) {
                $logCalls[] = [$message, $context];
            });

        $result = $this->loggingMiddleware->handle($envelope, $this->stack);

        $this->assertSame($handledEnvelope, $result);

        // Verify the log calls were made in the correct order
        $this->assertCount(2, $logCalls);
        $this->assertEquals('Processing command', $logCalls[0][0]);
        $this->assertIsArray($logCalls[0][1]);
        $this->assertArrayHasKey('message_id', $logCalls[0][1]);
        $this->assertEquals('test-command-id', $logCalls[0][1]['message_id']);
        $this->assertEquals('Command processed successfully', $logCalls[1][0]);
        $this->assertIsArray($logCalls[1][1]);
        $this->assertArrayHasKey('processing_time_ms', $logCalls[1][1]);
        $this->assertIsNumeric($logCalls[1][1]['processing_time_ms']);
    }


    public function test_logs_query_processing(): void
    {
        $query = $this->createMock(QueryInterface::class);

        $envelope = new Envelope($query, [new MessageIdStamp('test-query-id')]);

        $this->middleware
            ->expects($this->once())
            ->method('handle')
            ->with($envelope)
            ->willReturn($envelope->with(new HandledStamp(null, 'DummyHandler')));

        $logCalls = [];
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$logCalls) {
                $logCalls[] = [$message, $context];
            });

        $this->loggingMiddleware->handle($envelope, $this->stack);

        // Verify the log calls were made in the correct order
        $this->assertCount(2, $logCalls);
        $this->assertEquals('Processing query', $logCalls[0][0]);
        // $this->assertEquals([
        //         MessageIdStamp::class => [
        //             ['message_id' => 'test-query-id']
        //         ],
        // ], $logCalls[0][1]['envelope_stamps']);
        $this->assertEquals([
            'message_type' => new ReflectionObject($query)->getShortName(),
            'message_id' => 'test-query-id',
            'envelope_stamps' => [
                MessageIdStamp::class => [
                    new MessageIdStamp('test-query-id'),
                ],
            ],
        ], $logCalls[0][1]);
        $this->assertEquals('Query processed successfully', $logCalls[1][0]);
        $this->assertEquals([
            'message_type',
            'message_id',
            'processing_time_ms',
        ], array_keys($logCalls[1][1]));

        $this->assertEquals(new ReflectionObject($query)->getShortName(), $logCalls[1][1]['message_type']);
        $this->assertEquals('test-query-id', $logCalls[1][1]['message_id']);
        $this->assertIsNumeric($logCalls[1][1]['processing_time_ms']);
    }


    public function test_logs_domain_event_processing(): void
    {
        $event = $this->createMock(DomainEventInterface::class);

        $envelope = new Envelope($event, [new MessageIdStamp('test-domain-event-id')]);

        $this->middleware
            ->expects($this->once())
            ->method('handle')
            ->with($envelope)
            ->willReturn($envelope->with(new HandledStamp(null, 'DummyHandler')));

        $logCalls = [];
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$logCalls) {
                $logCalls[] = [$message, $context];
            });

        $this->loggingMiddleware->handle($envelope, $this->stack);

        // Verify the log calls were made in the correct order
        $this->assertCount(2, $logCalls);
        $this->assertEquals('Processing domain event', $logCalls[0][0]);

        $this->assertArrayHasKey('message_type', $logCalls[0][1]);
        $this->assertEquals(new ReflectionObject($event)->getShortName(), $logCalls[0][1]['message_type']);
        $this->assertArrayHasKey('message_id', $logCalls[0][1]);
        $this->assertEquals('test-domain-event-id', $logCalls[0][1]['message_id']);
        $this->assertArrayHasKey('envelope_stamps', $logCalls[0][1]);
        $this->assertEquals([
            MessageIdStamp::class => [new MessageIdStamp(('test-domain-event-id'))],
        ], $logCalls[0][1]['envelope_stamps']);
        $this->assertEquals('Domain event processed successfully', $logCalls[1][0]);
        $this->assertIsArray($logCalls[1][1]);
    }


    public function test_logs_processing_failure(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $envelope = new Envelope($command, [new MessageIdStamp('test-command-id')]);
        $exception = new RuntimeException('Test exception');

        $this->stack
            ->expects($this->once())
            ->method('next')
            ->willThrowException($exception);

        $logCalls = [];
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$logCalls) {
                $logCalls[] = [$message, $context];
            });

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Command processing failed',
                $this->callback(function (array $context) {
                    return isset($context['error']) && $context['error'] === 'Test exception';
                }),
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->loggingMiddleware->handle($envelope, $this->stack);

        // Verify the log call was made
        $this->assertCount(1, $logCalls);
        $this->assertEquals('Processing Command', $logCalls[0][0]);
        $this->assertIsArray($logCalls[0][1]);
        $this->assertArrayHasKey('message_id', $logCalls[0][1]);
        $this->assertEquals('test-command-id', $logCalls[0][1]['message_id']);
    }
}
