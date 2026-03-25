<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\OperationalReportBuilder;

final class OperationalReportBuilderTest extends TestCase
{
    public function testInvoicesBuildComputedStatusesAndDueState(): void
    {
        $builder = new OperationalReportBuilder();
        $invoices = [
            ['id' => 11, 'status' => 'issued', 'document_number' => 'INV-11', 'issued_at' => '2026-01-01 10:00:00', 'total_inc_vat_minor' => 10000, 'currency' => 'SEK', 'snapshot_json' => '{"payment_due_date":"2026-01-05"}', 'tax_mode' => 'private_consumer', 'rot_requested' => 1],
            ['id' => 12, 'status' => 'archived', 'document_number' => 'INV-12', 'issued_at' => '2026-03-01 10:00:00', 'total_inc_vat_minor' => 10000, 'currency' => 'SEK'],
        ];

        $rows = $builder->invoices($invoices, static function (int $invoiceId): array {
            if ($invoiceId === 11) {
                return [['amount_minor' => 5000]];
            }

            return [['amount_minor' => 10000]];
        }, ['status' => '', 'date_from' => '', 'date_to' => '', 'period' => ''], 30);

        self::assertCount(2, $rows);
        self::assertSame('partially_paid', $rows[1]['status']);
        self::assertSame('archived', $rows[0]['status']);
        self::assertSame('overdue', $rows[1]['due_state']);
    }

    public function testPaymentAndSupplierFiltersRespectDateRange(): void
    {
        $builder = new OperationalReportBuilder();

        $payments = $builder->payments([
            ['id' => 1, 'invoice_id' => 2, 'payment_date' => '2026-03-20 11:00:00', 'amount_minor' => 1000, 'currency' => 'SEK', 'method' => 'manual', 'reference' => 'A'],
            ['id' => 2, 'invoice_id' => 2, 'payment_date' => '2026-01-02 11:00:00', 'amount_minor' => 2000, 'currency' => 'SEK', 'method' => 'bank', 'reference' => 'B'],
        ], ['status' => '', 'date_from' => '2026-03-01', 'date_to' => '2026-03-30', 'period' => '']);

        self::assertCount(1, $payments);

        $supplierActivity = $builder->supplierImportActivity(
            [['id' => 1, 'status' => 'completed', 'imported_at' => '2026-03-20 11:00:00']],
            [['id' => 2, 'created_at' => '2026-03-21 11:00:00']],
            ['status' => 'completed', 'date_from' => '2026-03-01', 'date_to' => '2026-03-30', 'period' => '']
        );

        self::assertCount(1, $supplierActivity['imports']);
        self::assertCount(1, $supplierActivity['price_changes']);
    }
}
