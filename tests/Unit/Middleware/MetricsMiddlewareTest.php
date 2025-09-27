<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Middleware;

use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use CubicMushroom\Cqrs\Metrics\Exporter\MetricsExporterInterface;
use CubicMushroom\Cqrs\Metrics\Metric;
use CubicMushroom\Cqrs\Middleware\MetricsMiddleware;
use CubicMushroom\Cqrs\Query\QueryInterface;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class MetricsMiddlewareTest extends TestCase
{
    private MetricsExporterInterface|MockObject $exporter1;

    private MetricsExporterInterface|MockObject $exporter2;

    private MetricsMiddleware $middleware;

    private StackInterface|MockObject $stack;

    private MiddlewareInterface|MockObject $nextMiddleware;

    private LoggerInterface|MockObject $logger;


    protected function setUp(): void
    {
        $this->exporter1 = $this->createMock(MetricsExporterInterface::class);
        $this->exporter2 = $this->createMock(MetricsExporterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->middleware = new MetricsMiddleware($this->exporter1, $this->exporter2);
        $this->middleware->setLogger($this->logger);
        $this->stack = $this->createMock(StackInterface::class);
        $this->nextMiddleware = $this->createMock(MiddlewareInterface::class);

        $this->stack->method('next')->willReturn($this->nextMiddleware);
    }


    public function test_handle_with_command(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $envelope = new Envelope($command);

        $this->nextMiddleware->expects($this->once())->method('handle')->with($envelope, $this->stack)->willReturn(
            $envelope,
        );

        // Expect debug logs for each exporter
        $this->logger->expects($this->exactly(8)) // 4 metrics * 2 exporters
        ->method('debug')->with(
            'Exporting metric',
            $this->callback(function (array $context) {
                $this->assertArrayHasKey('metric', $context);
                $this->assertArrayHasKey('exporter', $context);

                return true;
            }),
        );

        $expectedMetrics = [
            'cqrs_messages_total',
            'cqrs_processing_seconds',
            'cqrs_memory_usage_bytes',
            'cqrs_memory_delta_bytes',
        ];

        $this->exporter1->expects($this->exactly(4))->method('export')->willReturnCallback(
            function (Metric $metric) use (&$expectedMetrics): null {
                $metricName = $metric->name;
                $this->assertContains($metricName, $expectedMetrics);
                $expectedMetrics = array_diff($expectedMetrics, [$metricName]);

                return null;
            },
        );

        $this->exporter2->expects($this->exactly(4))->method('export');

        $result = $this->middleware->handle($envelope, $this->stack);

        $this->assertSame($envelope, $result);
        $this->assertEmpty($expectedMetrics);
    }


    public function test_handle_with_query(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $envelope = new Envelope($query);

        $this->nextMiddleware->method('handle')->willReturn($envelope);

        $this->exporter1->expects($this->exactly(4))->method('export');

        $result = $this->middleware->handle($envelope, $this->stack);

        $this->assertSame($envelope, $result);
    }


    public function test_handle_with_domain_event(): void
    {
        $event = $this->createMock(DomainEventInterface::class);
        $envelope = new Envelope($event);

        $this->nextMiddleware->method('handle')->willReturn($envelope);

        $this->exporter1->expects($this->exactly(4))->method('export');

        $result = $this->middleware->handle($envelope, $this->stack);

        $this->assertSame($envelope, $result);
    }


    public function test_handle_with_export_failure(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $envelope = new Envelope($command);
        $exportException = new RuntimeException('Export failed');

        $this->nextMiddleware->expects($this->once())->method('handle')->with($envelope, $this->stack)->willReturn(
            $envelope,
        );

        // Make exporter1 fail
        $this->exporter1->expects($this->exactly(4))->method('export')->willReturnOnConsecutiveCalls(
            null,
            null,
            null,
            new Exception($exportException),
        );

        // Verify error is logged
        $this->logger->expects($this->once())->method('error')->with(
            'Failed to export metric',
            $this->callback(function (array $context) use ($exportException) {
                $this->assertArrayHasKey('error', $context);
                $this->assertArrayHasKey('metric', $context);
                $this->assertArrayHasKey('exporter', $context);
                // Double check this isn't a PHPUnit exception…
                $this->assertNotInstanceOf(ExpectationFailedException::class, $context['exception']);
                $this->assertSame($exportException, $context['exception']);

                return true;
            }),
        );

        // exporter2 should still be called
        $this->exporter2->expects($this->exactly(4))->method('export');

        $result = $this->middleware->handle($envelope, $this->stack);
        $this->assertSame($envelope, $result);
    }


    public function test_handle_with_exception(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $envelope = new Envelope($command);
        $exception = new RuntimeException('Test exception');

        $this->nextMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->stack)
            ->willThrowException($exception);

        // Verify metrics are still recorded even when an exception occurs
        $this->logger->expects($this->exactly(8))->method('debug');
        $this->exporter1->expects($this->exactly(4))->method('export');
        $this->exporter2->expects($this->exactly(4))->method('export');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->middleware->handle($envelope, $this->stack);
    }
}
