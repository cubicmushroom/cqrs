<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs;

/**
 * Enum representing the various statuses a message can have during its lifecycle.
 */
enum MessageStatusEnum: string
{
    /**
     * Message has been dispatched to a bus but not yet sent to transport.
     */
    case DISPATCHED = 'dispatched';

    /**
     * Message has been sent to a transport (queue/async processing).
     */
    case SENT = 'sent';

    /**
     * Message is currently being processed by a handler.
     */
    case PROCESSING = 'processing';

    /**
     * Message has been successfully handled/processed.
     */
    case HANDLED = 'handled';

    /**
     * Message processing failed with an error.
     */
    case FAILED = 'failed';

    /**
     * Message processing timed out.
     */
    case TIMEOUT = 'timeout';

    /**
     * Message was rejected (e.g., validation failed).
     */
    case REJECTED = 'rejected';
}
