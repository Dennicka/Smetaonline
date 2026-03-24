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

    public function testBuildIncludesIssuerRecipientAndProjectBlocks(): void
    {
        $result = $this->viewModel->build(
            ['estimate_id' => 55, 'issued_at' => '2026-03-10 10:00:00'],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            [
                'project' => ['name' => 'Project A', 'code' => 'PA-1'],
                'property' => ['name' => 'Property A', 'address_line' => 'Main 1', 'city' => 'Stockholm', 'postal_code' => '11111'],
                'client' => ['name' => 'Client A', 'org_number' => '555', 'email' => 'a@example.com', 'phone' => '123'],
                'document_profile' => ['company_name' => 'Issuer AB'],
            ]
        );

        self::assertSame('Issuer AB', $result['issuer']['company_name']);
        self::assertSame('Client A', $result['recipient']['client_name']);
        self::assertSame('Project A', $result['project_object']['project_name']);
        self::assertSame('Property A', $result['project_object']['property_name']);
    }

    public function testBuildDerivesPaymentDueDateFromIssuedAtAndTermsDays(): void
    {
        $result = $this->viewModel->build(
            ['issued_at' => '2026-03-10 10:00:00'],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            ['document_profile' => ['payment_terms_days' => '15']]
        );

        self::assertSame('2026-03-25', $result['document']['payment_due_date']);
        self::assertSame('2026-03-25', $result['payment_terms']['payment_due_date']);
    }

    public function testBuildPaymentTermsIncludePaymentInstructions(): void
    {
        $result = $this->viewModel->build(
            [],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            ['document_profile' => [
                'invoice_note' => 'Pay on time',
                'payment_terms_days' => '30',
                'bankgiro' => '111-2222',
                'plusgiro' => '333-4444',
                'swish' => '123123123',
                'iban' => 'SE111',
                'bic' => 'NDEASESS',
            ]]
        );

        self::assertSame('Pay on time', $result['payment_terms']['invoice_note']);
        self::assertSame('30', $result['payment_terms']['payment_terms_days']);
        self::assertSame('111-2222', $result['payment_terms']['bankgiro']);
        self::assertSame('SE111', $result['payment_terms']['iban']);
    }

    public function testBuildKeepsPaymentSummaryFieldsStable(): void
    {
        $result = $this->viewModel->build(
            ['total_inc_vat_minor' => 13000],
            [
                'header' => [],
                'totals' => [
                    'labour_total_minor' => 7000,
                    'materials_total_minor' => 3000,
                    'subtotal_ex_vat_minor' => 10000,
                    'vat_minor' => 3000,
                    'total_inc_vat_minor' => 13000,
                ],
                'lines' => [],
                'material_lines' => [],
                'metadata' => [],
            ],
            ['payment_summary' => [
                'invoice_total_minor' => 13000,
                'paid_total_minor' => 3000,
                'outstanding_minor' => 10000,
                'payment_count' => 1,
                'computed_status' => 'partially_paid',
            ]]
        );

        self::assertSame('3000', $result['invoice_summary']['paid_total']);
        self::assertSame('10000', $result['invoice_summary']['outstanding']);
        self::assertSame('partially_paid', $result['invoice_summary']['computed_status']);
        self::assertSame('13000', $result['payment_summary']['invoice_total_minor']);
    }

    public function testBuildNormalizesMissingInputsSafely(): void
    {
        $result = $this->viewModel->build(
            ['id' => 5],
            ['header' => [], 'totals' => [], 'lines' => ['invalid'], 'material_lines' => [null], 'metadata' => []],
            ['payment_summary' => [], 'document_profile' => ['payment_terms_days' => ['invalid']]]
        );

        self::assertSame('', $result['document']['document_number']);
        self::assertSame('', $result['document']['payment_due_date']);
        self::assertSame([], $result['labour_lines']);
        self::assertSame([], $result['material_lines']);
        self::assertSame('', $result['recipient']['client_name']);
        self::assertSame('', $result['payment_terms']['payment_terms_days']);
    }
}
