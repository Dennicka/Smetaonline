<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Trenor\Core\Domain\Service\DocumentNumberGenerator;
use Trenor\Core\Domain\Service\InvoiceFromOffertService;
use Trenor\Core\Domain\Service\InvoiceVersionProvider;

final class InvoiceFromOffertServiceTest extends TestCase
{
    public function testAcceptedOffertBuildsInvoicePayloadAndFreezesSnapshot(): void
    {
        $versionProvider = new class () implements InvoiceVersionProvider {
            public int $receivedOffertId = 0;

            public function nextVersionNo(int $offertId): int
            {
                $this->receivedOffertId = $offertId;

                return 3;
            }
        };

        $documentNumbers = new class () implements DocumentNumberGenerator {
            public string $receivedDocType = '';

            public function next(string $docType, ?DateTimeImmutable $date = null): string
            {
                $this->receivedDocType = $docType;

                return 'INV-202603-00031';
            }
        };

        $service = new InvoiceFromOffertService($versionProvider, $documentNumbers);

        $offert = ['id' => 17, 'estimate_id' => 44, 'status' => 'accepted', 'document_number' => 'OFF-202603-00077', 'currency' => 'SEK', 'tax_mode' => 'business_standard_vat', 'client_company_name' => 'Client AB', 'client_org_number' => '556677-8899', 'client_vat_number' => 'SE556677889901', 'vat_rate_percent' => 25.0];
        $snapshot = [
            'header' => ['id' => 44, 'title' => 'Kitchen refresh'],
            'totals' => ['labour_total_minor' => 90000, 'materials_total_minor' => 30000, 'subtotal_ex_vat_minor' => 120000, 'vat_minor' => 30000, 'total_inc_vat_minor' => 150000],
            'lines' => [['id' => 1, 'quantity' => 1.5, 'labour_subtotal_minor' => 90000]],
            'material_lines' => [['id' => 7, 'quantity' => 2.25, 'subtotal_minor' => 30000]],
            'metadata' => [],
            'rot' => [
                'rot_requested' => true,
                'housing_type' => 'smahus',
                'rot_eligibility_status' => 'preliminary_approved',
                'rot_eligible_labour_minor' => 90000,
                'preliminary_rot_minor' => 27000,
                'amount_after_preliminary_rot_minor' => 123000,
                'rot_buyer_count' => 1,
                'rot_buyers' => [['personal_identity' => '19800101-1234', 'share_percent' => 100]],
                'rot_allocation' => [['personal_identity' => '19800101-1234', 'allocated_rot_minor' => 27000]],
                'rot_property_reference' => 'FAST-123',
            ],
        ];

        $payload = $service->buildPayload($offert, $snapshot, new DateTimeImmutable('2026-03-11 07:15:00'));

        $snapshot['header']['title'] = 'Changed later';
        $snapshot['lines'][0]['quantity'] = 99.0;
        $snapshot['material_lines'][0]['quantity'] = 88.0;
        $snapshot['totals']['vat_minor'] = 0;

        self::assertSame(17, $payload['offert_id']);
        self::assertSame(44, $payload['estimate_id']);
        self::assertSame('INV-202603-00031', $payload['document_number']);
        self::assertSame(3, $payload['version_no']);
        self::assertSame('issued', $payload['status']);
        self::assertSame(90000, $payload['labour_total_minor']);
        self::assertSame(30000, $payload['materials_total_minor']);
        self::assertSame(120000, $payload['subtotal_ex_vat_minor']);
        self::assertSame(30000, $payload['vat_minor']);
        self::assertSame(150000, $payload['total_inc_vat_minor']);
        self::assertSame(27000, $payload['preliminary_rot_minor']);
        self::assertSame(123000, $payload['total_after_preliminary_rot_minor']);
        self::assertSame('business_standard_vat', $payload['tax_mode']);
        self::assertSame('Client AB', $payload['client_company_name']);

        self::assertSame(17, $versionProvider->receivedOffertId);
        self::assertSame('inv', $documentNumbers->receivedDocType);

        $frozen = json_decode((string) $payload['snapshot_json'], true);
        self::assertIsArray($frozen);
        self::assertSame('Kitchen refresh', $frozen['header']['title']);
        self::assertSame(1.5, $frozen['lines'][0]['quantity']);
        self::assertSame(2.25, $frozen['material_lines'][0]['quantity']);
        self::assertSame(30000, $frozen['totals']['vat_minor']);
        self::assertSame(27000, $frozen['rot']['preliminary_rot_minor']);

        self::assertSame(17, $frozen['metadata']['source_offert_id']);
        self::assertSame('OFF-202603-00077', $frozen['metadata']['source_offert_document_number']);
        self::assertSame(44, $frozen['metadata']['source_estimate_id']);
        self::assertSame(3, $frozen['metadata']['invoice_version_no']);
        self::assertSame('INV-202603-00031', $frozen['metadata']['document_number']);
    }

    public function testNonAcceptedOffertIsRejected(): void
    {
        $versionProvider = new class () implements InvoiceVersionProvider {
            public function nextVersionNo(int $offertId): int
            {
                return 1;
            }
        };

        $documentNumbers = new class () implements DocumentNumberGenerator {
            public function next(string $docType, ?DateTimeImmutable $date = null): string
            {
                return 'INV-202603-00001';
            }
        };

        $service = new InvoiceFromOffertService($versionProvider, $documentNumbers);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invoice can be issued only from accepted offert.');

        $service->buildPayload(
            ['id' => 99, 'estimate_id' => 1, 'status' => 'issued'],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []]
        );
    }
}
