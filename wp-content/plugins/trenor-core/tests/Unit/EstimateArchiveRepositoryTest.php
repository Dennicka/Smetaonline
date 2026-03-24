<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Database\EstimateLineRepository;
use Trenor\Core\Database\EstimateMaterialLineRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class EstimateArchiveRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new class () extends WpdbStub {
            public int $updateResult = 1;

            /** @var array<int, string> */
            public array $queries = [];

            public bool $deleteCalled = false;

            public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int
            {
                $this->updatedRows[] = [
                    'table' => $table,
                    'data' => $data,
                    'where' => $where,
                    'format' => $format,
                    'where_format' => $whereFormat,
                ];

                return $this->updateResult;
            }

            public function delete(string $table, array $where, array $format = []): int
            {
                $this->deleteCalled = true;

                return 1;
            }

            public function get_results(string $query, string $output = ARRAY_A): array
            {
                $this->queries[] = $query;

                return [];
            }
        });
    }

    public function testEstimateLineArchiveUsesSoftArchiveAndNeverDeletesRow(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repository = new EstimateLineRepository();

        self::assertTrue($repository->archive(77));
        self::assertCount(1, $wpdb->updatedRows);
        self::assertSame('archived', $wpdb->updatedRows[0]['data']['status']);
        self::assertArrayHasKey('archived_at', $wpdb->updatedRows[0]['data']);
        self::assertSame(['id' => 77, 'status' => 'active'], $wpdb->updatedRows[0]['where']);
        self::assertFalse($wpdb->deleteCalled);

        $wpdb->updateResult = 0;
        self::assertFalse($repository->archive(77));
    }

    public function testEstimateMaterialLineArchiveUsesSoftArchiveAndNeverDeletesRow(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repository = new EstimateMaterialLineRepository();

        self::assertTrue($repository->archive(88));
        self::assertCount(1, $wpdb->updatedRows);
        self::assertSame('archived', $wpdb->updatedRows[0]['data']['status']);
        self::assertArrayHasKey('archived_at', $wpdb->updatedRows[0]['data']);
        self::assertSame(['id' => 88, 'status' => 'active'], $wpdb->updatedRows[0]['where']);
        self::assertFalse($wpdb->deleteCalled);

        $wpdb->updateResult = 0;
        self::assertFalse($repository->archive(88));
    }

    public function testByEstimateFiltersArchivedRowsOutForBothRepositories(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];

        (new EstimateLineRepository())->byEstimate(10);
        (new EstimateMaterialLineRepository())->byEstimate(10);

        self::assertCount(2, $wpdb->queries);
        self::assertStringContainsString("status = 'active'", $wpdb->queries[0]);
        self::assertStringContainsString("status = 'active'", $wpdb->queries[1]);
    }
}
