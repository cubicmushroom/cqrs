<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus;

use CubicMushroom\Cqrs\Bus\Id\QueryId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactoryInterface;
use CubicMushroom\Cqrs\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Throwable;

/**
 * Symfony Messenger implementation of the query bus.
 *
 * This implementation uses Symfony Messenger to dispatch queries synchronously
 * and provides logging and metrics collection as per global defaults.
 */
final class SymfonyQueryBus implements QueryBusInterface
{
    use HandleTrait;

    public function __construct(
        private readonly MessageIdStampFactoryInterface $idStampFactory,
        MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->messageBus = $messageBus;
    }


    /**
     * @template TResult of mixed
     * @param QueryInterface<TResult> $query
     * @param StampInterface[] $stamps
     *
     * @return TResult
     * @throws Throwable
     */
    public function dispatch(QueryInterface $query, array $stamps = []): mixed
    {
        $stamps = $this->idStampFactory->attachStamp(
            $stamps,
            fn(string $messageId) => new QueryId($messageId),
        );

        $envelope = new Envelope($query, $stamps);
        $messageIdStamp = $envelope->last(MessageIdStamp::class);
        assert($messageIdStamp instanceof MessageIdStamp);

        try {
            // Log the query dispatch for audit purposes
            $this->logger->info('Dispatching query', [
                'message_id' => $messageIdStamp->messageId,
                'query_type' => $query::class,
            ]);

            // Dispatch the query synchronously and get the result
            $result = $this->handle($envelope);

            // Log successful dispatch
            $this->logger->info('Query processed successfully', [
                'message_id' => $messageIdStamp->messageId,
                'result_type' => is_object($result) ? $result::class : gettype($result),
            ]);

            return $result;
        } catch (Throwable $exception) {
            // Log the error for debugging and monitoring
            $this->logger->error('Failed to process query', [
                'message_id' => $messageIdStamp->messageId,
                'query_type' => $query::class,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
