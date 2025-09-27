<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Exception;

use LogicException as BaseLogicException;

final class LogicException extends BaseLogicException implements ExceptionInterface
{
}
