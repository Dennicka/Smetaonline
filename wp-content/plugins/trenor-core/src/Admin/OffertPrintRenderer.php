<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

use Trenor\Core\Domain\Service\DocumentSettings;

final class OffertPrintRenderer
{
    private OffertPrintViewModel $viewModel;
    private DocumentSettings $documentSettings;

    public function __construct(?OffertPrintViewModel $viewModel = null, ?DocumentSettings $documentSettings = null)
    {
        $this->viewModel = $viewModel ?? new OffertPrintViewModel();
        $this->documentSettings = $documentSettings ?? new DocumentSettings();
    }

    /**
     * @param array<string, mixed> $offert
     * @param array{
     *     header: array<string, mixed>,
     *     totals: array<string, mixed>,
     *     lines: array<int, mixed>,
     *     material_lines: array<int, mixed>,
     *     metadata: array<string, mixed>
     * } $snapshot
     * @param array{
     *     estimate?: array<string, mixed>,
     *     project?: array<string, mixed>,
     *     property?: array<string, mixed>,
     *     client?: array<string, mixed>
     * } $context
     */
    public function render(array $offert, array $snapshot, array $context = []): void
    {
        $context['document_settings'] = $this->documentSettings->get();
        $view = $this->viewModel->build($offert, $snapshot, $context);
        $offertId = (int) ($offert['id'] ?? 0);
        $estimateId = (int) ($offert['estimate_id'] ?? 0);
        $detailUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId);
        $listUrl = $estimateId > 0
            ? admin_url('admin.php?page=trn_offerts&estimate_id=' . $estimateId)
            : admin_url('admin.php?page=trn_offerts');

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
        echo '<a class="button" href="' . esc_url($detailUrl) . '">Back to offert detail</a>';
        echo '<a class="button" href="' . esc_url($listUrl) . '">Back to offerts list</a>';
        echo '<button type="button" class="button button-primary" onclick="window.print();">Print</button>';
        echo '</div>';

        echo '<div class="trn-print-doc">';
        echo '<h2>Printable offert document</h2>';
        echo '<h3>Commercial summary</h3>';
        $this->renderKeyValueTable($view['commercial_summary']);

        echo '<h3>Recipient / Customer</h3>';
        $this->renderKeyValueTable($view['recipient']);

        echo '<h3>Project / Object</h3>';
        $this->renderKeyValueTable($view['project_object']);

        echo '<h3>Totals</h3>';
        $this->renderTotalsTable($view['totals'], $view['currency']);

        echo '<h3>Labour lines</h3>';
        $this->renderLabourLinesTable($view['labour_lines'], $view['currency']);

        echo '<h3>Material lines</h3>';
        $this->renderMaterialLinesTable($view['material_lines'], $view['currency']);

        echo '<h3>Issuer / Company</h3>';
        $this->renderKeyValueTable($view['issuer']);

        echo '<h3>Contact / Payment details</h3>';
        $this->renderKeyValueTable($view['payment']);

        echo '<h3>Terms / Notes</h3>';
        $this->renderKeyValueTable($view['terms_notes']);
        echo '</div>';
    }

    /** @param array<string, string> $rows */
    private function renderKeyValueTable(array $rows): void
    {
        $hasValues = false;
        foreach ($rows as $value) {
            if ($value !== '') {
                $hasValues = true;
                break;
            }
        }

        if (! $hasValues) {
            echo '<p class="is-empty">No data.</p>';

            return;
        }

        echo '<table><tbody>';
        foreach ($rows as $label => $value) {
            if ($value === '') {
                continue;
            }

            echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * @param array<int, array{label: string, minor: string}> $rows
     */
    private function renderTotalsTable(array $rows, string $currency): void
    {
        echo '<table><thead><tr><th>Field</th><th>Amount</th><th>Minor</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $minor = $row['minor'];
            echo '<tr>';
            echo '<td>' . esc_html($row['label']) . '</td>';
            echo '<td>' . esc_html($this->formatMinorMoney($minor, $currency)) . '</td>';
            echo '<td>' . esc_html($minor !== '' ? $minor : '—') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * @param array<int, array{title: string, unit: string, quantity: string, hours: string, subtotal_minor: string}> $rows
     */
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

    /**
     * @param array<int, array{name: string, unit: string, quantity: string, subtotal_minor: string}> $rows
     */
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

        $major = ((float) $minor) / 100;

        return number_format($major, 2, '.', ' ') . ' ' . strtoupper($currency !== '' ? $currency : 'SEK');
    }
}
