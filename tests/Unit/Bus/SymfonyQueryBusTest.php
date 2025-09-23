<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus;

use CubicMushroom\Cqrs\Bus\Id\QueryId;
use CubicMushroom\Cqrs\Bus\Stamp\MessageIdStamp;
use CubicMushroom\Cqrs\Bus\StampFactory\MessageIdStampFactoryInterface;
use CubicMushroom\Cqrs\Bus\SymfonyQueryBus;
use CubicMushroom\Cqrs\Query\QueryInterface;
use CubicMushroom\Cqrs\Tests\Dummy\DummyStamp;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Unit tests for SymfonyQueryBus.
 */
final class SymfonyQueryBusTest extends TestCase
{
    private MessageBusInterface $messageBus;

    private LoggerInterface $logger;

    private SymfonyQueryBus $queryBus;

    private MessageIdStamp $messageIdStamp;

    private array $result;

    private $handledStamp;


    protected function setUp(): void
    {
        $idStampFactory = $this->createMock(MessageIdStampFactoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->queryBus = new SymfonyQueryBus(
            $idStampFactory,
            $this->messageBus,
            $this->logger,
        );

        $this->messageIdStamp = new MessageIdStamp('01K5K6P33FP68YWPEY8CB89J1J');
        $idStampFactory
            ->method('attachStamp')
            ->with($this->isArray())
            ->willReturnCallback(fn(array $stamps) => [...$stamps, $this->messageIdStamp]);

        $this->result = ['id' => 1, 'name' => 'Test Result'];
        $this->handledStamp = new HandledStamp($this->result, 'DummyQueryHandler');
    }


    public function test_dispatch_query_adds_message_id_stamp(): void
    {
        $query = $this->createMock(QueryInterface::class);

        $stamps = [
            new DelayStamp(100),
            new DummyStamp(),
        ];

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    fn(Envelope $envelope) => $envelope->all(MessageIdStamp::class) === [$this->messageIdStamp],
                ),
            )
            ->willReturnCallback(fn(Envelope $envelope) => $envelope->with($this->handledStamp));

        $this->queryBus->dispatch($query, $stamps);
    }


    public function test_dispatch_query_passes_on_provided_stamps(): void
    {
        $query = $this->createMock(QueryInterface::class);

        $stamps = [
            new DelayStamp(100),
            new DummyStamp(),
        ];

        $result = ['id' => 1, 'name' => 'Test Result'];

        $envelope = new Envelope($query, [...$stamps, $this->messageIdStamp]);
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($envelope)
            ->willReturn($envelope->with(new HandledStamp($result, 'DummyQueryHandler')));

        $this->queryBus->dispatch($query, $stamps);
    }


    public function test_dispatch_query_returns_result(): void
    {
        $query = $this->createMock(QueryInterface::class);

        $this->messageBus
            ->method('dispatch')
            ->with($this->isInstanceOf(Envelope::class))
            ->willReturnCallback(
                fn(Envelope $envelope) => $envelope->with($this->handledStamp),
            );

        $result = $this->queryBus->dispatch($query);

        $this->assertEquals($this->result, $result);
    }


    public function test_dispatch_logs_messages_on_success(): void
    {
        $query = $this->createMock(QueryInterface::class);

        $this->messageBus
            ->method('dispatch')
            ->with($this->isInstanceOf(Envelope::class))
            ->willReturnCallback(
                fn(Envelope $envelope) => $envelope->with($this->handledStamp),
            );

        $logCalls = [];
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$logCalls) {
                $logCalls[] = [$message, $context];
            });

        $this->queryBus->dispatch($query);

        // Verify the log calls were made in the correct order
        $this->assertCount(2, $logCalls);
        $this->assertEquals('Dispatching query', $logCalls[0][0]);
        $this->assertEquals([
            'message_id' => $this->messageIdStamp->messageId,
            'query_type' => $query::class,
        ], $logCalls[0][1]);
        $this->assertEquals('Query processed successfully', $logCalls[1][0]);
        $this->assertEquals([
            'message_id' => $this->messageIdStamp->messageId,
            'result_type' => 'array',
        ], $logCalls[1][1]);
    }


    public function test_dispatch_query_logs_error_on_exception(): void
    {
        $query = $this->createMock(QueryInterface::class);

        $exception = new RuntimeException('Test exception');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Dispatching query', $this->isArray());

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to process query', $this->isArray());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->queryBus->dispatch($query);
    }


    public function test_dispatch_query_handles_different_result_types(): void
    {
        // Test with array result
        $arrayResultQuery = $this->createMock(QueryInterface::class);
        $arrayResult = ['data' => 'test'];

        // Test with object result
        $objectResultQuery = $this->createMock(QueryInterface::class);
        $objectResult = new stdClass();
        $objectResult->data = 'test';

        // Test with null result
        $nullResultQuery = $this->createMock(QueryInterface::class);

        $this->messageBus
            ->method('dispatch')
            ->with($this->isInstanceOf(Envelope::class))
            ->willReturnCallback(fn(Envelope $envelope) => match ($envelope->getMessage()) {
                $arrayResultQuery => new Envelope($arrayResultQuery, [new HandledStamp($arrayResult, 'ArrayHandler')]),
                $objectResultQuery => new Envelope($objectResultQuery, [new HandledStamp($objectResult, 'ObjectHandler')]),
                $nullResultQuery => new Envelope($nullResultQuery, [new HandledStamp(null, 'NullHandler')]),
            });

        $result = $this->queryBus->dispatch($arrayResultQuery);
        $this->assertEquals($arrayResult, $result);

        $result = $this->queryBus->dispatch($objectResultQuery);
        $this->assertEquals($objectResult, $result);

        $result = $this->queryBus->dispatch($nullResultQuery);
        $this->assertEquals(null, $result);
    }
}
