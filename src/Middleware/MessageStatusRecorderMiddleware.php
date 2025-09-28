<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Middleware;

use CubicMushroom\Cqrs\Bus\Stamp\CausedByMessageStamp;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\MessageStatusEnum;
use CubicMushroom\Cqrs\MessageTypeEnum;
use CubicMushroom\Cqrs\Recorder\MessageStatusRecorderInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

final readonly class MessageStatusRecorderMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageStatusRecorderInterface $recorder,
    ) {
    }


    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $messageType = MessageTypeEnum::getMessageType($message);
        $messageId = MessageIdStamp::getMessageId($envelope);
        $causedByMessageIds = $this->extractCausedByMessageIds($envelope);

        // Record "dispatched" status if this is not a received message
        if ($envelope->last(ReceivedStamp::class) === null) {
            $this->recorder->recordStatus(
                $messageType,
                $messageId,
                MessageStatusEnum::DISPATCHED,
                $causedByMessageIds,
            );

            // Record dependencies for each parent message that caused this dispatch
            foreach ($causedByMessageIds as $parentMessageId) {
                $this->recorder->recordDependency($messageId, $parentMessageId);
            }
        }

        $envelope = $stack->next()->handle($envelope, $stack);

        // Record "sent" status if message was sent to transport
        $sentStamps = $envelope->all(SentStamp::class);
        if (!empty($sentStamps)) {
            $this->recorder->recordStatus(
                $messageType,
                $messageId,
                MessageStatusEnum::SENT,
                $causedByMessageIds,
                $this->extractTransportData($sentStamps),
            );

            return $envelope;
        }

        // Record "handled" status if message was handled
        $handledStamps = $envelope->all(HandledStamp::class);
        if (!empty($handledStamps)) {
            $this->recorder->recordStatus(
                $messageType,
                $messageId,
                MessageStatusEnum::HANDLED,
                $causedByMessageIds,
                $this->extractHandlerResults($handledStamps),
            );
        }

        return $envelope;
    }


    /**
     * Extracts message IDs from CausedByMessageStamp instances.
     *
     * @param Envelope $envelope
     *
     * @return array<string>
     */
    private function extractCausedByMessageIds(Envelope $envelope): array
    {
        $causedByStamps = $envelope->all(CausedByMessageStamp::class);

        return array_map(
            static fn(CausedByMessageStamp $stamp): string => $stamp->messageId,
            $causedByStamps,
        );
    }


    /**
     * Extracts transport information from SentStamp instances.
     *
     * @param array<SentStamp> $sentStamps
     *
     * @return array
     */
    private function extractTransportData(array $sentStamps): array
    {
        return array_map(
            static fn(SentStamp $stamp): array => [
                'transport_name' => $stamp->getSenderClass(),
                'sent_at' => new DateTimeImmutable(),
            ],
            $sentStamps,
        );
    }


    /**
     * Extracts handler results from HandledStamp instances.
     *
     * @param array<HandledStamp> $handledStamps
     *
     * @return array
     */
    private function extractHandlerResults(array $handledStamps): array
    {
        return array_map(
            static fn(HandledStamp $stamp): array => [
                'handler_name' => $stamp->getHandlerName(),
                'result' => $stamp->getResult(),
                'handled_at' => new DateTimeImmutable(),
            ],
            $handledStamps,
        );
    }
}
