<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Exception;

final class MessageIdNotFoundException extends \LogicException implements ExceptionInterface
{
}
