<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Query;

/**
 * Base interface for all queries in the CQRS system.
 *
 * Queries represent the intention to retrieve data from the system.
 * They should be immutable and contain all the parameters needed to fetch the data.
 *
 * @template TResult The type of result this query will return
 */
interface QueryInterface
{
}
