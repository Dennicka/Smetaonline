<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Exception\PaymentRegistrationException;
use Trenor\Core\Domain\Service\InvoicePaymentAccess;
use Trenor\Core\Domain\Service\InvoicePaymentSummaryCalculator;
use Trenor\Core\Domain\Service\InvoiceStatusAccess;
use Trenor\Core\Domain\Service\PaymentRecorderService;

final class PaymentRecorderServiceTest extends TestCase
{
    public function testIssuedInvoiceWithPartialPaymentTransitionsToPartiallyPaid(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'issued', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000], []);

        $id = $service->record(['invoice_id' => 14, 'payment_date' => '2026-03-24 10:00:00', 'amount_minor' => 4000, 'currency' => 'SEK']);

        self::assertSame(1, $id);
        self::assertSame('partially_paid', $service->invoiceRepository->lastStatus);
    }

    public function testIssuedInvoiceWithExactPaymentTransitionsToPaid(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'issued', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000], []);

        $id = $service->record(['invoice_id' => 14, 'amount_minor' => 10000, 'currency' => 'SEK']);

        self::assertSame(1, $id);
        self::assertSame('paid', $service->invoiceRepository->lastStatus);
    }

    public function testPartiallyPaidInvoiceWithRemainingPaymentTransitionsToPaid(): void
    {
        $service = $this->buildServiceWithInvoice(
            ['status' => 'partially_paid', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000],
            [['id' => 1, 'invoice_id' => 14, 'amount_minor' => 4000, 'currency' => 'SEK']]
        );

        $id = $service->record(['invoice_id' => 14, 'amount_minor' => 6000, 'currency' => 'SEK']);

        self::assertSame(2, $id);
        self::assertSame('paid', $service->invoiceRepository->lastStatus);
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

    public function testZeroOrNegativePaymentIsRejected(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'issued', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000], []);

        $this->expectException(PaymentRegistrationException::class);
        $this->expectExceptionMessage('Payment amount must be positive.');

        $service->record(['invoice_id' => 14, 'amount_minor' => 0, 'currency' => 'SEK']);
    }

    public function testArchivedInvoiceIsRejected(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'archived', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000], []);

        $this->expectException(PaymentRegistrationException::class);
        $this->expectExceptionMessage('Cannot record payment for an archived invoice.');

        $service->record(['invoice_id' => 14, 'amount_minor' => 1000, 'currency' => 'SEK']);
    }

    public function testPaidInvoiceIsRejected(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'paid', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000], []);

        $this->expectException(PaymentRegistrationException::class);
        $this->expectExceptionMessage('Cannot record payment for a paid invoice.');

        $service->record(['invoice_id' => 14, 'amount_minor' => 1000, 'currency' => 'SEK']);
    }

    public function testCurrencyMismatchIsRejected(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'issued', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000], []);

        $this->expectException(PaymentRegistrationException::class);
        $this->expectExceptionMessage('Payment currency must match invoice currency.');

        $service->record(['invoice_id' => 14, 'amount_minor' => 1000, 'currency' => 'USD']);
    }

    public function testPaymentRowIsWrittenBeforeStatusTransitionCheck(): void
    {
        $service = $this->buildServiceWithInvoice(['status' => 'issued', 'currency' => 'SEK', 'total_inc_vat_minor' => 10000], []);

        $service->record(['invoice_id' => 14, 'amount_minor' => 1000, 'currency' => 'SEK']);

        self::assertSame(['create', 'transition:partially_paid'], $service->events);
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<int, array<string, mixed>> $payments
     */
    private function buildServiceWithInvoice(array $invoice, array $payments): PaymentServiceHarness
    {
        $harness = new PaymentServiceHarness();

        $harness->invoiceRepository = new class ($invoice, $harness) implements InvoiceStatusAccess {
            public string $lastStatus = '';

            /** @param array<string, mixed> $invoice */
            public function __construct(private array $invoice, private PaymentServiceHarness $harness)
            {
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
                $this->lastStatus = $status;
                $this->harness->events[] = 'transition:' . $status;

                return true;
            }
        };

        $harness->paymentRepository = new class ($payments, $harness) implements InvoicePaymentAccess {
            /** @param array<int, array<string, mixed>> $rows */
            public function __construct(private array $rows, private PaymentServiceHarness $harness)
            {
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
                $this->harness->events[] = 'create';

                return $id;
            }
        };

        $harness->service = new PaymentRecorderService(
            $harness->invoiceRepository,
            $harness->paymentRepository,
            new InvoicePaymentSummaryCalculator()
        );

        return $harness;
    }
}

final class PaymentServiceHarness
{
    public PaymentRecorderService $service;

    public InvoiceStatusAccess $invoiceRepository;

    public InvoicePaymentAccess $paymentRepository;

    /** @var array<int, string> */
    public array $events = [];

    /** @param array<string, mixed> $payload */
    public function record(array $payload): int
    {
        return $this->service->record($payload);
    }
}
