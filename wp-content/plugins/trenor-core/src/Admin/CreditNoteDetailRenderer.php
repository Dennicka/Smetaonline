<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class CreditNoteDetailRenderer
{
    /**
     * @param array<string, mixed> $creditNote
     * @param array{header: array<string, mixed>, totals: array<string, mixed>, lines: array<int, mixed>, material_lines: array<int, mixed>, metadata: array<string, mixed>} $snapshot
     */
    public function render(array $creditNote, array $snapshot): void
    {
        $currency = (string) ($creditNote['currency'] ?? 'SEK');
        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];

        echo '<h2>Credit note detail</h2>';

        echo '<h3>1. Document summary</h3>';
        $this->renderKeyValueTable([
            'id' => $creditNote['id'] ?? '',
            'document_number' => $creditNote['document_number'] ?? '',
            'version_no' => $creditNote['version_no'] ?? '',
            'status' => $creditNote['status'] ?? '',
            'currency' => $currency,
            'issued_at' => $creditNote['issued_at'] ?? '',
        ]);

        echo '<h3>2. Source links and metadata</h3>';
        $this->renderKeyValueTable([
            'invoice_id' => $creditNote['invoice_id'] ?? '',
            'source_invoice_document_number' => $metadata['source_invoice_document_number'] ?? '',
            'offert_id' => $creditNote['offert_id'] ?? '',
            'estimate_id' => $creditNote['estimate_id'] ?? '',
            'source_estimate_title' => $metadata['source_estimate_title'] ?? '',
            'credit_note_version_no' => $metadata['credit_note_version_no'] ?? '',
            'issued_at_utc' => $metadata['issued_at_utc'] ?? '',
        ]);

        echo '<h3>3. Totals</h3>';
        $this->renderKeyValueTable([
            'labour_total_minor' => $this->formatMinorMoney($snapshot['totals']['labour_total_minor'] ?? 0, $currency),
            'materials_total_minor' => $this->formatMinorMoney($snapshot['totals']['materials_total_minor'] ?? 0, $currency),
            'subtotal_ex_vat_minor' => $this->formatMinorMoney($snapshot['totals']['subtotal_ex_vat_minor'] ?? 0, $currency),
            'vat_minor' => $this->formatMinorMoney($snapshot['totals']['vat_minor'] ?? 0, $currency),
            'total_inc_vat_minor' => $this->formatMinorMoney($snapshot['totals']['total_inc_vat_minor'] ?? 0, $currency),
        ]);

        echo '<h3>4. Labour lines snapshot</h3>';
        $this->renderLabourLines(is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [], $currency);

        echo '<h3>5. Material lines snapshot</h3>';
        $this->renderMaterialLines(is_array($snapshot['material_lines'] ?? null) ? $snapshot['material_lines'] : [], $currency);

        echo '<h3>6. Metadata block</h3>';
        $this->renderKeyValueTable($metadata);
    }

    /** @param array<string, mixed> $rows */
    private function renderKeyValueTable(array $rows): void
    {
        echo '<table class="widefat striped"><tbody>';
        foreach ($rows as $label => $value) {
            echo '<tr><th style="width:220px;">' . esc_html((string) $label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, mixed> $rows */
    private function renderLabourLines(array $rows, string $currency): void
    {
        if ($rows === []) {
            echo '<p>No labour lines in snapshot.</p>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>id</th><th>line_title_ru_snapshot</th><th>line_title_sv_snapshot</th><th>unit_code_snapshot</th><th>quantity</th><th>calculated_hours</th><th>labour_subtotal_minor</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['line_title_ru_snapshot'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['line_title_sv_snapshot'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['unit_code_snapshot'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['quantity'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['calculated_hours'] ?? '')) . '</td>';
            echo '<td>' . esc_html($this->formatMinorMoney($row['labour_subtotal_minor'] ?? 0, $currency)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, mixed> $rows */
    private function renderMaterialLines(array $rows, string $currency): void
    {
        if ($rows === []) {
            echo '<p>No material lines in snapshot.</p>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>id</th><th>material_name_ru_snapshot</th><th>material_name_sv_snapshot</th><th>unit_code_snapshot</th><th>quantity</th><th>subtotal_minor</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['material_name_ru_snapshot'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['material_name_sv_snapshot'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['unit_code_snapshot'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['quantity'] ?? '')) . '</td>';
            echo '<td>' . esc_html($this->formatMinorMoney($row['subtotal_minor'] ?? 0, $currency)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private function formatMinorMoney(mixed $minor, string $currency): string
    {
        if (! is_numeric($minor)) {
            return '—';
        }

        return number_format(((int) $minor) / 100, 2, '.', ' ') . ' ' . strtoupper($currency);
    }
}
