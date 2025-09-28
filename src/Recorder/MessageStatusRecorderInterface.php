<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Recorder;

use CubicMushroom\Cqrs\MessageStatusEnum;
use CubicMushroom\Cqrs\MessageTypeEnum;
use DateTimeImmutable;

/**
 * Interface for recording the status of messages and their relationships.
 *
 * This interface is responsible for persisting message status changes and maintaining
 * the causation chain between messages to track complex async workflows.
 */
interface MessageStatusRecorderInterface
{
    /**
     * Records a status change for a message.
     *
     * @param MessageTypeEnum $messageType The type of the message (Command, Query, Event).
     * @param string $messageId The unique ID of the message.
     * @param MessageStatusEnum $status The new status of the message.
     * @param array $causedByMessageIds Array of message IDs that caused this message to be dispatched.
     * @param mixed $data Optional additional data associated with this status change (e.g., handler results, error details, transport info).
     * @param DateTimeImmutable|null $occurredAt When this status change occurred (defaults to now).
     */
    public function recordStatus(
        MessageTypeEnum $messageType,
        string $messageId,
        MessageStatusEnum $status,
        array $causedByMessageIds = [],
        mixed $data = null,
        ?DateTimeImmutable $occurredAt = null,
    ): void;


    /**
     * Records a dependency relationship between messages.
     *
     * This creates an explicit record that one message depends on another,
     * enabling tracking of message causation chains and determining when
     * all related processing is complete.
     *
     * @param string $dependentMessageId The ID of the message that depends on another.
     * @param string $parentMessageId The ID of the message that the dependent message was caused by.
     * @param DateTimeImmutable|null $recordedAt When this dependency was recorded (defaults to now).
     */
    public function recordDependency(
        string $dependentMessageId,
        string $parentMessageId,
        ?DateTimeImmutable $recordedAt = null,
    ): void;


    /**
     * Updates the status of dependent messages when a parent message status changes.
     *
     * For example, when a command fails, all pending sub-commands/events it triggered
     * might need to be marked as cancelled or failed.
     *
     * @param string $parentMessageId The ID of the parent message whose status changed.
     * @param MessageStatusEnum $newStatus The status to apply to dependent messages.
     * @param string|null $reason Optional reason for the status update.
     */
    public function updateDependentMessageStatuses(
        string $parentMessageId,
        MessageStatusEnum $newStatus,
        ?string $reason = null,
    ): void;
}
