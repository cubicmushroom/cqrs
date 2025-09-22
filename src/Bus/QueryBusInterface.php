<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus;

use CubicMushroom\Cqrs\Query\QueryInterface;
use Exception;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Interface for the query bus in the CQRS system.
 *
 * The query bus is responsible for dispatching queries to their appropriate handlers
 * and returning the results. Queries are typically processed synchronously as they
 * need to return data immediately.
 */
interface QueryBusInterface
{
    /**
     * Dispatch a query for processing and return the result.
     *
     * Queries are processed synchronously as they need to return data.
     * The query will be routed to the appropriate handler and the result returned.
     *
     * @template TResult
     * @param QueryInterface<TResult> $query The query to dispatch
     * @param StampInterface[] $stamps Optional stamps to attach to the query
     *
     * @return TResult The result of the query processing
     *
     * @throws Exception When the query cannot be processed
     */
    public function dispatch(QueryInterface $query, array $stamps = []): mixed;
}
