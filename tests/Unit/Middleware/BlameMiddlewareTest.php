<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Middleware;

use Closure;
use CubicMushroom\Cqrs\Bus\Stamp\CausedByMessageStamp;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\DomainEvent\AbstractDomainEvent;
use CubicMushroom\Cqrs\DomainEvent\DomainEventInterface;
use CubicMushroom\Cqrs\MessageTypeEnum;
use CubicMushroom\Cqrs\Middleware\BlameMiddleware;
use CubicMushroom\Cqrs\Query\QueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

#[CoversClass(BlameMiddleware::class)]
final class BlameMiddlewareTest extends TestCase
{
    private BlameMiddleware $middleware;


    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new BlameMiddleware();
    }


    public function test_handle_without_processing_messages_leaves_envelope_unchanged(): void
    {
        $message = new class implements CommandInterface {
        };
        $envelope = $this->createEnvelope($message, 'command-1');

        $stack = $this->createStack(fn(Envelope $envelope, StackInterface $stack): Envelope => $envelope);

        $result = $this->middleware->handle($envelope, $stack);

        $this->assertSame($envelope, $result);
        $this->assertEmpty($result->all(CausedByMessageStamp::class));
    }


    public function test_nested_dispatch_receives_caused_by_stamp(): void
    {
        $outerMessage = new class implements CommandInterface {
        };
        $outerEnvelope = $this->createEnvelope($outerMessage, 'command-outer');

        $innerMessage = new readonly class extends AbstractDomainEvent implements DomainEventInterface {
        };
        $innerEnvelope = $this->createEnvelope($innerMessage, 'event-inner');
        $innerStack = $this->createStack(fn(Envelope $env, StackInterface $stack): Envelope => $env);

        $capturedInnerEnvelope = null;
        $outerStack = $this->createStack(
            function (/* Envelope $envelope, StackInterface $stack */) use (
                &$capturedInnerEnvelope,
                $innerEnvelope,
                $innerStack,
            ): Envelope {
                /** @noinspection PhpUnnecessaryLocalVariableInspection */
                $capturedInnerEnvelope = $this->middleware->handle($innerEnvelope, $innerStack);

                return $capturedInnerEnvelope;
            },
        );

        $this->middleware->handle($outerEnvelope, $outerStack);

        $this->assertInstanceOf(Envelope::class, $capturedInnerEnvelope);
        $stamps = $capturedInnerEnvelope->all(CausedByMessageStamp::class);
        $this->assertCount(1, $stamps);
        $stamp = $stamps[0];
        $this->assertInstanceOf(CausedByMessageStamp::class, $stamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $stamp->messageType);
        $this->assertSame('command-outer', $stamp->messageId);
        $this->assertSame('COMMAND:command-outer', (string)$stamp);
    }


    public function test_multiple_processing_messages_attach_all_unique_causes(): void
    {
        $grandchildMessage = new class implements QueryInterface {
        };
        $grandchildEnvelope = $this->createEnvelope($grandchildMessage, 'query-grandchild')
            ->with(new CausedByMessageStamp(MessageTypeEnum::COMMAND, 'command-outer'));
        $grandchildStack = $this->createStack(fn(Envelope $envelope, StackInterface $stack): Envelope => $envelope);

        $childMessage = new readonly class extends AbstractDomainEvent implements DomainEventInterface {
        };
        $childEnvelope = $this->createEnvelope($childMessage, 'event-child');

        $capturedGrandchildEnvelope = null;
        $childStack = $this->createStack(
            function (Envelope $envelope/*, StackInterface $stack */) use (
                &$capturedGrandchildEnvelope,
                $grandchildEnvelope,
                $grandchildStack,
            ): Envelope {
                /** @noinspection PhpUnnecessaryLocalVariableInspection */
                $capturedGrandchildEnvelope = $this->middleware->handle($grandchildEnvelope, $grandchildStack);

                return $envelope;
            },
        );

        $outerMessage = new class implements CommandInterface {
        };
        $outerEnvelope = $this->createEnvelope($outerMessage, 'command-outer');

        $capturedChildEnvelope = null;
        $outerStack = $this->createStack(
            function (Envelope $envelope/* , StackInterface $stack */) use (
                &$capturedChildEnvelope,
                $childEnvelope,
                $childStack,
            ): Envelope {
                /** @noinspection PhpUnnecessaryLocalVariableInspection */
                $capturedChildEnvelope = $this->middleware->handle($childEnvelope, $childStack);

                return $envelope;
            },
        );

        $this->middleware->handle($outerEnvelope, $outerStack);

        $this->assertInstanceOf(Envelope::class, $capturedChildEnvelope);
        $childStamps = $capturedChildEnvelope->all(CausedByMessageStamp::class);
        $this->assertCount(1, $childStamps);
        $childStamp = $childStamps[0];
        $this->assertInstanceOf(CausedByMessageStamp::class, $childStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $childStamp->messageType);
        $this->assertSame('command-outer', $childStamp->messageId);

        $this->assertInstanceOf(Envelope::class, $capturedGrandchildEnvelope);
        $grandchildStamps = $capturedGrandchildEnvelope->all(CausedByMessageStamp::class);
        $this->assertCount(2, $grandchildStamps);
        $grandchildStamp = $grandchildStamps[0];
        $this->assertInstanceOf(CausedByMessageStamp::class, $grandchildStamp);
        $this->assertSame(MessageTypeEnum::COMMAND, $grandchildStamp->messageType);
        $this->assertSame('command-outer', $grandchildStamp->messageId);
        $grandchildStamp = $grandchildStamps[1];
        $this->assertInstanceOf(CausedByMessageStamp::class, $grandchildStamp);
        $this->assertSame(MessageTypeEnum::DOMAIN_EVENT, $grandchildStamp->messageType);
        $this->assertSame('event-child', $grandchildStamp->messageId);
    }


    private function createEnvelope(object $message, string $messageId): Envelope
    {
        return new Envelope($message, [new MessageIdStamp($messageId)]);
    }


    private function createStack(Closure $callback): StackInterface
    {
        $middleware = new readonly class($callback) implements MiddlewareInterface {
            public function __construct(private Closure $callback)
            {
            }


            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return ($this->callback)($envelope, $stack);
            }
        };

        return new readonly class($middleware) implements StackInterface {
            public function __construct(private MiddlewareInterface $middleware)
            {
            }


            public function next(): MiddlewareInterface
            {
                return $this->middleware;
            }
        };
    }
}
