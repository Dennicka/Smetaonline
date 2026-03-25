<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\BusinessDocumentPresentationBuilder;

final class BusinessDocumentPresentationBuilderTest extends TestCase
{
    private BusinessDocumentPresentationBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new BusinessDocumentPresentationBuilder();
    }

    public function testBuildsOffertBusinessPresentationWithRotAndReferences(): void
    {
        $presentation = $this->builder->build('offert', [
            'document_number' => 'OFF-2026-100',
            'version_no' => 2,
            'status' => 'issued',
            'issued_at' => '2026-03-24 10:00:00',
            'currency' => 'SEK',
            'tax_mode' => 'private_consumer',
            'estimate_id' => 51,
            'total_inc_vat_minor' => 125000,
        ], [
            'header' => ['rot_property_reference' => 'BRF-101'],
            'totals' => [
                'subtotal_ex_vat_minor' => 100000,
                'vat_minor' => 25000,
                'total_inc_vat_minor' => 125000,
                'rot_eligible_labour_minor' => 40000,
                'preliminary_rot_minor' => 12000,
                'amount_before_rot_minor' => 125000,
                'amount_after_preliminary_rot_minor' => 113000,
            ],
            'metadata' => ['source_estimate_title' => 'Renovation package'],
        ], [
            'company_name' => 'Seller AB',
            'org_number' => '556677-8899',
        ]);

        self::assertSame('Offert / Commercial Proposal', $presentation['title']);
        self::assertNotEmpty($presentation['seller']);
        self::assertNotEmpty($presentation['references']);
        self::assertNotEmpty($presentation['totals']);
        self::assertStringContainsString('ROT', json_encode($presentation['tax_notes']));
    }

    public function testBuildsInvoiceReverseChargeTaxNote(): void
    {
        $presentation = $this->builder->build('invoice', [
            'document_number' => 'INV-2026-100',
            'version_no' => 1,
            'status' => 'issued',
            'issued_at' => '2026-03-24 10:00:00',
            'currency' => 'SEK',
            'tax_mode' => 'business_reverse_charge',
            'reverse_charge_note' => 'Omvänd betalningsskyldighet gäller.',
            'total_inc_vat_minor' => 100000,
        ], [
            'totals' => ['vat_minor' => 0, 'total_inc_vat_minor' => 100000],
        ]);

        self::assertSame('Invoice', $presentation['title']);
        self::assertStringContainsString('reverse charge', strtolower(json_encode($presentation['tax_notes']) ?: ''));
    }

    public function testBuildsDocumentSpecificTitlesForAllBusinessDocumentTypes(): void
    {
        foreach (['avtal', 'reminder', 'credit_note'] as $type) {
            $presentation = $this->builder->build($type, [
                'document_number' => strtoupper($type) . '-1',
                'version_no' => 1,
                'status' => 'issued',
                'issued_at' => '2026-03-24 10:00:00',
                'currency' => 'SEK',
                'tax_mode' => 'private_consumer',
                'total_inc_vat_minor' => 1000,
            ], [
                'totals' => ['vat_minor' => 200, 'total_inc_vat_minor' => 1000],
            ]);

            self::assertNotSame('', $presentation['title']);
            self::assertNotEmpty($presentation['identity']);
            self::assertNotEmpty($presentation['totals']);
        }
    }
}
