<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Bus\StampFactory\Exception;

use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use Exception;

final class MessageIdStampAlreadyExistsException extends Exception
{
    public function __construct(MessageIdStamp $existingStamp)
    {
        parent::__construct(
            sprintf(
                'MessageIdStamp with ID %s already exists',
                $existingStamp->messageId,
            ),
        );
    }
}
