<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Recorder;

use CubicMushroom\Cqrs\MessageStatusEnum;
use CubicMushroom\Cqrs\MessageTypeEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;

final readonly class LoggingMessageStatusRecorder implements MessageStatusRecorderInterface
{
    public function __construct(
        private MessageStatusRecorderInterface $decorated,
        private LoggerInterface $logger,

    ) {
    }


    public function recordStatus(
        MessageTypeEnum $messageType,
        string $messageId,
        MessageStatusEnum $status,
        array $causedByMessageIds = [],
        mixed $data = null,
        ?DateTimeImmutable $occurredAt = null,
    ): void {
        $context = [
            'message_type' => $messageType->value,
            'message_id' => $messageId,
            'status' => $status->value,
            'caused_by' => $causedByMessageIds,
            'occurred_at' => ($occurredAt ?? new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ];

        if ($data !== null) {
            $context['data'] = $data;
        }

        $this->logger->info("About to record message $messageId as $status->value", $context);

        $this->decorated->recordStatus($messageType, $messageId, $status, $causedByMessageIds, $data, $occurredAt);

        $this->logger->info("Recorded message $messageId as $status->value");
    }

    public function recordDependency(
        string $dependentMessageId,
        string $parentMessageId,
        ?DateTimeImmutable $recordedAt = null
    ): void {
        $context = [
            'dependent_message_id' => $dependentMessageId,
            'parent_message_id' => $parentMessageId,
            'recorded_at' => ($recordedAt ?? new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ];

        $this->logger->info("About to record dependency: $dependentMessageId depends on $parentMessageId", $context);

        $this->decorated->recordDependency($dependentMessageId, $parentMessageId, $recordedAt);

        $this->logger->info("Recorded dependency: $dependentMessageId depends on $parentMessageId");
    }

    public function updateDependentMessageStatuses(
        string $parentMessageId,
        MessageStatusEnum $newStatus,
        ?string $reason = null,
    ): void {
        $context = [
            'parent_message_id' => $parentMessageId,
            'new_status' => $newStatus->value,
        ];

        if ($reason !== null) {
            $context['reason'] = $reason;
        }

        $this->logger->info("About to update dependent message statuses for parent $parentMessageId", $context);

        $this->decorated->updateDependentMessageStatuses($parentMessageId, $newStatus, $reason);

        $this->logger->info("Updated dependent message statuses for parent $parentMessageId");
    }
}
