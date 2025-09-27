<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus;

use CubicMushroom\Cqrs\Bus\SymfonyQueryBus;
use CubicMushroom\Cqrs\Query\QueryInterface;
use CubicMushroom\Cqrs\Tests\Dummy\DummyStamp;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Unit tests for SymfonyQueryBus.
 */
final class SymfonyQueryBusTest extends TestCase
{
    private MessageBusInterface $messageBus;

    private SymfonyQueryBus $queryBus;

    private array $result;


    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = new SymfonyQueryBus($this->messageBus);

        $this->result = ['id' => 1, 'name' => 'Test Result'];
        $this->handledStamp = new HandledStamp($this->result, 'DummyQueryHandler');
    }


    public function test_dispatch_query_passes_on_provided_stamps(): void
    {
        $query = $this->createMock(QueryInterface::class);

        $originalStamps = [
            new DelayStamp(100),
            new DummyStamp(),
        ];

        $result = ['id' => 1, 'name' => 'Test Result'];

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($query, $originalStamps)
            ->willReturn(new Envelope($query, [...$originalStamps, new HandledStamp($result, 'DummyQueryHandler')]));

        $this->queryBus->dispatch($query, $originalStamps);
    }


    public function test_dispatch_query_returns_result(): void
    {
        $query = $this->createMock(QueryInterface::class);

        $this->messageBus
            ->method('dispatch')
            ->with($query, $this->isArray())
            ->willReturn(new Envelope($query, [new HandledStamp($this->result, 'DummyQueryHandler')]));

        $result = $this->queryBus->dispatch($query);

        $this->assertEquals($this->result, $result);
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
            ->with($this->isInstanceOf(QueryInterface::class), $this->isArray())
            ->willReturnCallback(fn(QueryInterface $query) => match ($query) {
                $arrayResultQuery => new Envelope($arrayResultQuery, [new HandledStamp($arrayResult, 'ArrayHandler')]),
                $objectResultQuery => new Envelope(
                    $objectResultQuery,
                    [new HandledStamp($objectResult, 'ObjectHandler')],
                ),
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
