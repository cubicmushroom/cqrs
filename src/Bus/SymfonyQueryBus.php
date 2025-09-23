<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus;

use CubicMushroom\Cqrs\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
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
        MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->messageBus = $messageBus;
    }


    /**
     * @inheritDoc
     *
     * @template TResult of mixed
     *
     * @param QueryInterface<TResult> $query The query to dispatch.
     * @param StampInterface[] $stamps An optional array of stamps to attach to the query.
     *
     * @return TResult
     *
     * @throws ExceptionInterface if something goes wrong with the message dispatching.
     * @throws Throwable if anything else goes wrong.
     */
    public function dispatch(QueryInterface $query, array $stamps = []): mixed
    {
        // Dispatch the query synchronously and get the result
        return $this->handle($query, $stamps);
    }
}
