<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Database\InvoicePaymentRepository;
use Trenor\Core\Database\InvoiceRepository;
use Trenor\Core\Domain\Exception\PaymentRegistrationException;
use Trenor\Core\Domain\Service\InvoicePaymentSummaryCalculator;
use Trenor\Core\Domain\Service\PaymentRecorderService;

final class PaymentRecorderServiceTest extends TestCase
{
    public function testValidPaymentOnIssuedInvoiceSetsPartiallyPaidOrPaid(): void
    {
        $invoiceRepository = new class () extends InvoiceRepository {
            public array $invoice = ['id' => 14, 'status' => 'issued', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000];
            public string $lastStatus = '';

            public function find(int $id): ?array
            {
                return $id === 14 ? $this->invoice : null;
            }

            public function transitionStatus(int $id, string $status): bool
            {
                $this->lastStatus = $status;

                return true;
            }
        };

        $paymentRepository = new class () extends InvoicePaymentRepository {
            /** @var array<int, array<string, mixed>> */
            public array $rows = [];

            public function byInvoice(int $invoiceId): array
            {
                return array_values(array_filter(
                    $this->rows,
                    static fn (array $row): bool => (int) ($row['invoice_id'] ?? 0) === $invoiceId
                ));
            }

            public function create(array $data): ?int
            {
                $id = count($this->rows) + 1;
                $data['id'] = $id;
                $this->rows[] = $data;

                return $id;
            }
        };

        $service = new PaymentRecorderService($invoiceRepository, $paymentRepository, new InvoicePaymentSummaryCalculator());

        $firstId = $service->record(['invoice_id' => 14, 'payment_date' => '2026-03-24 10:00:00', 'amount_minor' => 4000, 'currency' => 'SEK']);
        self::assertSame(1, $firstId);
        self::assertSame('partially_paid', $invoiceRepository->lastStatus);

        $secondId = $service->record(['invoice_id' => 14, 'payment_date' => '2026-03-24 11:00:00', 'amount_minor' => 6000, 'currency' => 'SEK']);
        self::assertSame(2, $secondId);
        self::assertSame('paid', $invoiceRepository->lastStatus);
    }

    public function testZeroOrNegativeAmountIsRejected(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'issued', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000]);

        $this->expectException(PaymentRegistrationException::class);
        $this->expectExceptionMessage('Payment amount must be positive.');

        $service->record(['invoice_id' => 14, 'amount_minor' => 0, 'currency' => 'SEK']);
    }

    public function testCurrencyMismatchIsRejected(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'issued', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000]);

        $this->expectException(PaymentRegistrationException::class);
        $this->expectExceptionMessage('Payment currency must match invoice currency.');

        $service->record(['invoice_id' => 14, 'amount_minor' => 1000, 'currency' => 'USD']);
    }

    public function testOverpaymentIsRejected(): void
    {
        $service = $this->buildServiceWithInvoice(
            ['status' => 'issued', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000],
            [['id' => 1, 'invoice_id' => 14, 'amount_minor' => 9000, 'currency' => 'SEK']]
        );

        $this->expectException(PaymentRegistrationException::class);
        $this->expectExceptionMessage('Payment exceeds invoice total.');

        $service->record(['invoice_id' => 14, 'amount_minor' => 2000, 'currency' => 'SEK']);
    }

    public function testPaidInvoiceIsRejected(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'paid', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000]);

        $this->expectException(PaymentRegistrationException::class);
        $this->expectExceptionMessage('Cannot record payment for a paid invoice.');

        $service->record(['invoice_id' => 14, 'amount_minor' => 1000, 'currency' => 'SEK']);
    }

    public function testArchivedInvoiceIsRejected(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'archived', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000]);

        $this->expectException(PaymentRegistrationException::class);
        $this->expectExceptionMessage('Cannot record payment for an archived invoice.');

        $service->record(['invoice_id' => 14, 'amount_minor' => 1000, 'currency' => 'SEK']);
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<int, array<string, mixed>> $payments
     */
    private function buildServiceWithInvoice(array $invoice, array $payments = []): PaymentRecorderService
    {
        $invoiceRepository = new class ($invoice) extends InvoiceRepository {
            /** @param array<string, mixed> $invoice */
            public function __construct(private array $invoice)
            {
                parent::__construct();
            }

            public function find(int $id): ?array
            {
                if ($id !== 14) {
                    return null;
                }

                return array_merge(['id' => 14], $this->invoice);
            }

            public function transitionStatus(int $id, string $status): bool
            {
                return true;
            }
        };

        $paymentRepository = new class ($payments) extends InvoicePaymentRepository {
            /** @param array<int, array<string, mixed>> $rows */
            public function __construct(private array $rows)
            {
                parent::__construct();
            }

            public function byInvoice(int $invoiceId): array
            {
                return array_values(array_filter(
                    $this->rows,
                    static fn (array $row): bool => (int) ($row['invoice_id'] ?? 0) === $invoiceId
                ));
            }

            public function create(array $data): ?int
            {
                $id = count($this->rows) + 1;
                $data['id'] = $id;
                $this->rows[] = $data;

                return $id;
            }
        };

        return new PaymentRecorderService($invoiceRepository, $paymentRepository, new InvoicePaymentSummaryCalculator());
    }
}
