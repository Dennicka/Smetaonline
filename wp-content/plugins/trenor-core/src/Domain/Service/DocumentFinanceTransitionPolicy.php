<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class DocumentFinanceTransitionPolicy
{
    public function canIssueInvoiceFromOffertStatus(string $offertStatus): bool
    {
        return sanitize_key($offertStatus) === 'accepted';
    }

    public function canRecordPaymentForInvoiceStatus(string $invoiceStatus): bool
    {
        return in_array(sanitize_key($invoiceStatus), ['issued', 'partially_paid'], true);
    }

    public function canIssueCreditNoteFromInvoiceStatus(string $invoiceStatus): bool
    {
        return in_array(sanitize_key($invoiceStatus), ['issued', 'partially_paid', 'paid'], true);
    }

    public function canIssueReminderFromInvoiceStatus(string $invoiceStatus): bool
    {
        return in_array(sanitize_key($invoiceStatus), ['issued', 'partially_paid'], true);
    }

    public function canTransitionInvoiceStatus(string $fromStatus, string $toStatus): bool
    {
        $from = sanitize_key($fromStatus);
        $to = sanitize_key($toStatus);

        $allowedTransitions = [
            'issued' => ['partially_paid', 'paid', 'archived'],
            'partially_paid' => ['paid', 'archived'],
            'paid' => ['archived'],
        ];

        return in_array($to, $allowedTransitions[$from] ?? [], true);
    }

    public function canTransitionCreditNoteStatus(string $fromStatus, string $toStatus): bool
    {
        $from = sanitize_key($fromStatus);
        $to = sanitize_key($toStatus);

        $allowedTransitions = [
            'issued' => ['archived'],
        ];

        return in_array($to, $allowedTransitions[$from] ?? [], true);
    }

    public function canTransitionReminderStatus(string $fromStatus, string $toStatus): bool
    {
        $from = sanitize_key($fromStatus);
        $to = sanitize_key($toStatus);

        $allowedTransitions = [
            'issued' => ['archived'],
        ];

        return in_array($to, $allowedTransitions[$from] ?? [], true);
    }
}
