<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class ReminderDetailRenderer
{
    /**
     * @param array<string, mixed> $reminder
     * @param array{header: array<string, mixed>, totals: array<string, mixed>, lines: array<int, mixed>, material_lines: array<int, mixed>, metadata: array<string, mixed>} $snapshot
     * @param array{source_invoice?: array<string,mixed>, source_offert?: array<string,mixed>, source_estimate?: array<string,mixed>, project?: array<string,mixed>, property?: array<string,mixed>, client?: array<string,mixed>, payment_summary?: array<string,mixed>, document_profile?: array<string,mixed>} $context
     */
    public function render(array $reminder, array $snapshot, array $context = []): void
    {
        $currency = (string) ($reminder['currency'] ?? 'SEK');
        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
        $sourceInvoice = is_array($context['source_invoice'] ?? null) ? $context['source_invoice'] : [];
        $sourceOffert = is_array($context['source_offert'] ?? null) ? $context['source_offert'] : [];
        $sourceEstimate = is_array($context['source_estimate'] ?? null) ? $context['source_estimate'] : [];
        $project = is_array($context['project'] ?? null) ? $context['project'] : [];
        $property = is_array($context['property'] ?? null) ? $context['property'] : [];
        $client = is_array($context['client'] ?? null) ? $context['client'] : [];
        $paymentSummary = is_array($context['payment_summary'] ?? null) ? $context['payment_summary'] : [];
        $documentProfile = is_array($context['document_profile'] ?? null) ? $context['document_profile'] : [];

        echo '<h2>Påminnelse / Reminder detail</h2>';
        echo '<p>Reminder uses the issued invoice snapshot and preserves source numbering/references.</p>';

        echo '<h3>1. Reminder identity</h3>';
        $this->renderTable([
            'Document number' => $reminder['document_number'] ?? '',
            'Version' => $reminder['version_no'] ?? '',
            'Reminder level' => $reminder['reminder_level'] ?? '1',
            'Status' => $reminder['status'] ?? '',
            'Issued at' => $this->first([$reminder['issued_at'] ?? null, $metadata['issued_at_utc'] ?? null]),
            'Currency' => $currency,
        ]);

        echo '<h3>2. Invoice and chain references</h3>';
        $this->renderTable([
            'Invoice id' => $this->first([$reminder['invoice_id'] ?? null, $sourceInvoice['id'] ?? null]),
            'Source invoice document number' => $this->first([$metadata['source_invoice_document_number'] ?? null, $sourceInvoice['document_number'] ?? null]),
            'Source offert document number' => $this->first([$metadata['source_offert_document_number'] ?? null, $sourceOffert['document_number'] ?? null]),
            'Source estimate id' => $this->first([$metadata['source_estimate_id'] ?? null, $sourceEstimate['id'] ?? null]),
            'Source estimate title' => $this->first([$metadata['source_estimate_title'] ?? null, $sourceEstimate['title'] ?? null]),
            'Project' => $project['name'] ?? '',
            'Property' => $property['name'] ?? '',
            'Client' => $this->first([$reminder['client_name'] ?? null, $client['name'] ?? null]),
        ]);

        echo '<h3>3. Amounts and escalation context</h3>';
        $this->renderTable([
            'Reminder total incl. VAT' => $this->formatMinorMoney($reminder['total_inc_vat_minor'] ?? null, $currency),
            'Invoice outstanding' => $this->formatMinorMoney(
                $metadata['invoice_outstanding_minor'] ?? ($paymentSummary['outstanding_minor'] ?? null),
                $currency
            ),
            'Paid on source invoice' => $this->formatMinorMoney($paymentSummary['paid_total_minor'] ?? null, $currency),
            'Computed invoice status' => $this->scalarToString($paymentSummary['computed_status'] ?? ''),
            'Escalation note' => 'Reminder level and outstanding amount are presented together for payment follow-up.',
        ]);

        echo '<h3>4. Seller and payment channels</h3>';
        $this->renderTable([
            'Company' => $documentProfile['company_name'] ?? '',
            'Org number' => $documentProfile['org_number'] ?? '',
            'Bankgiro' => $documentProfile['bankgiro'] ?? '',
            'Plusgiro' => $documentProfile['plusgiro'] ?? '',
            'Swish' => $documentProfile['swish'] ?? '',
            'IBAN' => $documentProfile['iban'] ?? '',
            'BIC' => $documentProfile['bic'] ?? '',
        ]);
    }

    /** @param array<string,mixed> $rows */
    private function renderTable(array $rows): void
    {
        echo '<table class="widefat striped"><tbody>';
        foreach ($rows as $label => $value) {
            $normalized = $this->scalarToString($value);
            if ($normalized === '') {
                continue;
            }
            echo '<tr><th style="width:260px;">' . esc_html($label) . '</th><td>' . esc_html($normalized) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function formatMinorMoney(mixed $minor, string $currency): string
    {
        if (! is_numeric($minor)) {
            return '';
        }

        return number_format(((int) $minor) / 100, 2, '.', ' ') . ' ' . strtoupper($currency);
    }

    /** @param array<int,mixed> $values */
    private function first(array $values): string
    {
        foreach ($values as $value) {
            $normalized = $this->scalarToString($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function scalarToString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
