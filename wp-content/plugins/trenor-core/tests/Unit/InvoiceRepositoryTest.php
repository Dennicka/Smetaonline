<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\InvoiceRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class InvoiceRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testRepositoryMapsTableAndEntity(): void
    {
        $repo = new InvoiceRepository();
        $table = new ReflectionMethod($repo, 'table');
        $table->setAccessible(true);
        $entity = new ReflectionMethod($repo, 'entityType');
        $entity->setAccessible(true);

        self::assertSame('wp_trn_invoices', $table->invoke($repo));
        self::assertSame('invoice', $entity->invoke($repo));
    }

    public function testNextVersionNoStartsAtOneAndIncrements(): void
    {
        $repo = new InvoiceRepository();

        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->maxVersion = 0;
        self::assertSame(1, $repo->nextVersionNo(17));

        $wpdb->maxVersion = 5;
        self::assertSame(6, $repo->nextVersionNo(17));
    }

    public function testCreateReturnsInsertId(): void
    {
        $repo = new InvoiceRepository();

        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->insert_id = 41;

        $id = $repo->create([
            'offert_id' => 17,
            'estimate_id' => 44,
            'document_number' => 'INV-202603-00031',
            'version_no' => 3,
            'status' => 'issued',
            'currency' => 'SEK',
            'vat_rate_percent' => 25.0,
            'snapshot_json' => '{}',
        ]);

        self::assertSame(41, $id);
    }

    public function testTransitionStatusFollowsStateMachineRules(): void
    {
        $repo = new InvoiceRepository();

        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'issued'];
        self::assertTrue($repo->transitionStatus(10, 'partially_paid'));

        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'partially_paid'];
        self::assertTrue($repo->transitionStatus(10, 'paid'));

        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'paid'];
        self::assertTrue($repo->transitionStatus(10, 'archived'));

        $wpdb->rowsById[11] = ['id' => 11, 'status' => 'archived'];
        self::assertFalse($repo->transitionStatus(11, 'issued'));

        $wpdb->rowsById[12] = ['id' => 12, 'status' => 'issued'];
        self::assertFalse($repo->transitionStatus(12, 'issued'));
    }
}
