<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class InvoicePrintRenderer
{
    private InvoicePrintViewModel $viewModel;

    public function __construct(?InvoicePrintViewModel $viewModel = null)
    {
        $this->viewModel = $viewModel ?? new InvoicePrintViewModel();
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array{
     *     header: array<string, mixed>,
     *     totals: array<string, mixed>,
     *     lines: array<int, mixed>,
     *     material_lines: array<int, mixed>,
     *     metadata: array<string, mixed>
     * } $snapshot
     * @param array{
     *     source_offert?: array<string, mixed>,
     *     source_estimate?: array<string, mixed>,
     *     project?: array<string, mixed>,
     *     property?: array<string, mixed>,
     *     client?: array<string, mixed>,
     *     payments?: array<int, mixed>,
     *     payment_summary?: array<string, mixed>,
     *     document_profile?: array<string, mixed>
     * } $context
     */
    public function render(array $invoice, array $snapshot, array $context = []): void
    {
        $view = $this->viewModel->build($invoice, $snapshot, $context);
        $invoiceId = (int) ($invoice['id'] ?? 0);
        $detailUrl = admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId);
        $listUrl = admin_url('admin.php?page=trn_invoices');

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
        echo '<a class="button" href="' . esc_url($detailUrl) . '">Back to invoice detail</a>';
        echo '<a class="button" href="' . esc_url($listUrl) . '">Back to invoices list</a>';
        echo '<button type="button" class="button button-primary" onclick="window.print();">Print</button>';
        echo '</div>';

        echo '<div class="trn-print-doc">';
        echo '<h2>Invoice</h2>';

        echo '<h3>1. Document header</h3>';
        $this->renderKeyValueTable($view['document'], [
            'document_number' => 'Document number',
            'version_no' => 'Version',
            'status' => 'Status',
            'issued_at' => 'Issued at',
            'payment_due_date' => 'Payment due date',
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

        echo '<h3>5. Invoice summary</h3>';
        $this->renderInvoiceSummaryTable($view['invoice_summary'], $view['currency']);

        echo '<h3>6. Labour lines</h3>';
        $this->renderLabourLinesTable($view['labour_lines'], $view['currency']);

        echo '<h3>7. Material lines</h3>';
        $this->renderMaterialLinesTable($view['material_lines'], $view['currency']);

        echo '<h3>8. Payment terms block</h3>';
        $this->renderKeyValueTable($view['payment_terms'], [
            'invoice_note' => 'Invoice note',
            'payment_terms_days' => 'Payment terms days',
            'payment_due_date' => 'Payment due date',
            'bankgiro' => 'Bankgiro',
            'plusgiro' => 'Plusgiro',
            'swish' => 'Swish',
            'iban' => 'IBAN',
            'bic' => 'BIC',
        ]);

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
    private function renderInvoiceSummaryTable(array $rows, string $currency): void
    {
        echo '<table><tbody>';
        $moneyLabels = [
            'labour_total' => 'Labour total',
            'materials_total' => 'Materials total',
            'subtotal_ex_vat' => 'Subtotal (ex VAT)',
            'vat' => 'VAT',
            'total_inc_vat' => 'Total (inc VAT)',
            'paid_total' => 'Paid total',
            'outstanding' => 'Outstanding',
        ];

        foreach ($moneyLabels as $key => $label) {
            $minor = $rows[$key] ?? '';
            echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($this->formatMinorMoney($minor, $currency)) . '</td></tr>';
        }

        echo '<tr><th>Computed status</th><td>' . esc_html($rows['computed_status'] !== '' ? $rows['computed_status'] : '—') . '</td></tr>';
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

    private function formatMinorMoney(string $minor, string $currency): string
    {
        if (! is_numeric($minor)) {
            return '—';
        }

        $major = ((float) $minor) / 100;

        return number_format($major, 2, '.', ' ') . ' ' . strtoupper($currency !== '' ? $currency : 'SEK');
    }
}
