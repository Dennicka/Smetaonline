<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class CreditNotePrintRenderer
{
    private CreditNotePrintViewModel $viewModel;

    public function __construct(?CreditNotePrintViewModel $viewModel = null)
    {
        $this->viewModel = $viewModel ?? new CreditNotePrintViewModel();
    }

    /**
     * @param array<string, mixed> $creditNote
     * @param array{header: array<string, mixed>, totals: array<string, mixed>, lines: array<int, mixed>, material_lines: array<int, mixed>, metadata: array<string, mixed>} $snapshot
     * @param array{source_invoice?: array<string, mixed>, source_offert?: array<string, mixed>, source_estimate?: array<string, mixed>, project?: array<string, mixed>, property?: array<string, mixed>, client?: array<string, mixed>} $context
     */
    public function render(array $creditNote, array $snapshot, array $context = []): void
    {
        $view = $this->viewModel->build($creditNote, $snapshot, $context);
        $creditNoteId = (int) ($creditNote['id'] ?? 0);

        echo '<style>
        .trn-print-toolbar { margin: 12px 0 20px; display:flex; gap:8px; flex-wrap:wrap; }
        .trn-print-doc h2 { margin-top: 0; }
        .trn-print-doc h3 { margin: 20px 0 8px; }
        .trn-print-doc table { width:100%; border-collapse: collapse; margin-bottom: 14px; }
        .trn-print-doc th, .trn-print-doc td { border:1px solid #ddd; padding:7px 8px; text-align:left; vertical-align:top; }
        .trn-print-doc th { background:#f6f7f7; width:220px; }
        .trn-print-doc .is-empty { color:#666; font-style:italic; }
        @media print {
            .trn-print-toolbar, #adminmenumain, #wpadminbar, #screen-meta-links, .notice { display:none !important; }
            #wpcontent, #wpbody-content { margin:0 !important; padding:0 !important; }
            .trn-print-doc table, .trn-print-doc th, .trn-print-doc td { border-color:#bbb; }
        }
        </style>';

        echo '<div class="trn-print-toolbar">';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=trn_credit_notes&credit_note_id=' . $creditNoteId)) . '">Back to credit note detail</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=trn_credit_notes')) . '">Back to credit notes list</a>';
        echo '<button type="button" class="button button-primary" onclick="window.print();">Print</button>';
        echo '</div>';

        echo '<div class="trn-print-doc">';
        echo '<h2>Printable credit note document</h2>';
        echo '<h3>Document</h3>';
        $this->renderKeyValueTable($view['document']);

        echo '<h3>Source context</h3>';
        $this->renderKeyValueTable($view['context']);

        echo '<h3>Totals</h3>';
        $this->renderTotalsTable($view['totals'], $view['currency']);

        echo '<h3>Labour lines</h3>';
        $this->renderLabourLinesTable($view['labour_lines'], $view['currency']);

        echo '<h3>Material lines</h3>';
        $this->renderMaterialLinesTable($view['material_lines'], $view['currency']);

        echo '<h3>Issuer / Company</h3>';
        $this->renderKeyValueTable($view['issuer']);

        echo '<h3>Contact / Payment details</h3>';
        $this->renderKeyValueTable($view['payment_details']);

        echo '<h3>Terms / Notes</h3>';
        $this->renderKeyValueTable($view['terms_notes']);
        echo '</div>';
    }

    /** @param array<string, string> $rows */
    private function renderKeyValueTable(array $rows): void
    {
        echo '<table><tbody>';
        foreach ($rows as $label => $value) {
            if ($value === '') {
                continue;
            }
            echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array{label: string, minor: string}> $rows */
    private function renderTotalsTable(array $rows, string $currency): void
    {
        echo '<table><thead><tr><th>Field</th><th>Amount</th><th>Minor</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['label']) . '</td>';
            echo '<td>' . esc_html($this->formatMinorMoney($row['minor'], $currency)) . '</td>';
            echo '<td>' . esc_html($row['minor'] !== '' ? $row['minor'] : '—') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array{title: string, unit: string, quantity: string, hours: string, subtotal_minor: string}> $rows */
    private function renderLabourLinesTable(array $rows, string $currency): void
    {
        if ($rows === []) {
            echo '<p class="is-empty">No lines.</p>';

            return;
        }

        echo '<table><thead><tr><th>title</th><th>unit</th><th>quantity</th><th>hours</th><th>labour subtotal</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['title']) . '</td>';
            echo '<td>' . esc_html($row['unit']) . '</td>';
            echo '<td>' . esc_html($row['quantity']) . '</td>';
            echo '<td>' . esc_html($row['hours']) . '</td>';
            echo '<td>' . esc_html($this->formatMinorMoney($row['subtotal_minor'], $currency)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array{name: string, unit: string, quantity: string, subtotal_minor: string}> $rows */
    private function renderMaterialLinesTable(array $rows, string $currency): void
    {
        if ($rows === []) {
            echo '<p class="is-empty">No lines.</p>';

            return;
        }

        echo '<table><thead><tr><th>name</th><th>unit</th><th>quantity</th><th>subtotal</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['name']) . '</td>';
            echo '<td>' . esc_html($row['unit']) . '</td>';
            echo '<td>' . esc_html($row['quantity']) . '</td>';
            echo '<td>' . esc_html($this->formatMinorMoney($row['subtotal_minor'], $currency)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private function formatMinorMoney(string $minor, string $currency): string
    {
        if (! is_numeric($minor)) {
            return '—';
        }

        return number_format(((float) $minor) / 100, 2, '.', ' ') . ' ' . strtoupper($currency !== '' ? $currency : 'SEK');
    }
}
