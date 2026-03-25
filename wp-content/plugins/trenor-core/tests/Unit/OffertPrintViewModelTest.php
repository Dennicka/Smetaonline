<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\OffertPrintViewModel;

final class OffertPrintViewModelTest extends TestCase
{
    private OffertPrintViewModel $viewModel;

    protected function setUp(): void
    {
        $this->viewModel = new OffertPrintViewModel();
    }

    public function testBuildReturnsStablePrintableStructure(): void
    {
        $result = $this->viewModel->build(
            [
                'estimate_id' => 10,
                'document_number' => 'OFF-202603-00077',
                'version_no' => 2,
                'status' => 'issued',
                'issued_at' => '2026-03-01 09:00:00',
                'currency' => 'SEK',
                'tax_mode' => 'business_reverse_charge',
                'reverse_charge_note' => 'RC applies',
                'vat_rate_percent' => 25,
                'total_inc_vat_minor' => 150000,
            ],
            [
                'header' => ['title' => 'Kitchen refresh', 'vat_rate_percent' => 25],
                'totals' => [
                    'labour_total_minor' => 90000,
                    'materials_total_minor' => 30000,
                    'subtotal_ex_vat_minor' => 120000,
                    'vat_minor' => 30000,
                    'total_inc_vat_minor' => 150000,
                ],
                'lines' => [[
                    'title' => 'Install cabinets',
                    'unit' => 'h',
                    'quantity' => 12,
                    'hours' => 12,
                    'labour_subtotal_minor' => 90000,
                    'internal_db_key' => 'ignore-this',
                ]],
                'material_lines' => [[
                    'name' => 'Cabinet package',
                    'unit' => 'pcs',
                    'quantity' => 2,
                    'subtotal_minor' => 30000,
                    'internal_db_key' => 'ignore-this-too',
                ]],
                'metadata' => [
                    'source_estimate_id' => 10,
                    'source_estimate_title' => 'Kitchen refresh',
                    'offert_version_no' => 2,
                    'document_number' => 'OFF-202603-00077',
                    'issued_at_utc' => '2026-03-01 09:00:00',
                ],
            ],
            [
                'project' => ['name' => 'Project A', 'code' => 'PA-10'],
                'property' => ['name' => 'Property A', 'address_line' => 'Main 1', 'city' => 'Stockholm', 'postal_code' => '11111'],
                'client' => ['name' => 'Client A', 'company_name' => 'Client A AB', 'org_number' => '556677-8899', 'vat_number' => 'SE556677889901', 'email' => 'a@example.com', 'phone' => '12345'],
                'document_profile' => ['company_name' => 'Issuer AB', 'offert_valid_days' => '20', 'offert_note' => 'Thanks'],
            ]
        );

        self::assertSame(['document', 'recipient', 'project_object', 'commercial_summary', 'labour_lines', 'material_lines', 'issuer', 'terms_acceptance', 'currency'], array_keys($result));
        self::assertSame('OFF-202603-00077', $result['document']['document_number']);
        self::assertSame('2', $result['document']['version_no']);
        self::assertSame('issued', $result['document']['status']);
        self::assertSame('SEK', $result['document']['currency']);
        self::assertSame('business_reverse_charge', $result['document']['tax_mode']);
        self::assertSame('2026-03-21', $result['document']['offert_valid_until']);

        self::assertSame('Client A', $result['recipient']['client_name']);
        self::assertSame('Project A', $result['project_object']['project_name']);
        self::assertSame('Property A', $result['project_object']['property_name']);
        self::assertSame('Kitchen refresh', $result['project_object']['source_estimate_title']);

        self::assertSame('90000', $result['commercial_summary']['labour_total']);
        self::assertSame('30000', $result['commercial_summary']['materials_total']);
        self::assertSame('150000', $result['commercial_summary']['total_inc_vat']);

        self::assertSame([
            'title' => 'Install cabinets',
            'unit' => 'h',
            'quantity' => '12',
            'hours' => '12',
            'subtotal_minor' => '90000',
        ], $result['labour_lines'][0]);
        self::assertArrayNotHasKey('internal_db_key', $result['labour_lines'][0]);

        self::assertSame([
            'name' => 'Cabinet package',
            'unit' => 'pcs',
            'quantity' => '2',
            'subtotal_minor' => '30000',
        ], $result['material_lines'][0]);
        self::assertArrayNotHasKey('internal_db_key', $result['material_lines'][0]);

        self::assertSame('Issuer AB', $result['issuer']['company_name']);
        self::assertSame('Thanks', $result['terms_acceptance']['offert_note']);
        self::assertSame('2026-03-21', $result['terms_acceptance']['offert_valid_until']);
        self::assertSame('SEK', $result['currency']);
    }

    public function testBuildNormalizesMissingSectionsSafely(): void
    {
        $result = $this->viewModel->build(
            ['estimate_id' => 10],
            ['header' => [], 'totals' => [], 'lines' => [null], 'material_lines' => ['invalid'], 'metadata' => []],
            ['project' => ['name' => 'Project A'], 'document_profile' => ['offert_valid_days' => ['invalid']]]
        );

        self::assertSame('', $result['document']['document_number']);
        self::assertSame('', $result['document']['offert_valid_until']);
        self::assertSame('Project A', $result['project_object']['project_name']);
        self::assertSame([], $result['labour_lines']);
        self::assertSame([], $result['material_lines']);
        self::assertSame('', $result['recipient']['client_name']);
    }
}
