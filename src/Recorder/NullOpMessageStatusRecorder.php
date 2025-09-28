<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Recorder;

use CubicMushroom\Cqrs\MessageStatusEnum;
use CubicMushroom\Cqrs\MessageTypeEnum;
use DateTimeImmutable;

/**
 * Implementation of MessageStatusRecorderInterface that does nothing.
 */
final readonly class NullOpMessageStatusRecorder implements MessageStatusRecorderInterface
{
    public function recordStatus(
        MessageTypeEnum $messageType,
        string $messageId,
        MessageStatusEnum $status,
        array $causedByMessageIds = [],
        mixed $data = null,
        ?DateTimeImmutable $occurredAt = null
    ): void {
        // No-op implementation
    }

    public function recordDependency(
        string $dependentMessageId,
        string $parentMessageId,
        ?DateTimeImmutable $recordedAt = null
    ): void {
        // No-op implementation
    }

    public function updateDependentMessageStatuses(
        string $parentMessageId,
        MessageStatusEnum $newStatus,
        ?string $reason = null
    ): void {
        // No-op implementation
    }
}
