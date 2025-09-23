<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Metrics\Exception;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a metric cannot be exported to a monitoring system.
 *
 * This exception is the base exception for all metric export related errors.
 * More specific exceptions may extend this class to provide more context.
 */
final class MetricExportException extends RuntimeException
{
    /**
     * @param string $exporter The class name of the exporter that failed
     * @param string $metricName The name of the metric that failed to export
     * @param string $message The error message
     * @param int $code The error code
     * @param Throwable|null $previous The previous exception used for chaining
     */
    public function __construct(
        public readonly string $exporter,
        public readonly string $metricName,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
