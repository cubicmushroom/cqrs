<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Middleware;

use CubicMushroom\Cqrs\Bus\Stamp\CausedByMessageStamp;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\MessageTypeEnum;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Throwable;

use function assert;

/**
 * Middleware that tracks which messages are responsible for dispatching others.
 */
final class BlameMiddleware implements MiddlewareInterface
{
    /**
     * @var list<CausedByMessageStamp>
     */
    private array $currentCauseStamps = [];


    /**
     * @inheritDoc
     *
     * @throws ExceptionInterface
     * @throws Throwable
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $this->attachCurrentCauseStamps($envelope);

        $message = $envelope->getMessage();
        $messageType = MessageTypeEnum::getMessageType($message);
        $messageId = MessageIdStamp::getMessageId($envelope);

        $newCauseStamp = new CausedByMessageStamp($messageType, $messageId);

        $previousCauseStamps = $this->currentCauseStamps;
        if (!$this->hasCauseStamp($this->currentCauseStamps, $newCauseStamp)) {
            $this->currentCauseStamps[] = $newCauseStamp;
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->currentCauseStamps = $previousCauseStamps;
        }
    }


    private function attachCurrentCauseStamps(Envelope $envelope): Envelope
    {
        if ($this->currentCauseStamps === []) {
            $this->currentCauseStamps = $envelope->all(CausedByMessageStamp::class);
        }

        if ($this->currentCauseStamps === []) {
            return $envelope;
        }

        $existingIdentifiers = $this->indexCauseStamps($envelope);

        foreach ($this->currentCauseStamps as $causeStamp) {
            if (isset($existingIdentifiers[(string)$causeStamp])) {
                continue;
            }

            $envelope = $envelope->with($causeStamp);
            $existingIdentifiers[(string)$causeStamp] = true;
        }

        return $envelope;
    }


    /**
     * @return array<string, true>
     */
    private function indexCauseStamps(Envelope $envelope): array
    {
        $index = [];
        foreach ($envelope->all(CausedByMessageStamp::class) as $stamp) {
            assert($stamp instanceof CausedByMessageStamp);
            $index[(string)$stamp] = true;
        }

        return $index;
    }


    /**
     * @param list<CausedByMessageStamp> $stamps
     */
    private function hasCauseStamp(array $stamps, CausedByMessageStamp $candidate): bool
    {
        return array_any($stamps, fn($stamp) => (string)$stamp === (string)$candidate);
    }
}
