<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\InvoicePrintViewModel;

final class InvoicePrintViewModelTest extends TestCase
{
    private InvoicePrintViewModel $viewModel;

    protected function setUp(): void
    {
        $this->viewModel = new InvoicePrintViewModel();
    }

    public function testBuildCreatesStableSectionsFromInvoiceSnapshotAndContext(): void
    {
        $invoice = [
            'id' => 23,
            'offert_id' => 17,
            'estimate_id' => 55,
            'document_number' => 'IN-2026-001',
            'version_no' => 1,
            'status' => 'issued',
            'issued_at' => '2026-03-10 10:00:00',
            'currency' => 'SEK',
            'vat_rate_percent' => '25.00',
            'total_inc_vat_minor' => 13000,
        ];
        $snapshot = [
            'header' => ['title' => 'Kitchen renovation'],
            'totals' => [
                'labour_total_minor' => 7000,
                'materials_total_minor' => 3000,
                'subtotal_ex_vat_minor' => 10000,
                'vat_minor' => 3000,
                'total_inc_vat_minor' => 13000,
            ],
            'lines' => [['title' => 'Paint walls', 'unit' => 'm2', 'quantity' => 10, 'hours' => 8, 'labour_subtotal_minor' => 7000]],
            'material_lines' => [['name' => 'Paint', 'unit' => 'bucket', 'quantity' => 2, 'subtotal_minor' => 3000]],
            'metadata' => ['source_estimate_title' => 'Kitchen renovation'],
        ];
        $context = [
            'source_offert' => ['id' => 17, 'document_number' => 'OF-2026-001'],
            'source_estimate' => ['id' => 55, 'title' => 'Kitchen renovation'],
            'project' => ['name' => 'Project A', 'code' => 'PA-1'],
            'property' => ['name' => 'Property A', 'address_line' => 'Main 1', 'city' => 'Stockholm', 'postal_code' => '11111'],
            'client' => ['name' => 'Client A', 'org_number' => '555', 'email' => 'a@example.com', 'phone' => '123'],
            'payment_summary' => [
                'invoice_total_minor' => 13000,
                'paid_total_minor' => 3000,
                'outstanding_minor' => 10000,
                'payment_count' => 1,
                'computed_status' => 'partially_paid',
            ],
            'payments' => [[
                'payment_date' => '2026-03-11 12:00:00',
                'amount_minor' => 3000,
                'currency' => 'SEK',
                'method' => 'manual',
                'reference' => 'REF-1',
                'note' => 'First payment',
            ]],
        ];

        $result = $this->viewModel->build($invoice, $snapshot, $context);

        self::assertSame('IN-2026-001', $result['document']['document_number']);
        self::assertSame('17', $result['context']['source_offert_id']);
        self::assertSame('Kitchen renovation', $result['context']['source_title']);
        self::assertSame('7000', $result['totals'][0]['minor']);
        self::assertSame('Paint walls', $result['labour_lines'][0]['title']);
        self::assertSame('Paint', $result['material_lines'][0]['name']);
        self::assertSame('partially_paid', $result['payment_summary']['computed_status']);
        self::assertSame('REF-1', $result['payments'][0]['reference']);
    }

    public function testBuildNormalizesMissingSectionsSafely(): void
    {
        $result = $this->viewModel->build(
            ['id' => 5, 'total_inc_vat_minor' => 999],
            [
                'header' => [],
                'totals' => [],
                'lines' => [],
                'material_lines' => [],
                'metadata' => [],
            ],
            [
                'source_offert' => [],
                'source_estimate' => [],
                'project' => [],
                'property' => [],
                'client' => [],
                'payment_summary' => [],
                'payments' => [],
            ]
        );

        self::assertSame('', $result['document']['document_number']);
        self::assertSame('', $result['context']['source_offert_id']);
        self::assertCount(5, $result['totals']);
        self::assertSame([], $result['labour_lines']);
        self::assertSame([], $result['material_lines']);
        self::assertSame('999', $result['payment_summary']['invoice_total_minor']);
        self::assertSame([], $result['payments']);
    }

    public function testBuildPrefersBestAvailableCandidateFields(): void
    {
        $result = $this->viewModel->build(
            ['offert_id' => 9, 'estimate_id' => 77, 'currency' => 'SEK'],
            [
                'header' => ['source_estimate_title' => 'Header title'],
                'totals' => [],
                'lines' => [[
                    'line_title_ru_snapshot' => 'RU title',
                    'unit_code_snapshot' => 'h',
                    'quantity' => 4,
                    'calculated_hours' => 2,
                    'labour_subtotal_minor' => 1000,
                ]],
                'material_lines' => [[
                    'material_name_sv_snapshot' => 'SV material',
                    'unit_code_snapshot' => 'pcs',
                    'quantity' => 1,
                    'subtotal_minor' => 500,
                ]],
                'metadata' => [],
            ],
            [
                'source_estimate' => ['id' => 77, 'title' => 'Estimate title'],
                'payments' => [[
                    'payment_date' => '2026-03-01',
                    'amount_minor' => 500,
                    'method' => 'manual',
                    'reference' => 'A',
                    'note' => '',
                ]],
            ]
        );

        self::assertSame('Header title', $result['context']['source_title']);
        self::assertSame('RU title', $result['labour_lines'][0]['title']);
        self::assertSame('SV material', $result['material_lines'][0]['name']);
        self::assertSame('SEK', $result['payments'][0]['currency']);
    }

    public function testBuildIncludesPaymentSummaryAndPaymentRowsWhenPresent(): void
    {
        $result = $this->viewModel->build(
            ['total_inc_vat_minor' => 10000],
            [
                'header' => [],
                'totals' => [],
                'lines' => [],
                'material_lines' => [],
                'metadata' => [],
            ],
            [
                'payment_summary' => [
                    'invoice_total_minor' => 10000,
                    'paid_total_minor' => 8000,
                    'outstanding_minor' => 2000,
                    'payment_count' => 2,
                    'computed_status' => 'partially_paid',
                ],
                'payments' => [
                    ['payment_date' => '2026-01-10', 'amount_minor' => 3000, 'currency' => 'SEK', 'method' => 'bank', 'reference' => 'R1', 'note' => 'n1'],
                    ['payment_date' => '2026-02-10', 'amount_minor' => 5000, 'currency' => 'SEK', 'method' => 'bank', 'reference' => 'R2', 'note' => 'n2'],
                ],
            ]
        );

        self::assertSame('10000', $result['payment_summary']['invoice_total_minor']);
        self::assertSame('8000', $result['payment_summary']['paid_total_minor']);
        self::assertSame('2', $result['payment_summary']['payment_count']);
        self::assertCount(2, $result['payments']);
    }

    public function testBuildHandlesEmptyLinkedContextMapsWithoutFatal(): void
    {
        $result = $this->viewModel->build(
            ['offert_id' => 1, 'estimate_id' => 2],
            [
                'header' => [],
                'totals' => [],
                'lines' => [],
                'material_lines' => [],
                'metadata' => [],
            ],
            [
                'source_offert' => [],
                'source_estimate' => [],
                'project' => [],
                'property' => [],
                'client' => [],
            ]
        );

        self::assertSame('1', $result['context']['source_offert_id']);
        self::assertSame('2', $result['context']['source_estimate_id']);
        self::assertSame('', $result['context']['project_name']);
        self::assertSame('', $result['context']['client_name']);
    }
}
