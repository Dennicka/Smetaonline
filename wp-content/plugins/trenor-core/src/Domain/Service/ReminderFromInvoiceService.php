<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Trenor\Core\Admin\CreditNoteSnapshotReader;
use Trenor\Core\Support\Snapshot;

final class ReminderFromInvoiceService
{
    private ReminderVersionProvider $versionProvider;
    private InvoicePaymentAccess $paymentRepository;
    private DocumentNumberGenerator $documentNumberGenerator;
    private InvoicePaymentSummaryCalculator $summaryCalculator;
    private CreditNoteSnapshotReader $snapshotReader;
    private DocumentFinanceTransitionPolicy $transitionPolicy;

    public function __construct(
        ReminderVersionProvider $versionProvider,
        InvoicePaymentAccess $paymentRepository,
        DocumentNumberGenerator $documentNumberGenerator,
        ?InvoicePaymentSummaryCalculator $summaryCalculator = null,
        ?CreditNoteSnapshotReader $snapshotReader = null,
        ?DocumentFinanceTransitionPolicy $transitionPolicy = null
    ) {
        $this->versionProvider = $versionProvider;
        $this->paymentRepository = $paymentRepository;
        $this->documentNumberGenerator = $documentNumberGenerator;
        $this->summaryCalculator = $summaryCalculator ?? new InvoicePaymentSummaryCalculator();
        $this->snapshotReader = $snapshotReader ?? new CreditNoteSnapshotReader();
        $this->transitionPolicy = $transitionPolicy ?? new DocumentFinanceTransitionPolicy();
    }

    /**
     * @param array<string,mixed> $invoiceRow
     * @return array<string,mixed>
     */
    public function buildPayload(array $invoiceRow, int $reminderLevel = 1, ?DateTimeImmutable $issuedAtUtc = null): array
    {
        $invoiceId = (int) ($invoiceRow['id'] ?? 0);
        if ($invoiceId <= 0) {
            throw new RuntimeException('Source invoice not found.');
        }

        $status = sanitize_key((string) ($invoiceRow['status'] ?? ''));
        if (! $this->transitionPolicy->canIssueReminderFromInvoiceStatus($status)) {
            throw new RuntimeException('Reminder can be issued only from issued or partially paid invoice.');
        }

        $payments = $this->paymentRepository->byInvoice($invoiceId);
        $summary = $this->summaryCalculator->calculate($invoiceRow, $payments);
        $outstandingMinor = (int) ($summary['outstanding_minor'] ?? 0);
        if ($outstandingMinor <= 0) {
            throw new RuntimeException('Reminder cannot be issued: invoice is fully paid.');
        }

        $snapshot = $this->snapshotReader->read($invoiceRow);
        if ($snapshot['header'] === [] || $snapshot['totals'] === []) {
            throw new RuntimeException('Cannot issue reminder: source invoice snapshot is missing or invalid.');
        }

        $issuedAtUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $normalizedLevel = max(1, $reminderLevel);
        $versionNo = $this->versionProvider->nextVersionNo($invoiceId);
        $documentNumber = $this->documentNumberGenerator->next('rem', $issuedAtUtc);

        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
        $header = is_array($snapshot['header'] ?? null) ? $snapshot['header'] : [];
        $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
        $lines = is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];
        $materialLines = is_array($snapshot['material_lines'] ?? null) ? $snapshot['material_lines'] : [];

        $snapshotPayload = Snapshot::freeze([
            'header' => $header,
            'totals' => $totals,
            'lines' => $lines,
            'material_lines' => $materialLines,
            'metadata' => [
                'source_invoice_id' => $invoiceId,
                'source_invoice_document_number' => (string) ($invoiceRow['document_number'] ?? ''),
                'source_offert_id' => (int) ($invoiceRow['offert_id'] ?? 0),
                'source_estimate_id' => (int) ($invoiceRow['estimate_id'] ?? 0),
                'source_estimate_title' => (string) ($metadata['source_estimate_title'] ?? $header['title'] ?? ''),
                'reminder_version_no' => $versionNo,
                'reminder_level' => $normalizedLevel,
                'document_number' => $documentNumber,
                'issued_at_utc' => $issuedAtUtc->format('Y-m-d H:i:s'),
                'invoice_outstanding_minor' => $outstandingMinor,
            ],
        ]);

        return [
            'invoice_id' => $invoiceId,
            'offert_id' => (int) ($invoiceRow['offert_id'] ?? 0),
            'estimate_id' => (int) ($invoiceRow['estimate_id'] ?? 0),
            'project_id' => (int) ($metadata['project_id'] ?? 0),
            'client_id' => (int) ($metadata['client_id'] ?? 0),
            'document_number' => $documentNumber,
            'version_no' => $versionNo,
            'status' => 'issued',
            'reminder_level' => $normalizedLevel,
            'currency' => strtoupper((string) ($invoiceRow['currency'] ?? 'SEK')),
            'vat_rate_percent' => (float) ($invoiceRow['vat_rate_percent'] ?? 25),
            'labour_total_minor' => (int) ($totals['labour_total_minor'] ?? 0),
            'materials_total_minor' => (int) ($totals['materials_total_minor'] ?? 0),
            'subtotal_ex_vat_minor' => (int) ($totals['subtotal_ex_vat_minor'] ?? 0),
            'vat_minor' => (int) ($totals['vat_minor'] ?? 0),
            'total_inc_vat_minor' => (int) ($totals['total_inc_vat_minor'] ?? 0),
            'snapshot_json' => (string) wp_json_encode($snapshotPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            'issued_at' => $issuedAtUtc->format('Y-m-d H:i:s'),
            'actor_user_id' => get_current_user_id() ?: null,
        ];
    }
}
