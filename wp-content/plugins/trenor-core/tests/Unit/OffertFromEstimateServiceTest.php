<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\DocumentNumberGenerator;
use Trenor\Core\Domain\Service\OffertFromEstimateService;
use Trenor\Core\Domain\Service\OffertVersionProvider;

final class OffertFromEstimateServiceTest extends TestCase
{
    public function testBuildPayloadCreatesIssuedPayloadAndImmutableSnapshot(): void
    {
        $versionProvider = new class () implements OffertVersionProvider {
            public int $receivedEstimateId = 0;

            public function nextVersionNo(int $estimateId): int
            {
                $this->receivedEstimateId = $estimateId;

                return 2;
            }
        };

        $documentNumbers = new class () implements DocumentNumberGenerator {
            public string $receivedDocType = '';

            public function next(string $docType, ?DateTimeImmutable $date = null): string
            {
                $this->receivedDocType = $docType;

                return 'OFF-202603-00077';
            }
        };

        $service = new OffertFromEstimateService($versionProvider, $documentNumbers);

        $header = ['id' => 44, 'title' => 'Kitchen refresh', 'currency' => 'SEK', 'tax_mode' => 'business_reverse_charge', 'reverse_charge_note' => 'RC applies', 'client_company_name' => 'Client AB', 'client_org_number' => '556677-8899', 'client_vat_number' => 'SE556677889901', 'vat_rate_percent' => 25.0];
        $lines = [['id' => 1, 'quantity' => 1.5, 'labour_subtotal_minor' => 90000]];
        $materials = [['id' => 7, 'quantity' => 2.25, 'subtotal_minor' => 30000]];
        $totals = ['labour_total_minor' => 90000, 'materials_total_minor' => 30000, 'subtotal_ex_vat_minor' => 120000, 'vat_minor' => 30000, 'total_inc_vat_minor' => 150000];

        $rotSummary = [
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
        ];

        $payload = $service->buildPayload($header, $lines, $materials, $totals, new DateTimeImmutable('2026-03-10 09:30:00'), $rotSummary);

        $header['title'] = 'Changed later';
        $lines[0]['quantity'] = 99.0;
        $materials[0]['quantity'] = 99.0;
        $totals['total_inc_vat_minor'] = 0;

        self::assertSame(44, $payload['estimate_id']);
        self::assertSame('OFF-202603-00077', $payload['document_number']);
        self::assertSame(2, $payload['version_no']);
        self::assertSame('issued', $payload['status']);
        self::assertSame(90000, $payload['labour_total_minor']);
        self::assertSame(30000, $payload['materials_total_minor']);
        self::assertSame(120000, $payload['subtotal_ex_vat_minor']);
        self::assertSame(30000, $payload['vat_minor']);
        self::assertSame(150000, $payload['total_inc_vat_minor']);
        self::assertSame(27000, $payload['preliminary_rot_minor']);
        self::assertSame(123000, $payload['total_after_preliminary_rot_minor']);
        self::assertSame('business_reverse_charge', $payload['tax_mode']);
        self::assertSame('Client AB', $payload['client_company_name']);

        self::assertSame(44, $versionProvider->receivedEstimateId);
        self::assertSame('off', $documentNumbers->receivedDocType);

        $snapshot = json_decode((string) $payload['snapshot_json'], true);
        self::assertIsArray($snapshot);
        self::assertSame('Kitchen refresh', $snapshot['header']['title']);
        self::assertSame(1.5, $snapshot['lines'][0]['quantity']);
        self::assertSame(2.25, $snapshot['material_lines'][0]['quantity']);
        self::assertSame(150000, $snapshot['totals']['total_inc_vat_minor']);
        self::assertSame(27000, $snapshot['rot']['preliminary_rot_minor']);
        self::assertSame(44, $snapshot['metadata']['source_estimate_id']);
        self::assertSame(2, $snapshot['metadata']['offert_version_no']);
        self::assertSame((string) $payload['document_number'], $snapshot['metadata']['document_number']);
    }
}
