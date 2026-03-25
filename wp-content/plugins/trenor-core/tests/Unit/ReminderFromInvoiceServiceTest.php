<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Trenor\Core\Domain\Service\DocumentNumberGenerator;
use Trenor\Core\Domain\Service\InvoicePaymentAccess;
use Trenor\Core\Domain\Service\ReminderFromInvoiceService;
use Trenor\Core\Domain\Service\ReminderVersionProvider;

final class ReminderFromInvoiceServiceTest extends TestCase
{
    public function testBuildsPayloadForUnpaidInvoice(): void
    {
        $service = new ReminderFromInvoiceService(
            new class implements ReminderVersionProvider {
                public function nextVersionNo(int $invoiceId): int
                {
                    return 3;
                }
            },
            new class implements InvoicePaymentAccess {
                public function create(array $data): ?int
                {
                    return null;
                }

                public function byInvoice(int $invoiceId): array
                {
                    return [['amount_minor' => 1000]];
                }
            },
            new class implements DocumentNumberGenerator {
                public function next(string $docType, ?DateTimeImmutable $date = null): string
                {
                    return 'REM-202603-00031';
                }
            }
        );

        $payload = $service->buildPayload([
            'id' => 5,
            'status' => 'issued',
            'document_number' => 'INV-202603-00003',
            'offert_id' => 2,
            'estimate_id' => 1,
            'total_inc_vat_minor' => 5000,
            'snapshot_json' => json_encode([
                'header' => ['title' => 'Kitchen'],
                'totals' => ['total_inc_vat_minor' => 5000],
                'lines' => [['id' => 1]],
                'material_lines' => [],
                'metadata' => ['project_id' => 77, 'client_id' => 88],
            ]),
        ], 1, new DateTimeImmutable('2026-03-24 12:00:00'));

        self::assertSame(5, $payload['invoice_id']);
        self::assertSame(3, $payload['version_no']);
        self::assertSame('REM-202603-00031', $payload['document_number']);
        self::assertSame(1, $payload['reminder_level']);
        self::assertSame(77, $payload['project_id']);
        self::assertSame(88, $payload['client_id']);
        $snapshot = json_decode((string) $payload['snapshot_json'], true);
        self::assertSame('INV-202603-00003', $snapshot['metadata']['source_invoice_document_number']);
    }

    public function testFullyPaidInvoiceCannotIssueReminder(): void
    {
        $service = new ReminderFromInvoiceService(
            new class implements ReminderVersionProvider {
                public function nextVersionNo(int $invoiceId): int
                {
                    return 1;
                }
            },
            new class implements InvoicePaymentAccess {
                public function create(array $data): ?int
                {
                    return null;
                }

                public function byInvoice(int $invoiceId): array
                {
                    return [['amount_minor' => 5000]];
                }
            },
            new class implements DocumentNumberGenerator {
                public function next(string $docType, ?DateTimeImmutable $date = null): string
                {
                    return 'REM-202603-00001';
                }
            }
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reminder cannot be issued: invoice is fully paid.');

        $service->buildPayload([
            'id' => 5,
            'status' => 'issued',
            'total_inc_vat_minor' => 5000,
            'snapshot_json' => json_encode([
                'header' => ['title' => 'Kitchen'],
                'totals' => ['total_inc_vat_minor' => 5000],
            ]),
        ]);
    }

    public function testArchivedInvoiceCannotIssueReminder(): void
    {
        $service = new ReminderFromInvoiceService(
            new class implements ReminderVersionProvider {
                public function nextVersionNo(int $invoiceId): int
                {
                    return 1;
                }
            },
            new class implements InvoicePaymentAccess {
                public function create(array $data): ?int
                {
                    return null;
                }

                public function byInvoice(int $invoiceId): array
                {
                    return [];
                }
            },
            new class implements DocumentNumberGenerator {
                public function next(string $docType, ?DateTimeImmutable $date = null): string
                {
                    return 'REM-202603-00001';
                }
            }
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reminder can be issued only from issued or partially paid invoice.');

        $service->buildPayload([
            'id' => 5,
            'status' => 'archived',
            'total_inc_vat_minor' => 5000,
            'snapshot_json' => json_encode([
                'header' => ['title' => 'Kitchen'],
                'totals' => ['total_inc_vat_minor' => 5000],
            ]),
        ]);
    }
}
