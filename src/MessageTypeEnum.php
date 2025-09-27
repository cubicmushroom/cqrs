<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs;

use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use CubicMushroom\Cqrs\Query\QueryInterface;

/**
 * Enum for message types.
 */
enum MessageTypeEnum: string
{
    case COMMAND = 'command';
    case QUERY = 'query';
    case DOMAIN_EVENT = 'domain event';


    public static function getMessageType(object $message): self
    {
        return match (true) {
            $message instanceof CommandInterface => self::COMMAND,
            $message instanceof QueryInterface => self::QUERY,
            $message instanceof DomainEventInterface => self::DOMAIN_EVENT,
        };
    }
}
