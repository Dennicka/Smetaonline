<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\CreditNoteRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class CreditNoteRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testMapsCorrectTableAndEntityType(): void
    {
        $repo = new CreditNoteRepository();
        $table = new ReflectionMethod($repo, 'table');
        $table->setAccessible(true);
        $entity = new ReflectionMethod($repo, 'entityType');
        $entity->setAccessible(true);

        self::assertSame('wp_trn_credit_notes', $table->invoke($repo));
        self::assertSame('credit_note', $entity->invoke($repo));
    }

    public function testNextVersionNoStartsFromOneAndIncrements(): void
    {
        $repo = new CreditNoteRepository();

        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->maxVersion = 0;
        self::assertSame(1, $repo->nextVersionNo(7));

        $wpdb->maxVersion = 9;
        self::assertSame(10, $repo->nextVersionNo(7));
    }

    public function testTransitionArchivePathWorksOnlyForAllowedStates(): void
    {
        $repo = new CreditNoteRepository();

        self::assertFalse($repo->transitionStatus(3, 'paid'));
        self::assertTrue($repo->transitionStatus(3, 'archived'));
    }

    public function testCreateReturnsInsertIdAndWritesAuditTrail(): void
    {
        $repo = new CreditNoteRepository();

        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->insert_id = 22;

        $id = $repo->create([
            'invoice_id' => 17,
            'offert_id' => 9,
            'estimate_id' => 3,
            'document_number' => 'CRN-202603-00022',
            'version_no' => 2,
            'status' => 'issued',
            'currency' => 'SEK',
            'vat_rate_percent' => 25.0,
            'snapshot_json' => '{}',
        ]);

        self::assertSame(22, $id);
        self::assertCount(2, $wpdb->insertHistory);
        self::assertSame('wp_trn_credit_notes', $wpdb->insertHistory[0]['table']);
        self::assertSame('wp_trn_audit_log', $wpdb->insertHistory[1]['table']);
    }
}
