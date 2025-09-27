<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Middleware;

use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use CubicMushroom\Cqrs\MessageTypeEnum;
use CubicMushroom\Cqrs\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Throwable;

use function ucfirst;

/**
 * Middleware for logging all commands, queries, and events as they pass through the message bus.
 *
 * This middleware provides comprehensive audit logging for all CQRS operations
 * as specified in the global project defaults.
 */
final readonly class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }


    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $messageType = MessageTypeEnum::getMessageType($message);;
        $messageId = MessageIdStamp::getMessageId($envelope);

        // Log the start of message processing
        $this->logger->info("Processing $messageType->value", [
            'message_type' => $message::class,
            'message_id' => $messageId,
            'envelope_stamps' => array_keys($envelope->all()),
        ]);

        $startTime = microtime(true);

        try {
            // Continue processing the message
            $envelope = $stack->next()->handle($envelope, $stack);

            $processingTime = microtime(true) - $startTime;

            // Log successful processing
            $this->logger->info(ucfirst($messageType->value) . ' processed successfully', [
                'message_type' => $message::class,
                'message_id' => $messageId,
                'processing_time_ms' => round($processingTime * 1000, 2),
            ]);

            return $envelope;
        } catch (Throwable $exception) {
            $processingTime = microtime(true) - $startTime;

            // Log processing failure
            $this->logger->error(ucfirst($messageType->value) . ' processing failed', [
                'message_type' => $message::class,
                'message_id' => $messageId,
                'processing_time_ms' => round($processingTime * 1000, 2),
                'error' => $exception->getMessage(),
                'exception_type' => $exception::class,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
