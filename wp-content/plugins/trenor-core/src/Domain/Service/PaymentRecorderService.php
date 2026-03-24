<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use Trenor\Core\Domain\Exception\PaymentRegistrationException;

final class PaymentRecorderService
{
    private InvoiceStatusAccess $invoiceRepository;

    private InvoicePaymentAccess $paymentRepository;

    private InvoicePaymentSummaryCalculator $summaryCalculator;

    public function __construct(
        InvoiceStatusAccess $invoiceRepository,
        InvoicePaymentAccess $paymentRepository,
        InvoicePaymentSummaryCalculator $summaryCalculator
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->paymentRepository = $paymentRepository;
        $this->summaryCalculator = $summaryCalculator;
    }

    /** @param array<string, mixed> $payload */
    public function record(array $payload): int
    {
        $invoiceId = (int) ($payload['invoice_id'] ?? 0);
        $invoice = $this->invoiceRepository->find($invoiceId);

        if ($invoice === null) {
            throw new PaymentRegistrationException('Invoice not found.');
        }

        $invoiceStatus = (string) ($invoice['status'] ?? '');
        if ($invoiceStatus === 'paid') {
            throw new PaymentRegistrationException('Cannot record payment for a paid invoice.');
        }

        if ($invoiceStatus === 'archived') {
            throw new PaymentRegistrationException('Cannot record payment for an archived invoice.');
        }

        if (! in_array($invoiceStatus, ['issued', 'partially_paid'], true)) {
            throw new PaymentRegistrationException('Payments can be recorded only for issued or partially paid invoices.');
        }

        $amountMinor = (int) ($payload['amount_minor'] ?? 0);
        if ($amountMinor <= 0) {
            throw new PaymentRegistrationException('Payment amount must be positive.');
        }

        $invoiceCurrency = strtoupper((string) ($invoice['currency'] ?? 'SEK'));
        $paymentCurrency = strtoupper((string) ($payload['currency'] ?? 'SEK'));
        if ($paymentCurrency !== $invoiceCurrency) {
            throw new PaymentRegistrationException('Payment currency must match invoice currency.');
        }

        $payments = $this->paymentRepository->byInvoice($invoiceId);
        $summary = $this->summaryCalculator->calculate($invoice, $payments);
        $resultingPaidTotal = (int) $summary['paid_total_minor'] + $amountMinor;

        if ($resultingPaidTotal > (int) ($invoice['total_inc_vat_minor'] ?? 0)) {
            throw new PaymentRegistrationException('Payment exceeds invoice total.');
        }

        $paymentId = $this->paymentRepository->create([
            'invoice_id' => $invoiceId,
            'payment_date' => (string) ($payload['payment_date'] ?? current_time('mysql', true)),
            'amount_minor' => $amountMinor,
            'currency' => $paymentCurrency,
            'method' => (string) ($payload['method'] ?? 'manual'),
            'reference' => (string) ($payload['reference'] ?? ''),
            'note' => (string) ($payload['note'] ?? ''),
            'actor_user_id' => isset($payload['actor_user_id']) ? (int) $payload['actor_user_id'] : null,
        ]);

        if ($paymentId === null) {
            throw new PaymentRegistrationException('Payment could not be recorded.');
        }

        $updatedPayments = $this->paymentRepository->byInvoice($invoiceId);
        $updatedSummary = $this->summaryCalculator->calculate($invoice, $updatedPayments);
        $this->invoiceRepository->transitionStatus($invoiceId, (string) $updatedSummary['computed_status']);

        return $paymentId;
    }
}
