<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Metrics;

use Symfony\Component\Clock\DatePoint;

/**
 * Represents a single metric data point with name, value, and associated tags.
 *
 * This class is used to standardize metric data before it is exported to various
 * monitoring systems like Prometheus or StatsD. It ensures consistency in how
 * metrics are named, tagged, and timestamped across the application.
 *
 * @property string $name The name of the metric (e.g., 'http_requests_total')
 * @property float $value The numeric value of the metric
 * @property array<string, string> $tags Key-value pairs of tags/labels for the metric
 * @property DatePoint $timestamp When the metric was recorded
 */
final readonly class Metric
{
    /**
     * @param string $name The name of the metric (e.g., 'http_requests_total').
     * @param float $value The numeric value of the metric.
     * @param array<string, string> $tags Key-value pairs of tags/labels for the metric.
     * @param DatePoint $timestamp When the metric was recorded.
     */
    public function __construct(
        public string $name,
        public float $value,
        public array $tags,
        public DatePoint $timestamp,
    ) {
    }
}
