<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Metrics\Exporter;

use CubicMushroom\Cqrs\Metrics\Exception\MetricExportException;
use CubicMushroom\Cqrs\Metrics\Metric;

/**
 * Defines the contract for exporting metrics to various monitoring systems.
 *
 * Implementations of this interface are responsible for:
 * - Translating generic Metric objects into the specific format required by the monitoring system
 * - Handling any transport-level concerns (e.g., network connections, batching)
 * - Managing errors and reporting them via MetricExportException
 *
 * The middleware will call export() for each metric that needs to be recorded.
 * Implementations should be thread-safe and handle errors gracefully, as they
 * should not disrupt the normal flow of the application.
 *
 * @see PrometheusExporter For a Prometheus-specific implementation
 * @see StatsDExporter For a StatsD-specific implementation
 */
interface MetricsExporterInterface
{
    /**
     * Exports a single metric to the monitoring system.
     *
     * This method is responsible for:
     * 1. Validating the metric (if needed)
     * 2. Converting the metric to the target system's format
     * 3. Transmitting the metric to the monitoring backend
     * 4. Handling any errors that occur during export
     *
     * Implementations should be idempotent when possible and should not throw
     * exceptions for non-critical issues (e.g., network timeouts) unless explicitly
     * configured to do so.
     *
     * @param Metric $metric The metric to export, containing name, value, tags, and timestamp
     *
     * @throws MetricExportException If the metric cannot be exported and the implementation
     *                              determines this is a critical failure that should be logged
     *                              and potentially monitored.
     *                              The exception should include the exporter class name and
     *                              metric name for better error tracking.
     *
     * @see Metric For details on the structure of the metric object
     */
    public function export(Metric $metric): void;
}
