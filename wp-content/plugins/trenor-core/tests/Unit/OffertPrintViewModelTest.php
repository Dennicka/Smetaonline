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

    public function testBuildCreatesStableSectionsFromOffertAndSnapshot(): void
    {
        $offert = [
            'id' => 17,
            'estimate_id' => 55,
            'document_number' => 'OF-2026-001',
            'version_no' => 2,
            'status' => 'issued',
            'issued_at' => '2026-02-14 13:00:00',
            'currency' => 'SEK',
            'vat_rate_percent' => '25.00',
            'total_inc_vat_minor' => 13000,
        ];
        $snapshot = [
            'header' => ['title' => 'Kitchen renovation', 'currency' => 'SEK'],
            'totals' => [
                'labour_total_minor' => 7000,
                'materials_total_minor' => 3000,
                'subtotal_ex_vat_minor' => 10000,
                'vat_minor' => 3000,
                'total_inc_vat_minor' => 13000,
            ],
            'lines' => [['title' => 'Paint walls', 'unit' => 'm2', 'quantity' => 10, 'hours' => 8, 'labour_subtotal_minor' => 7000]],
            'material_lines' => [['name' => 'Paint', 'unit' => 'bucket', 'quantity' => 2, 'subtotal_minor' => 3000]],
            'metadata' => ['source_estimate_id' => 55, 'source_estimate_title' => 'Kitchen renovation'],
        ];

        $result = $this->viewModel->build($offert, $snapshot);

        self::assertSame('OF-2026-001', $result['document']['document_number']);
        self::assertSame('2', $result['document']['version_no']);
        self::assertSame('Kitchen renovation', $result['context']['source_estimate_title']);
        self::assertSame('7000', $result['totals'][0]['minor']);
        self::assertSame('Paint walls', $result['labour_lines'][0]['title']);
        self::assertSame('Paint', $result['material_lines'][0]['name']);
        self::assertSame('', $result['recipient']['client_name']);
        self::assertSame('', $result['project_object']['project_name']);
    }

    public function testBuildIncludesRecipientAndProjectObjectSectionsWhenContextDataExists(): void
    {
        $result = $this->viewModel->build(
            ['estimate_id' => 10],
            [
                'header' => [],
                'totals' => [],
                'lines' => [],
                'material_lines' => [],
                'metadata' => [],
            ],
            [
                'estimate' => ['id' => 10],
                'project' => ['name' => 'Project A', 'code' => 'PA-10'],
                'property' => [
                    'name' => 'Property A',
                    'address_line' => 'Main 1',
                    'city' => 'Stockholm',
                    'postal_code' => '11111',
                ],
                'client' => ['name' => 'Client A', 'org_number' => '556677-8899', 'email' => 'a@example.com', 'phone' => '12345'],
            ]
        );

        self::assertSame('Client A', $result['recipient']['client_name']);
        self::assertSame('556677-8899', $result['recipient']['client_org_number']);
        self::assertSame('a@example.com', $result['recipient']['client_email']);
        self::assertSame('12345', $result['recipient']['client_phone']);
        self::assertSame('Project A', $result['project_object']['project_name']);
        self::assertSame('PA-10', $result['project_object']['project_code']);
        self::assertSame('Property A', $result['project_object']['property_name']);
        self::assertSame('Main 1', $result['project_object']['property_address']);
        self::assertSame('Stockholm', $result['project_object']['property_city']);
        self::assertSame('11111', $result['project_object']['property_postal_code']);
    }

    public function testBuildNormalizesMissingSectionsSafely(): void
    {
        $result = $this->viewModel->build(
            ['estimate_id' => 10],
            [
                'header' => [],
                'totals' => [],
                'lines' => [],
                'material_lines' => [],
                'metadata' => [],
            ],
            [
                'estimate' => ['id' => 10],
                'project' => ['name' => 'Project A'],
                'property' => [],
                'client' => [],
            ]
        );

        self::assertSame('', $result['document']['document_number']);
        self::assertSame('10', $result['context']['source_estimate_id']);
        self::assertSame('Project A', $result['context']['project_name']);
        self::assertCount(5, $result['totals']);
        self::assertSame([], $result['labour_lines']);
        self::assertSame([], $result['material_lines']);
        self::assertSame('', $result['recipient']['client_name']);
        self::assertSame('', $result['project_object']['property_postal_code']);
        self::assertSame('', $result['commercial_summary']['source_estimate_title']);
    }

    public function testBuildPrefersBestAvailableCandidateFields(): void
    {
        $result = $this->viewModel->build(
            ['estimate_id' => 77, 'currency' => 'SEK'],
            [
                'header' => ['title' => 'Header title'],
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
                'estimate' => ['id' => 77, 'title' => 'Estimate title'],
            ]
        );

        self::assertSame('Header title', $result['context']['source_estimate_title']);
        self::assertSame('RU title', $result['labour_lines'][0]['title']);
        self::assertSame('SV material', $result['material_lines'][0]['name']);
    }

    public function testBuildIntegratesDocumentProfileWithFallbackAndPopulatedValues(): void
    {
        $emptyResult = $this->viewModel->build(
            ['estimate_id' => 1],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            ['document_profile' => []]
        );
        self::assertSame('', $emptyResult['issuer']['company_name']);
        self::assertSame('', $emptyResult['commercial_terms']['offert_note']);

        $result = $this->viewModel->build(
            ['estimate_id' => 1],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            ['document_profile' => [
                'company_name' => 'ACME AB',
                'vat_number' => 'SE123',
                'address_line' => 'Main street 1',
                'bankgiro' => '111-2222',
                'iban' => 'SE111',
                'offert_valid_days' => '20',
                'offert_note' => 'Thanks',
            ]]
        );

        self::assertSame('ACME AB', $result['issuer']['company_name']);
        self::assertSame('SE123', $result['issuer']['vat_number']);
        self::assertSame('Main street 1', $result['issuer']['address_line']);
        self::assertSame('111-2222', $result['issuer']['bankgiro']);
        self::assertSame('SE111', $result['issuer']['iban']);
        self::assertSame('20', $result['commercial_terms']['offert_valid_days']);
        self::assertSame('Thanks', $result['commercial_terms']['offert_note']);
    }

    public function testBuildNormalizesMissingDocumentProfileFieldsToScalarStrings(): void
    {
        $result = $this->viewModel->build(
            ['estimate_id' => 1],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            ['document_profile' => [
                'company_name' => ['invalid'],
                'vat_number' => 'SE555',
                'address_line' => 'Street 5',
                'bic' => 'NDEASESS',
                'offert_valid_days' => ['invalid'],
                'offert_note' => null,
            ]]
        );

        self::assertSame('', $result['issuer']['company_name']);
        self::assertSame('SE555', $result['issuer']['vat_number']);
        self::assertSame('Street 5', $result['issuer']['address_line']);
        self::assertSame('NDEASESS', $result['issuer']['bic']);
        self::assertSame('', $result['commercial_terms']['offert_valid_days']);
        self::assertSame('', $result['commercial_terms']['offert_note']);
    }
}
