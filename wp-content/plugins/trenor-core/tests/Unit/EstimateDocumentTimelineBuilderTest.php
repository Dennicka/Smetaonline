<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\EstimateDocumentTimelineBuilder;

final class EstimateDocumentTimelineBuilderTest extends TestCase
{
    private EstimateDocumentTimelineBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new EstimateDocumentTimelineBuilder();
    }

    public function testEmptySourcesReturnEmptyRowsAndZeroSummary(): void
    {
        $actual = $this->builder->build(100, [], [], [], []);

        self::assertSame([], $actual['rows']);
        self::assertSame([
            'snapshots_count' => 0,
            'offerts_count' => 0,
            'invoices_count' => 0,
            'payments_count' => 0,
            'invoiced_total_minor' => 0,
            'paid_total_minor' => 0,
            'outstanding_minor' => 0,
        ], $actual['summary']);
    }

    public function testMixedSourcesCountsAndTotalsAreCorrect(): void
    {
        $actual = $this->builder->build(
            10,
            [['id' => 7, 'created_at' => '2026-01-01 10:00:00', 'snapshot_type' => 'manual']],
            [['id' => 11, 'estimate_id' => 10, 'document_number' => 'OFF-11', 'status' => 'issued', 'issued_at' => '2026-01-02 09:00:00', 'currency' => 'SEK', 'total_inc_vat_minor' => 1000]],
            [
                11 => [
                    ['id' => 21, 'offert_id' => 11, 'estimate_id' => 10, 'document_number' => 'INV-21', 'status' => 'issued', 'issued_at' => '2026-01-03 08:00:00', 'currency' => 'SEK', 'total_inc_vat_minor' => 3000],
                ],
            ],
            [
                21 => [
                    ['id' => 31, 'invoice_id' => 21, 'payment_reference' => 'PAY-31', 'payment_date' => '2026-01-04 07:00:00', 'amount_minor' => 1200, 'currency' => 'SEK'],
                    ['id' => 32, 'invoice_id' => 21, 'payment_date' => '2026-01-05 07:00:00', 'amount_minor' => 200, 'currency' => 'SEK'],
                ],
            ]
        );

        self::assertSame(1, $actual['summary']['snapshots_count']);
        self::assertSame(1, $actual['summary']['offerts_count']);
        self::assertSame(1, $actual['summary']['invoices_count']);
        self::assertSame(2, $actual['summary']['payments_count']);
        self::assertSame(3000, $actual['summary']['invoiced_total_minor']);
        self::assertSame(1400, $actual['summary']['paid_total_minor']);
        self::assertSame(1600, $actual['summary']['outstanding_minor']);
    }

    public function testOutstandingNeverGoesBelowZero(): void
    {
        $actual = $this->builder->build(
            10,
            [],
            [['id' => 11, 'estimate_id' => 10]],
            [11 => [['id' => 21, 'offert_id' => 11, 'total_inc_vat_minor' => 1000]]],
            [21 => [['id' => 31, 'invoice_id' => 21, 'amount_minor' => 5000]]]
        );

        self::assertSame(0, $actual['summary']['outstanding_minor']);
    }

    public function testSortingByDateThenFallbackAndEmptyDatesLast(): void
    {
        $actual = $this->builder->build(
            1,
            [
                ['id' => 2, 'created_at' => ''],
                ['id' => 1, 'created_at' => '2026-01-01 00:00:00'],
            ],
            [
                ['id' => 4, 'estimate_id' => 1, 'issued_at' => '2026-01-10 00:00:00'],
                ['id' => 3, 'estimate_id' => 1, 'issued_at' => '2026-01-10 00:00:00'],
            ],
            [],
            []
        );

        self::assertSame('offert', $actual['rows'][0]['source_type']);
        self::assertSame(4, $actual['rows'][0]['source_id']);
        self::assertSame('offert', $actual['rows'][1]['source_type']);
        self::assertSame(3, $actual['rows'][1]['source_id']);
        self::assertSame('', $actual['rows'][3]['event_at']);
    }

    public function testMissingKeysAreHandledSafelyAndRelationsArePreserved(): void
    {
        $actual = $this->builder->build(
            77,
            [['id' => 5]],
            [['id' => 11]],
            [
                11 => [
                    ['id' => 21],
                ],
            ],
            [
                21 => [
                    ['id' => 31],
                ],
            ]
        );

        $invoiceRow = null;
        $paymentRow = null;
        foreach ($actual['rows'] as $row) {
            if ($row['source_type'] === 'invoice') {
                $invoiceRow = $row;
            }
            if ($row['source_type'] === 'payment') {
                $paymentRow = $row;
            }
        }

        self::assertNotNull($invoiceRow);
        self::assertNotNull($paymentRow);
        self::assertSame(11, $invoiceRow['offert_id']);
        self::assertSame(21, $paymentRow['invoice_id']);
        self::assertSame('', $paymentRow['document_number_or_reference']);
        self::assertSame('', $paymentRow['event_at']);
    }
}
