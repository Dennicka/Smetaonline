<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\OffertRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class OffertRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testVersionNoStartsFromOneAndIncrements(): void
    {
        $repo = new OffertRepository();

        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->maxVersion = 0;
        self::assertSame(1, $repo->nextVersionNo(99));

        $wpdb->maxVersion = 1;
        self::assertSame(2, $repo->nextVersionNo(99));
    }

    public function testStatusTransitionsRespectPolicyAndNoGeneralUpdatePath(): void
    {
        $repo = new OffertRepository();
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];

        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'issued'];
        self::assertTrue($repo->transitionStatus(10, 'accepted'));
        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'issued'];
        self::assertTrue($repo->transitionStatus(10, 'rejected'));
        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'issued'];
        self::assertTrue($repo->transitionStatus(10, 'archived'));
        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'accepted'];
        self::assertTrue($repo->transitionStatus(10, 'archived'));
        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'rejected'];
        self::assertTrue($repo->transitionStatus(10, 'archived'));

        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'accepted'];
        self::assertFalse($repo->transitionStatus(10, 'rejected'));
        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'rejected'];
        self::assertFalse($repo->transitionStatus(10, 'accepted'));
        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'archived'];
        self::assertFalse($repo->transitionStatus(10, 'accepted'));
        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'archived'];
        self::assertFalse($repo->transitionStatus(10, 'rejected'));
        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'archived'];
        self::assertFalse($repo->transitionStatus(10, 'issued'));

        self::assertFalse(method_exists($repo, 'updateEntity'));

        $statuses = array_column(array_map(static fn (array $row): array => $row['data'], $wpdb->updatedRows), 'status');
        self::assertSame(['accepted', 'rejected', 'archived', 'archived', 'archived'], $statuses);
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
