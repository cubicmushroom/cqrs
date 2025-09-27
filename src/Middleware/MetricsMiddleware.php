<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Middleware;

use CubicMushroom\Cqrs\MessageTypeEnum;
use CubicMushroom\Cqrs\Metrics\Exporter\MetricsExporterInterface;
use CubicMushroom\Cqrs\Metrics\Metric;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Throwable;

/**
 * Middleware for collecting comprehensive metrics on CQRS message processing.
 *
 * This middleware automatically records the following metrics for all CQRS operations:
 * - Message processing time (in seconds)
 * - Memory usage (in bytes)
 * - Memory delta (difference in memory usage)
 * - Message counters
 *
 * Metrics are tagged with:
 * - message_type: 'command', 'query', 'event', or 'message'
 * - message_class: The short class name of the message
 * - success: 'true' or 'false' indicating if processing succeeded
 *
 * The middleware uses the configured exporters (e.g., Prometheus, StatsD) to send
 * metrics to monitoring systems. It implements LoggerAwareInterface to log any
 * issues that occur during metric export without interrupting message processing.
 */
final class MetricsMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    /** @var MetricsExporterInterface[] */
    private array $exporters;

    private LoggerInterface $logger;


    public function __construct(MetricsExporterInterface ...$exporters)
    {
        $this->exporters = $exporters;
        $this->logger = new NullLogger();
    }


    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }


    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $messageType = MessageTypeEnum::getMessageType($message);
        $messageClass = $message::class;

        $tags = [
            'type' => $messageType->value,
            'class' => $this->getShortClassName($messageClass),
            'success' => 'true',
        ];

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (Throwable $exception) {
            $tags['success'] = 'false';
            throw $exception;
        } finally {
            $this->recordMetrics($startTime, $startMemory, $tags);
        }
    }


    private function recordMetrics(
        float $startTime,
        int $startMemory,
        array $baseTags,
    ): void {
        $processingTime = microtime(true) - $startTime;
        $memoryUsage = memory_get_peak_usage(true);
        $memoryDelta = memory_get_usage(true) - $startMemory;

        // Record processing time in seconds
        $this->exportMetric('cqrs_processing_seconds', $processingTime, $baseTags);

        // Record memory usage in bytes
        $this->exportMetric('cqrs_memory_usage_bytes', $memoryUsage, $baseTags);
        $this->exportMetric('cqrs_memory_delta_bytes', $memoryDelta, $baseTags);

        // Record counter for total messages
        $this->exportMetric('cqrs_messages_total', 1, $baseTags);
    }


    private function exportMetric(string $name, float $value, array $tags): void
    {
        $metric = new Metric($name, $value, $tags, new DatePoint());

        foreach ($this->exporters as $exporter) {
            $this->logger->debug('Exporting metric', [
                'metric' => $name,
                'exporter' => get_class($exporter),
            ]);
            try {
                $exporter->export($metric);
            } catch (Throwable $e) {
                $this->logger->error('Failed to export metric', [
                    'error' => $e->getMessage(),
                    'metric' => $name,
                    'exporter' => get_class($exporter),
                    'exception' => $e,
                ]);
            }
        }
    }


    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
