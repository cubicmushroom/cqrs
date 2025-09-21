<?php /** @noinspection PhpConditionAlreadyCheckedInspection */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Unit\Bus\IdProvider;

use CubicMushroom\Cqrs\Bus\IdProvider\MessageIdFactoryInterface;
use CubicMushroom\Cqrs\Bus\IdProvider\UlidMessageIdFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Uid\Ulid;

/**
 * Unit tests for UlidMessageIdFactory.
 */
final class UlidMessageIdFactoryTest extends TestCase
{
    private UlidMessageIdFactory $factory;


    protected function setUp(): void
    {
        $this->factory = new UlidMessageIdFactory();
    }


    public function test_implements_message_id_factory_interface(): void
    {
        $this->assertInstanceOf(MessageIdFactoryInterface::class, $this->factory);
    }


    public function test_is_readonly(): void
    {
        $reflection = new ReflectionClass($this->factory);
        $this->assertTrue($reflection->isReadOnly());
    }


    public function test_next_id_returns_string(): void
    {
        $id = $this->factory->nextId();

        $this->assertIsString($id);
    }


    public function test_next_id_returns_valid_ulid_format(): void
    {
        $id = $this->factory->nextId();

        // ULID should be 26 characters long
        $this->assertEquals(26, strlen($id));

        // ULID should only contain valid base32 characters (excluding I, L, O, U)
        $this->assertMatchesRegularExpression('/^[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}$/', $id);
    }


    public function test_next_id_returns_parseable_ulid(): void
    {
        $id = $this->factory->nextId();

        // Should be able to parse the generated ID as a valid ULID
        $ulid = Ulid::fromString($id);
        $this->assertInstanceOf(Ulid::class, $ulid);
        $this->assertEquals($id, (string)$ulid);
    }


    public function test_next_id_generates_unique_ids(): void
    {
        $ids = [];
        $iterations = 100;

        // Generate multiple IDs
        for ($i = 0; $i < $iterations; $i++) {
            $ids[] = $this->factory->nextId();
        }

        // All IDs should be unique
        $uniqueIds = array_unique($ids);
        $this->assertCount($iterations, $uniqueIds, 'All generated IDs should be unique');
    }


    public function test_next_id_generates_chronologically_sortable_ids(): void
    {
        $ids = [];
        $iterations = 10;

        // Generate IDs with small delays to ensure different timestamps
        for ($i = 0; $i < $iterations; $i++) {
            $ids[] = $this->factory->nextId();
            // Small delay to ensure different timestamps (ULIDs are time-based)
            usleep(1000); // 1ms delay
        }

        // Sort the IDs lexicographically
        $sortedIds = $ids;
        sort($sortedIds);

        // The sorted order should match the generation order (chronological)
        $this->assertEquals($ids, $sortedIds, 'ULIDs should be chronologically sortable');
    }


    public function test_next_id_contains_timestamp_component(): void
    {
        $beforeTime = time();
        $id = $this->factory->nextId();
        $afterTime = time();

        $ulid = Ulid::fromString($id);
        $ulidTimestamp = $ulid->getDateTime()->getTimestamp();

        // The ULID timestamp should be within the time range when it was generated
        $this->assertGreaterThanOrEqual($beforeTime, $ulidTimestamp);
        $this->assertLessThanOrEqual($afterTime, $ulidTimestamp);
    }


    public function test_next_id_has_random_component(): void
    {
        // Generate multiple IDs at the same millisecond
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $ids[] = $this->factory->nextId();
        }

        // Even if generated at the same time, the random component should make them different
        $uniqueIds = array_unique($ids);
        $this->assertCount(10, $uniqueIds, 'IDs should be unique even when generated quickly');
    }


    public function test_next_id_performance(): void
    {
        $iterations = 1000;
        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->factory->nextId();
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $iterations;

        // Should be able to generate 1000 IDs in reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $totalTime, 'Should generate 1000 IDs in less than 1 second');
        $this->assertLessThan(0.001, $averageTime, 'Average ID generation should be less than 1ms');
    }


    public function test_next_id_consistent_format_across_calls(): void
    {
        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->factory->nextId();
        }

        foreach ($ids as $id) {
            $this->assertEquals(26, strlen($id), "ID '$id' should be 26 characters long");
            $this->assertMatchesRegularExpression(
                '/^[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}$/',
                $id,
                "ID '$id' should match ULID format",
            );
        }
    }


    public function test_next_id_is_case_sensitive(): void
    {
        $id = $this->factory->nextId();

        // ULID should be uppercase
        $this->assertEquals(strtoupper($id), $id, 'ULID should be uppercase');
        $this->assertNotEquals(strtolower($id), $id, 'ULID should not be lowercase');
    }


    public function test_multiple_factory_instances_generate_unique_ids(): void
    {
        $factory1 = new UlidMessageIdFactory();
        $factory2 = new UlidMessageIdFactory();

        $ids1 = [];
        $ids2 = [];

        // Generate IDs from both factories
        for ($i = 0; $i < 50; $i++) {
            $ids1[] = $factory1->nextId();
            $ids2[] = $factory2->nextId();
        }

        $allIds = array_merge($ids1, $ids2);
        $uniqueIds = array_unique($allIds);

        $this->assertCount(100, $uniqueIds, 'Different factory instances should generate unique IDs');
    }
}
