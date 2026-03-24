<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\InvoicePaymentRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class InvoicePaymentRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testRepositoryMapsTableAndEntity(): void
    {
        $repo = new InvoicePaymentRepository();
        $table = new ReflectionMethod($repo, 'table');
        $table->setAccessible(true);
        $entity = new ReflectionMethod($repo, 'entityType');
        $entity->setAccessible(true);

        self::assertSame('wp_trn_invoice_payments', $table->invoke($repo));
        self::assertSame('invoice_payment', $entity->invoke($repo));
    }

    public function testTotalPaidByInvoiceReturnsExpectedValue(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->sumByInvoice[11] = 3400;

        $repo = new InvoicePaymentRepository();

        self::assertSame(3400, $repo->totalPaidByInvoice(11));
        self::assertSame(0, $repo->totalPaidByInvoice(99));
    }

    public function testCreatePathReturnsInsertId(): void
    {
        $repo = new InvoicePaymentRepository();

        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->insert_id = 77;

        $id = $repo->create([
            'invoice_id' => 5,
            'payment_date' => '2026-03-24 12:00:00',
            'amount_minor' => 2500,
            'currency' => 'SEK',
            'method' => 'manual',
            'reference' => 'BANK-1',
            'note' => 'part payment',
            'actor_user_id' => 9,
        ]);

        self::assertSame(77, $id);
        $insertTables = array_map(
            static fn (array $insert): string => (string) ($insert['table'] ?? ''),
            $wpdb->insertHistory
        );

        self::assertContains('wp_trn_invoice_payments', $insertTables);
        self::assertContains('wp_trn_audit_log', $insertTables);
    }
}
