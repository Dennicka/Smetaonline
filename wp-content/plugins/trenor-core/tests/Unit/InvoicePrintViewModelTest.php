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

    public function testBuildReturnsStablePrintableStructureIncludingPayments(): void
    {
        $result = $this->viewModel->build(
            [
                'offert_id' => 17,
                'estimate_id' => 44,
                'document_number' => 'INV-202603-00031',
                'version_no' => 3,
                'status' => 'partially_paid',
                'issued_at' => '2026-03-10 10:00:00',
                'currency' => 'SEK',
                'tax_mode' => 'business_standard_vat',
                'total_inc_vat_minor' => 13000,
            ],
            [
                'header' => ['title' => 'Kitchen refresh'],
                'totals' => [
                    'labour_total_minor' => 7000,
                    'materials_total_minor' => 3000,
                    'subtotal_ex_vat_minor' => 10000,
                    'vat_minor' => 3000,
                    'tax_mode' => 'business_standard_vat',
                'total_inc_vat_minor' => 13000,
                ],
                'lines' => [[
                    'title' => 'Labour item',
                    'unit' => 'h',
                    'quantity' => 5,
                    'hours' => 5,
                    'labour_subtotal_minor' => 7000,
                    'internal_key' => 'ignore',
                ]],
                'material_lines' => [[
                    'name' => 'Material item',
                    'unit' => 'pcs',
                    'quantity' => 1,
                    'subtotal_minor' => 3000,
                    'internal_key' => 'ignore',
                ]],
                'metadata' => [
                    'source_offert_id' => 17,
                    'source_estimate_id' => 44,
                    'source_estimate_title' => 'Kitchen refresh',
                    'invoice_version_no' => 3,
                    'document_number' => 'INV-202603-00031',
                    'issued_at_utc' => '2026-03-10 10:00:00',
                ],
            ],
            [
                'project' => ['name' => 'Project A', 'code' => 'PA-1'],
                'property' => ['name' => 'Property A', 'address_line' => 'Main 1', 'city' => 'Stockholm', 'postal_code' => '11111'],
                'client' => ['name' => 'Client A', 'org_number' => '555', 'email' => 'a@example.com', 'phone' => '123'],
                'document_profile' => ['company_name' => 'Issuer AB', 'payment_terms_days' => '15', 'invoice_note' => 'Pay on time'],
                'payment_summary' => [
                    'invoice_total_minor' => 13000,
                    'paid_total_minor' => 3000,
                    'outstanding_minor' => 10000,
                    'payment_count' => 1,
                    'computed_status' => 'partially_paid',
                ],
                'payments' => [[
                    'payment_date' => '2026-03-20 11:00:00',
                    'amount_minor' => 3000,
                    'currency' => 'SEK',
                    'method' => 'manual',
                    'reference' => 'BANK-1',
                    'note' => 'part payment',
                    'internal_key' => 'ignore',
                ]],
            ]
        );

        self::assertSame(['document', 'recipient', 'project_object', 'invoice_summary', 'labour_lines', 'material_lines', 'payment_summary', 'payments', 'issuer', 'payment_terms', 'currency'], array_keys($result));

        self::assertSame('INV-202603-00031', $result['document']['document_number']);
        self::assertSame('business_standard_vat', $result['document']['tax_mode']);
        self::assertSame('3', $result['document']['version_no']);
        self::assertSame('partially_paid', $result['document']['status']);
        self::assertSame('2026-03-25', $result['document']['payment_due_date']);

        self::assertSame('Client A', $result['recipient']['client_name']);
        self::assertSame('Project A', $result['project_object']['project_name']);
        self::assertSame('Kitchen refresh', $result['project_object']['source_estimate_title']);

        self::assertSame('7000', $result['invoice_summary']['labour_total']);
        self::assertSame('3000', $result['invoice_summary']['materials_total']);
        self::assertSame('3000', $result['invoice_summary']['paid_total']);
        self::assertSame('10000', $result['invoice_summary']['outstanding']);
        self::assertSame('partially_paid', $result['invoice_summary']['computed_status']);

        self::assertSame([
            'title' => 'Labour item',
            'unit' => 'h',
            'quantity' => '5',
            'hours' => '5',
            'subtotal_minor' => '7000',
        ], $result['labour_lines'][0]);
        self::assertArrayNotHasKey('internal_key', $result['labour_lines'][0]);

        self::assertSame([
            'name' => 'Material item',
            'unit' => 'pcs',
            'quantity' => '1',
            'subtotal_minor' => '3000',
        ], $result['material_lines'][0]);
        self::assertArrayNotHasKey('internal_key', $result['material_lines'][0]);

        self::assertSame('13000', $result['payment_summary']['invoice_total_minor']);
        self::assertSame('1', $result['payment_summary']['payment_count']);

        self::assertSame([
            'payment_date' => '2026-03-20 11:00:00',
            'amount_minor' => '3000',
            'currency' => 'SEK',
            'method' => 'manual',
            'reference' => 'BANK-1',
            'note' => 'part payment',
        ], $result['payments'][0]);
        self::assertArrayNotHasKey('internal_key', $result['payments'][0]);

        self::assertSame('Issuer AB', $result['issuer']['company_name']);
        self::assertSame('Pay on time', $result['payment_terms']['invoice_note']);
        self::assertSame('15', $result['payment_terms']['payment_terms_days']);
    }

    public function testBuildNormalizesMissingSectionsSafely(): void
    {
        $result = $this->viewModel->build(
            ['id' => 5],
            ['header' => [], 'totals' => [], 'lines' => ['invalid'], 'material_lines' => [null], 'metadata' => []],
            ['payment_summary' => [], 'payments' => 'invalid', 'document_profile' => ['payment_terms_days' => ['invalid']]]
        );

        self::assertSame('', $result['document']['document_number']);
        self::assertSame('', $result['document']['payment_due_date']);
        self::assertSame([], $result['labour_lines']);
        self::assertSame([], $result['material_lines']);
        self::assertSame('', $result['recipient']['client_name']);
        self::assertSame('', $result['payment_terms']['payment_terms_days']);
        self::assertSame([], $result['payments']);
    }
}
