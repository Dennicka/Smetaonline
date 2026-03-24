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
        $versionProvider = new class implements InvoiceVersionProvider {
            public function nextVersionNo(int $offertId): int
            {
                return 3;
            }
        };

        $documentNumbers = new class implements DocumentNumberGenerator {
            public function next(string $docType, ?DateTimeImmutable $date = null): string
            {
                return 'INV-202603-00031';
            }
        };

        $service = new InvoiceFromOffertService($versionProvider, $documentNumbers);

        $offert = ['id' => 17, 'estimate_id' => 44, 'status' => 'accepted', 'document_number' => 'OFF-202603-00077', 'currency' => 'SEK', 'vat_rate_percent' => 25.0];
        $snapshot = [
            'header' => ['id' => 44, 'title' => 'Kitchen refresh'],
            'totals' => ['labour_total_minor' => 90000, 'materials_total_minor' => 30000, 'subtotal_ex_vat_minor' => 120000, 'vat_minor' => 30000, 'total_inc_vat_minor' => 150000],
            'lines' => [['id' => 1, 'quantity' => 1.5, 'labour_subtotal_minor' => 90000]],
            'material_lines' => [['id' => 7, 'quantity' => 2.25, 'subtotal_minor' => 30000]],
            'metadata' => [],
        ];

        $payload = $service->buildPayload($offert, $snapshot, new DateTimeImmutable('2026-03-11 07:15:00'));

        $snapshot['header']['title'] = 'Changed later';
        $snapshot['lines'][0]['quantity'] = 99.0;
        $snapshot['material_lines'][0]['quantity'] = 88.0;

        self::assertSame('INV-202603-00031', $payload['document_number']);
        self::assertSame(3, $payload['version_no']);

        $frozen = json_decode((string) $payload['snapshot_json'], true);
        self::assertIsArray($frozen);
        self::assertSame('Kitchen refresh', $frozen['header']['title']);
        self::assertSame(1.5, $frozen['lines'][0]['quantity']);
        self::assertSame(2.25, $frozen['material_lines'][0]['quantity']);

        self::assertSame(17, $frozen['metadata']['source_offert_id']);
        self::assertSame('OFF-202603-00077', $frozen['metadata']['source_offert_document_number']);
        self::assertSame(44, $frozen['metadata']['source_estimate_id']);
        self::assertSame(3, $frozen['metadata']['invoice_version_no']);
        self::assertSame('INV-202603-00031', $frozen['metadata']['document_number']);
    }

    public function testNonAcceptedOffertIsRejected(): void
    {
        $versionProvider = new class implements InvoiceVersionProvider {
            public function nextVersionNo(int $offertId): int
            {
                return 1;
            }
        };

        $documentNumbers = new class implements DocumentNumberGenerator {
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
