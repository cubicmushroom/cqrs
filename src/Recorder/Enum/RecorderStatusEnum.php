<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Recorder\Enum;

enum RecorderStatusEnum: string
{
    case DISPATCHED = 'dispatched';
}
