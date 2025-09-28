<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Utility;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

final class RecordingLogger implements LoggerInterface
{
    private array $logs = [];


    /**
     * @inheritDoc
     */
    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->logs[] = new LogMessage(LogLevel::EMERGENCY, (string)$message, $context);
    }


    /**
     * @inheritDoc
     */
    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->logs[] = new LogMessage(LogLevel::ALERT, (string)$message, $context);
    }


    /**
     * @inheritDoc
     */
    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->logs[] = new LogMessage(LogLevel::CRITICAL, (string)$message, $context);
    }


    /**
     * @inheritDoc
     */
    public function error(Stringable|string $message, array $context = []): void
    {
        $this->logs[] = new LogMessage(LogLevel::ERROR, (string)$message, $context);
    }


    /**
     * @inheritDoc
     */
    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->logs[] = new LogMessage(LogLevel::WARNING, (string)$message, $context);
    }


    /**
     * @inheritDoc
     */
    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->logs[] = new LogMessage(LogLevel::NOTICE, (string)$message, $context);
    }


    /**
     * @inheritDoc
     */
    public function info(Stringable|string $message, array $context = []): void
    {
        $this->logs[] = new LogMessage(LogLevel::INFO, (string)$message, $context);
    }


    /**
     * @inheritDoc
     */
    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->logs[] = new LogMessage(LogLevel::DEBUG, (string)$message, $context);
    }


    /**
     * @inheritDoc
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->logs[] = new LogMessage($level, (string)$message, $context);
    }
}


final readonly class LogMessage
{
    public function __construct(
        public string $level,
        public string $message,
        public array $context,
    ) {
    }
}
