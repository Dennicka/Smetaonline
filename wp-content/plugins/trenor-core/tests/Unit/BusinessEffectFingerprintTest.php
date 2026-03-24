<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\BusinessEffectFingerprint;

final class BusinessEffectFingerprintTest extends TestCase
{
    public function testOffertFingerprintIsStableForSameEstimateState(): void
    {
        $service = new BusinessEffectFingerprint();
        $estimate = ['id' => 7, 'status' => 'draft', 'currency' => 'SEK', 'vat_rate_percent' => '25.0000', 'labour_rate_minor' => 50000, 'title' => 'A'];
        $lines = [
            ['id' => 2, 'work_item_id' => 4, 'room_id' => 10, 'quantity' => '2.0', 'unit_code' => 'm2', 'complexity' => 'medium', 'price_minor' => 100, 'line_total_minor' => 200, 'status' => 'active'],
            ['id' => 1, 'work_item_id' => 3, 'room_id' => 11, 'quantity' => '1.0', 'unit_code' => 'm2', 'complexity' => 'slow', 'price_minor' => 80, 'line_total_minor' => 80, 'status' => 'active'],
        ];
        $materials = [['id' => 3, 'material_id' => 9, 'room_id' => 1, 'quantity' => '1.0', 'unit_code' => 'pcs', 'sell_price_minor_snapshot' => 300, 'subtotal_minor' => 300, 'status' => 'active', 'consumption_note' => 'n']];
        $totals = ['labour_total_minor' => 280, 'materials_total_minor' => 300, 'subtotal_ex_vat_minor' => 580, 'vat_minor' => 145, 'total_inc_vat_minor' => 725];

        $hashA = $service->offertForEstimate($estimate, $lines, $materials, $totals);
        $hashB = $service->offertForEstimate($estimate, array_reverse($lines), $materials, $totals);

        self::assertSame($hashA, $hashB);
    }

    public function testInvoiceFingerprintChangesWhenOffertStateChanges(): void
    {
        $service = new BusinessEffectFingerprint();
        $offert = ['id' => 4, 'status' => 'accepted', 'currency' => 'SEK', 'total_inc_vat_minor' => 1000, 'snapshot_json' => '{"x":1}'];

        $hashA = $service->invoiceForOffert($offert, ['lines' => [['id' => 1]]]);
        $offert['snapshot_json'] = '{"x":2}';
        $hashB = $service->invoiceForOffert($offert, ['lines' => [['id' => 1]]]);

        self::assertNotSame($hashA, $hashB);
    }

    public function testPaymentFingerprintBlocksOnlySamePayload(): void
    {
        $service = new BusinessEffectFingerprint();

        $payloadA = [
            'invoice_id' => 12,
            'payment_date' => '2026-03-24 10:00:00',
            'amount_minor' => 100,
            'currency' => 'sek',
            'method' => 'Manual',
            'reference' => 'abc',
            'note' => 'n',
        ];

        $hashA = $service->paymentPayload($payloadA);
        $hashB = $service->paymentPayload($payloadA);
        self::assertSame($hashA, $hashB);

        $payloadA['reference'] = 'other';
        self::assertNotSame($hashA, $service->paymentPayload($payloadA));
    }

    public function testCreditNoteFingerprintChangesWhenInvoiceStateChanges(): void
    {
        $service = new BusinessEffectFingerprint();
        $invoice = ['id' => 15, 'status' => 'issued', 'currency' => 'SEK', 'snapshot_json' => '{"s":1}', 'total_inc_vat_minor' => 500];

        $hashA = $service->creditNoteForInvoice($invoice);
        $invoice['total_inc_vat_minor'] = 700;

        self::assertNotSame($hashA, $service->creditNoteForInvoice($invoice));
    }

    public function testAvtalFingerprintChangesWhenOffertSnapshotChanges(): void
    {
        $service = new BusinessEffectFingerprint();
        $offert = ['id' => 4, 'status' => 'accepted', 'currency' => 'SEK', 'snapshot_json' => '{"x":1}', 'total_inc_vat_minor' => 1000];

        $hashA = $service->avtalForOffert($offert, ['totals' => ['total_inc_vat_minor' => 1000]]);
        $hashB = $service->avtalForOffert($offert, ['totals' => ['total_inc_vat_minor' => 1100]]);

        self::assertNotSame($hashA, $hashB);
    }

    public function testReminderFingerprintChangesWithReminderEffectContext(): void
    {
        $service = new BusinessEffectFingerprint();
        $invoice = ['id' => 17, 'status' => 'issued', 'currency' => 'SEK', 'snapshot_json' => '{"a":1}'];
        $summary = ['computed_status' => 'partially_paid', 'outstanding_minor' => 2500];

        $hashA = $service->reminderForInvoice($invoice, $summary, 1);
        $hashB = $service->reminderForInvoice($invoice, $summary, 1);
        self::assertSame($hashA, $hashB);

        $summary['outstanding_minor'] = 1500;
        self::assertNotSame($hashA, $service->reminderForInvoice($invoice, $summary, 1));
    }
}
