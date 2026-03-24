<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\OffertRepository;

final class OffertRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';
            public array $updatedRows = [];
            public int $maxVersion = 0;

            public function prepare(string $query, ...$args): string
            {
                $escaped = array_map(static fn ($value): string => is_numeric($value) ? (string) $value : "'" . addslashes((string) $value) . "'", $args);
                return vsprintf($query, $escaped);
            }

            public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = [])
            {
                $this->updatedRows[] = ['table' => $table, 'data' => $data, 'where' => $where];
                return 1;
            }

            public function get_var(string $query)
            {
                if (str_contains($query, 'MAX(version_no)')) {
                    return $this->maxVersion;
                }

                return null;
            }

            public function insert(string $table, array $data, array $format = [])
            {
                return 1;
            }
        };
    }

    public function testVersionNoStartsFromOneAndIncrements(): void
    {
        $repo = new OffertRepository();

        $GLOBALS['wpdb']->maxVersion = 0;
        self::assertSame(1, $repo->nextVersionNo(99));

        $GLOBALS['wpdb']->maxVersion = 1;
        self::assertSame(2, $repo->nextVersionNo(99));
    }

    public function testStatusTransitionsAndNoGeneralUpdatePath(): void
    {
        $repo = new OffertRepository();

        self::assertTrue($repo->transitionStatus(10, 'accepted'));
        self::assertTrue($repo->transitionStatus(10, 'rejected'));
        self::assertTrue($repo->transitionStatus(10, 'archived'));
        self::assertFalse($repo->transitionStatus(10, 'draft'));

        self::assertFalse(method_exists($repo, 'updateEntity'));

        $statuses = array_column(array_map(static fn (array $row): array => $row['data'], $GLOBALS['wpdb']->updatedRows), 'status');
        self::assertSame(['accepted', 'rejected', 'archived'], $statuses);
    }

    public function testRepositoryMapsTableAndEntity(): void
    {
        $repo = new OffertRepository();
        $table = new ReflectionMethod($repo, 'table');
        $table->setAccessible(true);
        $entity = new ReflectionMethod($repo, 'entityType');
        $entity->setAccessible(true);

        self::assertSame('wp_trn_offerts', $table->invoke($repo));
        self::assertSame('offert', $entity->invoke($repo));
    }
}
