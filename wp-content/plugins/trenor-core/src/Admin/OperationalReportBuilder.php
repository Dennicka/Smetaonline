<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

use Trenor\Core\Domain\Service\InvoicePaymentSummaryCalculator;

final class OperationalReportBuilder
{
    private InvoicePaymentSummaryCalculator $summaryCalculator;

    public function __construct(?InvoicePaymentSummaryCalculator $summaryCalculator = null)
    {
        $this->summaryCalculator = $summaryCalculator ?? new InvoicePaymentSummaryCalculator();
    }

    /**
     * @param array<int, array<string, mixed>> $invoices
     * @param callable(int):array<int, array<string, mixed>> $paymentsByInvoice
     * @param array{status:string,date_from:string,date_to:string,period:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function invoices(array $invoices, callable $paymentsByInvoice, array $filters, int $paymentTermsDays = 30): array
    {
        $today = new \DateTimeImmutable('today');
        $rows = [];

        foreach ($invoices as $invoice) {
            $invoiceId = (int) ($invoice['id'] ?? 0);
            $payments = $paymentsByInvoice($invoiceId);
            $summary = $this->summaryCalculator->calculate($invoice, $payments);
            $storedStatus = sanitize_key((string) ($invoice['status'] ?? 'issued'));
            $computedStatus = $storedStatus === 'archived' ? 'archived' : (string) $summary['computed_status'];
            $dueDate = $this->resolveInvoiceDueDate($invoice, $paymentTermsDays);
            $outstandingMinor = (int) ($summary['outstanding_minor'] ?? 0);
            $dueState = 'n/a';
            if ($outstandingMinor > 0 && $dueDate !== '') {
                $dueState = strcmp($dueDate, $today->format('Y-m-d')) < 0 ? 'overdue' : 'due';
            }

            $row = [
                'id' => $invoiceId,
                'document_number' => (string) ($invoice['document_number'] ?? ''),
                'issued_at' => (string) ($invoice['issued_at'] ?? ''),
                'currency' => strtoupper((string) ($invoice['currency'] ?? 'SEK')),
                'stored_status' => $storedStatus,
                'status' => $computedStatus,
                'invoice_total_minor' => (int) ($summary['invoice_total_minor'] ?? 0),
                'paid_total_minor' => (int) ($summary['paid_total_minor'] ?? 0),
                'outstanding_minor' => $outstandingMinor,
                'due_date' => $dueDate,
                'due_state' => $dueState,
                'rot_requested' => (int) ($invoice['rot_requested'] ?? 0) === 1,
                'tax_mode' => sanitize_key((string) ($invoice['tax_mode'] ?? '')),
            ];

            if (! $this->matchesStatus($row, $filters['status'])) {
                continue;
            }

            if (! $this->matchesDateRange((string) $row['issued_at'], $filters['date_from'], $filters['date_to'])) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) ($b['issued_at'] ?? ''), (string) ($a['issued_at'] ?? '')));

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     * @param array{status:string,date_from:string,date_to:string,period:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function payments(array $payments, array $filters): array
    {
        $rows = [];
        foreach ($payments as $payment) {
            $row = [
                'id' => (int) ($payment['id'] ?? 0),
                'invoice_id' => (int) ($payment['invoice_id'] ?? 0),
                'payment_date' => (string) ($payment['payment_date'] ?? ''),
                'currency' => strtoupper((string) ($payment['currency'] ?? 'SEK')),
                'amount_minor' => (int) ($payment['amount_minor'] ?? 0),
                'method' => sanitize_key((string) ($payment['method'] ?? 'manual')),
                'reference' => (string) ($payment['reference'] ?? ''),
            ];

            if ($filters['status'] !== '' && $row['method'] !== $filters['status']) {
                continue;
            }

            if (! $this->matchesDateRange((string) $row['payment_date'], $filters['date_from'], $filters['date_to'])) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) ($b['payment_date'] ?? ''), (string) ($a['payment_date'] ?? '')));

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $rows @return array{count:int,total_minor:int} */
    public function paymentTotals(array $rows): array
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) ($row['amount_minor'] ?? 0);
        }

        return ['count' => count($rows), 'total_minor' => $total];
    }

    /**
     * @param array<int, array<string, mixed>> $reminders
     * @param array{status:string,date_from:string,date_to:string,period:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function reminders(array $reminders, array $filters): array
    {
        return $this->filterSimpleRows($reminders, 'status', 'issued_at', $filters);
    }

    /**
     * @param array<int, array<string, mixed>> $creditNotes
     * @param array{status:string,date_from:string,date_to:string,period:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function creditNotes(array $creditNotes, array $filters): array
    {
        return $this->filterSimpleRows($creditNotes, 'status', 'issued_at', $filters);
    }

    /**
     * @param array<int, array<string, mixed>> $invoices
     * @param array<int, array<string, mixed>> $creditNotes
     * @return array{rot_documents:int,reverse_charge_documents:int}
     */
    public function taxVisibility(array $invoices, array $creditNotes): array
    {
        $rotDocuments = 0;
        $reverseChargeDocuments = 0;
        foreach ([$invoices, $creditNotes] as $rows) {
            foreach ($rows as $row) {
                if ((int) ($row['rot_requested'] ?? 0) === 1) {
                    ++$rotDocuments;
                }

                if (sanitize_key((string) ($row['tax_mode'] ?? '')) === 'reverse_charge') {
                    ++$reverseChargeDocuments;
                }
            }
        }

        return [
            'rot_documents' => $rotDocuments,
            'reverse_charge_documents' => $reverseChargeDocuments,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $imports
     * @param array<int, array<string, mixed>> $priceRows
     * @param array{status:string,date_from:string,date_to:string,period:string} $filters
     * @return array{imports:array<int, array<string, mixed>>,price_changes:array<int, array<string, mixed>>}
     */
    public function supplierImportActivity(array $imports, array $priceRows, array $filters): array
    {
        $filteredImports = $this->filterSimpleRows($imports, 'status', 'imported_at', $filters);
        $filteredPriceRows = $this->filterSimpleRows($priceRows, '', 'created_at', $filters);

        return [
            'imports' => array_slice($filteredImports, 0, 30),
            'price_changes' => array_slice($filteredPriceRows, 0, 30),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $manifests
     * @param array{status:string,date_from:string,date_to:string,period:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function backupActivity(array $manifests, array $filters): array
    {
        return $this->filterSimpleRows($manifests, 'status', 'created_at', $filters);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterSimpleRows(array $rows, string $statusKey, string $dateKey, array $filters): array
    {
        $result = [];
        foreach ($rows as $row) {
            if ($statusKey !== '' && $filters['status'] !== '' && sanitize_key((string) ($row[$statusKey] ?? '')) !== $filters['status']) {
                continue;
            }

            if (! $this->matchesDateRange((string) ($row[$dateKey] ?? ''), $filters['date_from'], $filters['date_to'])) {
                continue;
            }

            $result[] = $row;
        }

        return $result;
    }

    private function matchesStatus(array $invoiceRow, string $requestedStatus): bool
    {
        if ($requestedStatus === '') {
            return true;
        }

        return (string) ($invoiceRow['status'] ?? '') === $requestedStatus;
    }

    private function matchesDateRange(string $rawDate, string $dateFrom, string $dateTo): bool
    {
        $date = $this->datePart($rawDate);
        if ($date === '') {
            return $dateFrom === '' && $dateTo === '';
        }

        if ($dateFrom !== '' && strcmp($date, $dateFrom) < 0) {
            return false;
        }

        if ($dateTo !== '' && strcmp($date, $dateTo) > 0) {
            return false;
        }

        return true;
    }

    private function datePart(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return '';
        }

        return substr($value, 0, 10);
    }

    private function resolveInvoiceDueDate(array $invoice, int $paymentTermsDays): string
    {
        $snapshot = (string) ($invoice['snapshot_json'] ?? '');
        if ($snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            if (is_array($decoded)) {
                $candidate = $decoded['payment_due_date'] ?? $decoded['document']['payment_due_date'] ?? null;
                if (is_string($candidate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate) === 1) {
                    return $candidate;
                }
            }
        }

        $issuedDate = $this->datePart((string) ($invoice['issued_at'] ?? ''));
        if ($issuedDate === '') {
            return '';
        }

        $baseDate = \DateTimeImmutable::createFromFormat('Y-m-d', $issuedDate);
        if (! $baseDate instanceof \DateTimeImmutable) {
            return '';
        }

        $days = max(1, $paymentTermsDays);

        return $baseDate->modify('+' . $days . ' days')->format('Y-m-d');
    }
}
