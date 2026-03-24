<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class ReminderDetailRenderer
{
    /**
     * @param array<string, mixed> $reminder
     * @param array{header: array<string, mixed>, totals: array<string, mixed>, lines: array<int, mixed>, material_lines: array<int, mixed>, metadata: array<string, mixed>} $snapshot
     */
    public function render(array $reminder, array $snapshot): void
    {
        $currency = (string) ($reminder['currency'] ?? 'SEK');
        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];

        echo '<h2>Påminnelse / Reminder detail</h2>';
        echo '<table class="widefat striped"><tbody>';
        $this->row('id', $reminder['id'] ?? '');
        $this->row('invoice_id', $reminder['invoice_id'] ?? '');
        $this->row('document_number', $reminder['document_number'] ?? '');
        $this->row('version_no', $reminder['version_no'] ?? '');
        $this->row('reminder_level', $reminder['reminder_level'] ?? '1');
        $this->row('status', $reminder['status'] ?? '');
        $this->row('currency', $currency);
        $this->row('total_inc_vat_minor', $this->formatMinorMoney($reminder['total_inc_vat_minor'] ?? 0, $currency));
        $this->row('issued_at', $reminder['issued_at'] ?? '');
        $this->row('source_invoice_document_number', $metadata['source_invoice_document_number'] ?? '');
        $this->row('invoice_outstanding_minor', $this->formatMinorMoney($metadata['invoice_outstanding_minor'] ?? null, $currency));
        $this->row('issued_at_utc', $metadata['issued_at_utc'] ?? '');
        echo '</tbody></table>';
    }

    private function row(string $label, mixed $value): void
    {
        if ($value === '') {
            return;
        }

        echo '<tr><th style="width:220px;">' . esc_html($label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
    }

    private function formatMinorMoney(mixed $minor, string $currency): string
    {
        if (! is_numeric($minor)) {
            return '—';
        }

        return number_format(((int) $minor) / 100, 2, '.', ' ') . ' ' . strtoupper($currency);
    }
}
