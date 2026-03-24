<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\ProjectDossierBuilder;
use Trenor\Core\Domain\Service\InvoicePaymentSummaryCalculator;

final class ProjectDossierBuilderTest extends TestCase
{
    private ProjectDossierBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProjectDossierBuilder(new InvoicePaymentSummaryCalculator());
    }

    public function testBuildsLinkedChainForSingleProjectAndExcludesUnrelatedRows(): void
    {
        $dossier = $this->builder->build(
            ['id' => 10, 'name' => 'P1', 'code' => 'PR-10', 'property_id' => 200],
            ['id' => 200, 'name' => 'Prop1', 'client_id' => 300],
            ['id' => 300, 'name' => 'Client1'],
            [
                ['id' => 1001, 'project_id' => 10, 'title' => 'E1'],
                ['id' => 1002, 'project_id' => 10, 'title' => 'E2'],
            ],
            [
                ['id' => 2001, 'estimate_id' => 1001, 'document_number' => 'OFF-1'],
                ['id' => 2002, 'estimate_id' => 9999, 'document_number' => 'OFF-X'],
            ],
            [
                ['id' => 3001, 'offert_id' => 2001, 'estimate_id' => 1001, 'status' => 'issued', 'total_inc_vat_minor' => 10000],
                ['id' => 3002, 'offert_id' => 0, 'estimate_id' => 1002, 'status' => 'issued', 'total_inc_vat_minor' => 8000],
                ['id' => 3003, 'offert_id' => 2002, 'estimate_id' => 9999, 'status' => 'issued', 'total_inc_vat_minor' => 9000],
            ],
            [
                3001 => [
                    ['id' => 1, 'invoice_id' => 3001, 'amount_minor' => 4000],
                    ['id' => 2, 'invoice_id' => 3001, 'amount_minor' => 1000],
                ],
                3002 => [
                    ['id' => 3, 'invoice_id' => 3002, 'amount_minor' => 8000],
                ],
                3003 => [
                    ['id' => 4, 'invoice_id' => 3003, 'amount_minor' => 500],
                ],
            ],
            [
                ['id' => 9001, 'project_id' => 10, 'document_number' => 'ATA-1'],
                ['id' => 9002, 'project_id' => 77, 'document_number' => 'ATA-X'],
            ]
        );

        self::assertCount(2, $dossier['estimates']);
        self::assertCount(1, $dossier['offerts']);
        self::assertCount(2, $dossier['invoices']);
        self::assertCount(3, $dossier['payments']);
        self::assertCount(1, $dossier['atas']);
        self::assertSame(1, $dossier['summary']['atas_count']);

        self::assertSame(3001, $dossier['invoices'][0]['id']);
        self::assertSame(3002, $dossier['invoices'][1]['id']);
    }

    public function testHandlesMissingPropertyAndClientSafely(): void
    {
        $dossier = $this->builder->build(
            ['id' => 10, 'name' => 'P1', 'code' => 'PR-10', 'property_id' => 0],
            [],
            [],
            [],
            [],
            [],
            [],
            []
        );

        self::assertSame([], $dossier['property']);
        self::assertSame([], $dossier['client']);
        self::assertSame(0, $dossier['summary']['invoices_count']);
        self::assertSame(0, $dossier['summary']['payments_count']);
    }

    public function testSummaryTotalsAndComputedInvoiceFieldsUsePaymentSummary(): void
    {
        $dossier = $this->builder->build(
            ['id' => 10],
            [],
            [],
            [
                ['id' => 1001],
            ],
            [
                ['id' => 2001, 'estimate_id' => 1001],
            ],
            [
                ['id' => 3001, 'offert_id' => 2001, 'estimate_id' => 1001, 'status' => 'issued', 'total_inc_vat_minor' => 10000],
                ['id' => 3002, 'offert_id' => 2001, 'estimate_id' => 1001, 'status' => 'archived', 'total_inc_vat_minor' => 5000],
            ],
            [
                3001 => [
                    ['invoice_id' => 3001, 'amount_minor' => 6000],
                ],
                3002 => [
                    ['invoice_id' => 3002, 'amount_minor' => 5000],
                ],
            ],
            []
        );

        self::assertSame(15000, $dossier['summary']['invoiced_total_minor']);
        self::assertSame(11000, $dossier['summary']['paid_total_minor']);
        self::assertSame(4000, $dossier['summary']['outstanding_total_minor']);
        self::assertSame(1, $dossier['summary']['fully_paid_invoices_count']);
        self::assertSame(1, $dossier['summary']['partially_paid_invoices_count']);
        self::assertSame(1, $dossier['summary']['archived_invoices_count']);

        self::assertSame(6000, $dossier['invoices'][0]['paid_total_minor']);
        self::assertSame(4000, $dossier['invoices'][0]['outstanding_minor']);
        self::assertSame('partially_paid', $dossier['invoices'][0]['computed_status']);
        self::assertSame(1, $dossier['invoices'][0]['payment_count']);

        self::assertSame(5000, $dossier['invoices'][1]['paid_total_minor']);
        self::assertSame(0, $dossier['invoices'][1]['outstanding_minor']);
        self::assertSame('paid', $dossier['invoices'][1]['computed_status']);
    }
}
