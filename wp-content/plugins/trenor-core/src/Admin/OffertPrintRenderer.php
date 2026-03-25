<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class OffertPrintRenderer
{
    private OffertPrintViewModel $viewModel;

    public function __construct(?OffertPrintViewModel $viewModel = null)
    {
        $this->viewModel = $viewModel ?? new OffertPrintViewModel();
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
     *     client?: array<string, mixed>,
     *     document_profile?: array<string, mixed>
     * } $context
     */
    public function render(array $offert, array $snapshot, array $context = []): void
    {
        $view = $this->viewModel->build($offert, $snapshot, $context);
        $offertId = (int) ($offert['id'] ?? 0);
        $estimateId = (int) ($offert['estimate_id'] ?? 0);
        $detailUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId);
        $listUrl = $estimateId > 0
            ? admin_url('admin.php?page=trn_offerts&estimate_id=' . $estimateId)
            : admin_url('admin.php?page=trn_offerts');

        echo '<style>
        .trn-print-toolbar { margin: 12px 0 20px; display:flex; gap:8px; flex-wrap:wrap; }
        .trn-print-doc { max-width: 960px; }
        .trn-print-doc h2 { margin-top: 0; }
        .trn-print-doc h3 { margin: 20px 0 8px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
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
        echo '<h2>Offert</h2>';

        echo '<h3>1. Document header</h3>';
        $this->renderKeyValueTable($view['document'], [
            'document_number' => 'Document number',
            'version_no' => 'Version',
            'status' => 'Status',
            'issued_at' => 'Issued at',
            'offert_valid_until' => 'Offert valid until',
            'currency' => 'Currency',
            'vat_rate_percent' => 'VAT rate (%)',
        ]);

        echo '<h3>2. Issuer block</h3>';
        $this->renderKeyValueTable($view['issuer'], [
            'company_name' => 'Company name',
            'org_number' => 'Org number',
            'vat_number' => 'VAT number',
            'email' => 'Email',
            'phone' => 'Phone',
            'website' => 'Website',
            'address_line' => 'Address',
            'postal_code' => 'Postal code',
            'city' => 'City',
            'country' => 'Country',
            'bankgiro' => 'Bankgiro',
            'plusgiro' => 'Plusgiro',
            'swish' => 'Swish',
            'iban' => 'IBAN',
            'bic' => 'BIC',
        ]);

        echo '<h3>3. Recipient block</h3>';
        $this->renderKeyValueTable($view['recipient'], [
            'client_name' => 'Client name',
            'client_org_number' => 'Client org number',
            'client_email' => 'Client email',
            'client_phone' => 'Client phone',
        ]);

        echo '<h3>4. Project/property block</h3>';
        $this->renderKeyValueTable($view['project_object'], [
            'source_estimate_id' => 'Source estimate ID',
            'source_estimate_title' => 'Source estimate title',
            'project_name' => 'Project name',
            'project_code' => 'Project code',
            'property_name' => 'Property name',
            'property_address' => 'Property address',
            'property_city' => 'Property city',
            'property_postal_code' => 'Property postal code',
        ]);

        echo '<h3>5. Commercial summary</h3>';
        $this->renderCommercialSummaryTable($view['commercial_summary'], $view['currency']);

        echo '<h3>6. Labour lines</h3>';
        $this->renderLabourLinesTable($view['labour_lines'], $view['currency']);

        echo '<h3>7. Material lines</h3>';
        $this->renderMaterialLinesTable($view['material_lines'], $view['currency']);

        echo '<h3>8. Terms and acceptance block</h3>';
        $this->renderTermsAcceptanceTable($view['terms_acceptance']);
        echo '</div>';
    }

    /** @param array<string, string> $rows @param array<string, string> $labels */
    private function renderKeyValueTable(array $rows, array $labels = []): void
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
        foreach ($rows as $key => $value) {
            if ($value === '') {
                continue;
            }

            $label = $labels[$key] ?? $key;
            echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<string, string> $rows */
    private function renderCommercialSummaryTable(array $rows, string $currency): void
    {
        echo '<table><tbody>';
        $labels = [
            'labour_total' => 'Labour total',
            'materials_total' => 'Materials total',
            'subtotal_ex_vat' => 'Subtotal (ex VAT)',
            'vat' => 'VAT',
            'total_inc_vat' => 'Total (inc VAT)',
            'rot_eligible_labour' => 'ROT eligible labour',
            'preliminary_rot' => 'Preliminary ROT',
            'amount_before_rot' => 'Amount before ROT',
            'amount_after_preliminary_rot' => 'Amount after preliminary ROT',
        ];

        foreach ($labels as $key => $label) {
            $minor = $rows[$key] ?? '';
            echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($this->formatMinorMoney($minor, $currency)) . '</td></tr>';
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

        echo '<table><thead><tr><th>Title</th><th>Unit</th><th>Quantity</th><th>Hours</th><th>Subtotal</th></tr></thead><tbody>';
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

        echo '<table><thead><tr><th>Name</th><th>Unit</th><th>Quantity</th><th>Subtotal</th></tr></thead><tbody>';
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

    /** @param array<string, string> $rows */
    private function renderTermsAcceptanceTable(array $rows): void
    {
        echo '<table><tbody>';
        echo '<tr><th>Offert note</th><td>' . esc_html($rows['offert_note'] !== '' ? $rows['offert_note'] : '—') . '</td></tr>';
        echo '<tr><th>Offert valid days</th><td>' . esc_html($rows['offert_valid_days'] !== '' ? $rows['offert_valid_days'] : '—') . '</td></tr>';
        echo '<tr><th>Offert valid until</th><td>' . esc_html($rows['offert_valid_until'] !== '' ? $rows['offert_valid_until'] : '—') . '</td></tr>';
        echo '<tr><th>Accepted by</th><td>__________________________________</td></tr>';
        echo '<tr><th>Accepted at</th><td>__________________________________</td></tr>';
        echo '<tr><th>Signature</th><td>__________________________________</td></tr>';
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
