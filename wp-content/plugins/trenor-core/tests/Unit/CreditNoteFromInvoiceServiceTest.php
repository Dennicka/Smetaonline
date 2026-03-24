<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Trenor\Core\Domain\Service\CreditNoteFromInvoiceService;
use Trenor\Core\Domain\Service\CreditNoteVersionProvider;
use Trenor\Core\Domain\Service\DocumentNumberGenerator;

final class CreditNoteFromInvoiceServiceTest extends TestCase
{
    public function testBuildsPayloadFromValidInvoiceAndSnapshot(): void
    {
        $versionProvider = new class implements CreditNoteVersionProvider {
            public function nextVersionNo(int $invoiceId): int
            {
                return 4;
            }
        };

        $documentNumberGenerator = new class implements DocumentNumberGenerator {
            public function next(string $docType, ?DateTimeImmutable $date = null): string
            {
                return 'CRN-202603-00041';
            }
        };

        $service = new CreditNoteFromInvoiceService($versionProvider, $documentNumberGenerator);

        $invoice = [
            'id' => 55,
            'offert_id' => 11,
            'estimate_id' => 9,
            'status' => 'issued',
            'document_number' => 'INV-202603-00088',
            'currency' => 'SEK',
            'vat_rate_percent' => 25.0,
            'snapshot_json' => json_encode([
                'header' => ['title' => 'Bathroom redo'],
                'totals' => ['labour_total_minor' => 1000, 'materials_total_minor' => 2000, 'subtotal_ex_vat_minor' => 3000, 'vat_minor' => 750, 'total_inc_vat_minor' => 3750],
                'lines' => [['id' => 1]],
                'material_lines' => [['id' => 2]],
                'metadata' => ['source_estimate_title' => 'Bathroom redo'],
            ]),
        ];

        $payload = $service->buildPayload($invoice, new DateTimeImmutable('2026-03-11 07:15:00'));

        self::assertSame(55, $payload['invoice_id']);
        self::assertSame(11, $payload['offert_id']);
        self::assertSame(9, $payload['estimate_id']);
        self::assertSame(3750, $payload['total_inc_vat_minor']);
    }

    public function testCreatesImmutableSnapshotMetadata(): void
    {
        $versionProvider = new class implements CreditNoteVersionProvider {
            public function nextVersionNo(int $invoiceId): int
            {
                return 2;
            }
        };

        $documentNumberGenerator = new class implements DocumentNumberGenerator {
            public function next(string $docType, ?DateTimeImmutable $date = null): string
            {
                return 'CRN-202603-00012';
            }
        };

        $service = new CreditNoteFromInvoiceService($versionProvider, $documentNumberGenerator);
        $invoice = [
            'id' => 2,
            'status' => 'issued',
            'document_number' => 'INV-202603-00010',
            'snapshot_json' => json_encode([
                'header' => ['title' => 'Initial'],
                'totals' => ['total_inc_vat_minor' => 100],
                'lines' => [['id' => 1, 'quantity' => 1.0]],
                'material_lines' => [['id' => 2, 'quantity' => 2.0]],
                'metadata' => [],
            ]),
        ];

        $payload = $service->buildPayload($invoice);
        $decoded = json_decode((string) $payload['snapshot_json'], true);

        $invoice['snapshot_json'] = json_encode(['header' => ['title' => 'Changed']]);

        self::assertSame('INV-202603-00010', $decoded['metadata']['source_invoice_document_number']);
        self::assertSame(2, $decoded['metadata']['source_invoice_id']);
        self::assertSame('CRN-202603-00012', $decoded['metadata']['document_number']);
        self::assertSame(1.0, $decoded['lines'][0]['quantity']);
    }

    public function testVersionNumberIncrementsFromProviderAndDocumentNumberIsInjected(): void
    {
        $service = new CreditNoteFromInvoiceService(
            new class implements CreditNoteVersionProvider {
                public function nextVersionNo(int $invoiceId): int
                {
                    return 7;
                }
            },
            new class implements DocumentNumberGenerator {
                public function next(string $docType, ?DateTimeImmutable $date = null): string
                {
                    return 'CRN-202603-00099';
                }
            }
        );

        $payload = $service->buildPayload([
            'id' => 99,
            'status' => 'issued',
            'snapshot_json' => json_encode([
                'header' => ['title' => 'X'],
                'totals' => ['total_inc_vat_minor' => 1],
                'lines' => [['id' => 1]],
                'material_lines' => [],
                'metadata' => [],
            ]),
        ]);

        self::assertSame(7, $payload['version_no']);
        self::assertSame('CRN-202603-00099', $payload['document_number']);
    }

    public function testFailsSafelyWhenSourceSnapshotIsInvalid(): void
    {
        $service = new CreditNoteFromInvoiceService(
            new class implements CreditNoteVersionProvider {
                public function nextVersionNo(int $invoiceId): int
                {
                    return 1;
                }
            },
            new class implements DocumentNumberGenerator {
                public function next(string $docType, ?DateTimeImmutable $date = null): string
                {
                    return 'CRN-202603-00001';
                }
            }
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot issue credit note: source invoice snapshot is missing or invalid.');

        $service->buildPayload([
            'id' => 10,
            'status' => 'issued',
            'snapshot_json' => '{invalid',
        ]);
    }

    public function testFailsWhenInvoiceStatusCannotIssueCreditNote(): void
    {
        $service = new CreditNoteFromInvoiceService(
            new class implements CreditNoteVersionProvider {
                public function nextVersionNo(int $invoiceId): int
                {
                    return 1;
                }
            },
            new class implements DocumentNumberGenerator {
                public function next(string $docType, ?DateTimeImmutable $date = null): string
                {
                    return 'CRN-202603-00001';
                }
            }
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Credit note can be issued only from issued, partially paid, or paid invoice.');

        $service->buildPayload([
            'id' => 10,
            'status' => 'archived',
            'snapshot_json' => json_encode([
                'header' => ['title' => 'X'],
                'totals' => ['total_inc_vat_minor' => 1],
                'lines' => [['id' => 1]],
                'material_lines' => [],
                'metadata' => [],
            ]),
        ]);
    }
}
