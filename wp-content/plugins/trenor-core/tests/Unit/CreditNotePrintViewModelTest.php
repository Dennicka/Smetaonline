<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\CreditNotePrintViewModel;

final class CreditNotePrintViewModelTest extends TestCase
{
    private CreditNotePrintViewModel $viewModel;

    protected function setUp(): void
    {
        $this->viewModel = new CreditNotePrintViewModel();
    }

    public function testBuildCreatesExpectedSectionsFromSnapshotAndContext(): void
    {
        $result = $this->viewModel->build(
            [
                'id' => 7,
                'invoice_id' => 9,
                'offert_id' => 3,
                'estimate_id' => 4,
                'document_number' => 'CRN-2026-001',
                'version_no' => 2,
                'status' => 'issued',
                'currency' => 'SEK',
                'total_inc_vat_minor' => 2500,
                'issued_at' => '2026-03-20 12:00:00',
            ],
            [
                'header' => [],
                'totals' => [
                    'labour_total_minor' => 1000,
                    'materials_total_minor' => 1000,
                    'subtotal_ex_vat_minor' => 2000,
                    'vat_minor' => 500,
                    'total_inc_vat_minor' => 2500,
                ],
                'lines' => [['line_title_sv_snapshot' => 'Arbete', 'unit_code_snapshot' => 'h', 'quantity' => 2, 'calculated_hours' => 2, 'labour_subtotal_minor' => 1000]],
                'material_lines' => [['material_name_sv_snapshot' => 'Farg', 'unit_code_snapshot' => 'pcs', 'quantity' => 1, 'subtotal_minor' => 1000]],
                'metadata' => ['source_invoice_document_number' => 'INV-2026-001'],
            ],
            [
                'source_invoice' => ['id' => 9, 'document_number' => 'INV-2026-001'],
                'source_estimate' => ['id' => 4, 'title' => 'Kitchen'],
                'project' => ['name' => 'Project X'],
                'client' => ['name' => 'Client Y'],
            ]
        );

        self::assertSame('CRN-2026-001', $result['document']['document_number']);
        self::assertSame('INV-2026-001', $result['context']['source_invoice_document_number']);
        self::assertSame('Kitchen', $result['context']['source_estimate_title']);
        self::assertSame('1000', $result['totals'][0]['minor']);
        self::assertSame('Arbete', $result['labour_lines'][0]['title']);
        self::assertSame('Farg', $result['material_lines'][0]['name']);
    }

    public function testBuildUsesFallbackValuesWhenSnapshotFieldsAreMissing(): void
    {
        $result = $this->viewModel->build(
            ['invoice_id' => 5, 'total_inc_vat_minor' => 999],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            ['source_invoice' => ['currency' => 'EUR']]
        );

        self::assertSame('EUR', $result['currency']);
        self::assertSame('999', $result['totals'][4]['minor']);
        self::assertSame('5', $result['context']['source_invoice_id']);
        self::assertSame([], $result['labour_lines']);
        self::assertSame([], $result['material_lines']);
    }
}
