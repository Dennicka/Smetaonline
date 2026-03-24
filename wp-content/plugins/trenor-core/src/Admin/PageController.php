<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

use RuntimeException;
use Trenor\Core\Database\RepositoryFactory;
use Trenor\Core\Domain\Exception\PaymentRegistrationException;
use Trenor\Core\Domain\Service\DocumentSequenceGenerator;
use Trenor\Core\Domain\Exception\EstimateCalculationException;
use Trenor\Core\Domain\Service\EstimateCalculator;
use Trenor\Core\Domain\Service\EstimateSnapshotService;
use Trenor\Core\Domain\Service\EstimateTotalsCalculator;
use Trenor\Core\Domain\Service\InvoicePaymentSummaryCalculator;
use Trenor\Core\Domain\Service\CreditNoteFromInvoiceService;
use Trenor\Core\Domain\Service\InvoiceFromOffertService;
use Trenor\Core\Domain\Service\OffertFromEstimateService;
use Trenor\Core\Domain\Service\PaymentRecorderService;
use Trenor\Core\Domain\Service\DocumentSettings;

final class PageController
{
    private RepositoryFactory $factory;

    public function __construct(RepositoryFactory $factory)
    {
        $this->factory = $factory;
    }

    public function handleRequests(): void
    {
        if (! is_admin() || ! current_user_can('read')) {
            return;
        }

        $postPayload = filter_input_array(INPUT_POST) ?: [];
        if (! is_array($postPayload) || $postPayload === []) {
            return;
        }

        $entity = sanitize_key($this->postValue($postPayload, 'trn_entity'));
        $action = sanitize_key($this->postValue($postPayload, 'trn_action'));

        if ($entity === '' || $action === '') {
            return;
        }

        check_admin_referer('trn_' . $entity . '_' . $action);

        if (! current_user_can($this->requiredCapability($entity, $action))) {
            wp_die(esc_html__('You do not have permissions to perform this action.', 'trenor-core'));
        }

        $id = (int) $this->postValue($postPayload, 'id');

        if ($entity === 'client') {
            $this->handleEntity($this->factory->clients(), 'trn_clients', $action, $id, $this->collectData($postPayload, ['name', 'org_number', 'email', 'phone']));
        }

        if ($entity === 'property') {
            $this->handleEntity($this->factory->properties(), 'trn_properties', $action, $id, $this->collectData($postPayload, ['client_id', 'name', 'address_line', 'city', 'postal_code']));
        }

        if ($entity === 'project') {
            $this->handleEntity($this->factory->projects(), 'trn_projects', $action, $id, $this->collectData($postPayload, ['property_id', 'name', 'code']));
        }

        if ($entity === 'room') {
            $this->handleEntity($this->factory->rooms(), 'trn_rooms', $action, $id, $this->collectData($postPayload, ['project_id', 'name', 'floor']));
        }

        if ($entity === 'work_item') {
            $this->handleEntity($this->factory->workItems(), 'trn_work_items', $action, $id, $this->collectData($postPayload, ['category_id', 'name_ru', 'name_sv', 'unit_code', 'norm_slow_per_hour', 'norm_medium_per_hour', 'norm_fast_per_hour', 'default_material_consumption_note', 'is_rot_eligible', 'is_active', 'sort_order']));
        }

        if ($entity === 'material') {
            $this->handleEntity($this->factory->materials(), 'trn_materials', $action, $id, $this->collectData($postPayload, ['category_id', 'name_ru', 'name_sv', 'unit_code', 'coverage_per_unit', 'buy_price_minor', 'sell_price_minor', 'currency', 'sku', 'is_active']));
        }

        if ($entity === 'estimate') {
            $this->handleEntity($this->factory->estimates(), 'trn_estimates', $action, $id, $this->collectData($postPayload, ['project_id', 'title', 'status', 'currency', 'vat_rate_percent', 'labour_rate_minor', 'notes']));
        }

        if ($entity === 'estimate_line') {
            $this->handleEstimateLine($action, $id, $postPayload);
        }

        if ($entity === 'estimate_material_line') {
            $this->handleEstimateMaterialLine($action, $id, $postPayload);
        }

        if ($entity === 'estimate_recalculate' && $action === 'recalculate') {
            $this->recalculateEstimate((int) $this->postValue($postPayload, 'estimate_id'));
        }

        if ($entity === 'offert') {
            $this->handleOffert($action, $id, $postPayload);
        }

        if ($entity === 'invoice') {
            $this->handleInvoice($action, $postPayload);
        }

        if ($entity === 'invoice_payment') {
            $this->handleInvoicePayment($action, $postPayload);
        }

        if ($entity === 'credit_note') {
            $this->handleCreditNote($action, $postPayload);
        }

        if ($entity === 'document_settings' && $action === 'save') {
            $this->handleDocumentSettingsSave($postPayload);
        }

        if ($entity === 'document_profile_settings' && $action === 'save') {
            $this->handleDocumentProfileSettingsSave($postPayload);
        }
    }

    public function renderDashboard(): void
    {
        echo '<div class="wrap"><h1>Smeta / Dashboard</h1>';
        $this->renderAdminNoticeFromRequest();
        echo '<p>Core plugin active.</p></div>';
    }

    public function renderClients(): void
    {
        if (! current_user_can('trn_manage_clients')) {
            wp_die('Forbidden');
        }
        $this->renderEntityPage('Клиенты', 'client', ['name', 'org_number', 'email', 'phone'], $this->factory->clients()->all());
    }

    public function renderProperties(): void
    {
        if (! current_user_can('trn_manage_projects')) {
            wp_die('Forbidden');
        }
        $this->renderEntityPage('Объекты', 'property', ['client_id', 'name', 'address_line', 'city', 'postal_code'], $this->factory->properties()->all());
    }

    public function renderProjects(): void
    {
        if (! current_user_can('trn_manage_projects')) {
            wp_die('Forbidden');
        }
        $this->renderEntityPage('Проекты', 'project', ['property_id', 'name', 'code'], $this->factory->projects()->all());
    }

    public function renderRooms(): void
    {
        if (! current_user_can('trn_manage_projects')) {
            wp_die('Forbidden');
        }
        $this->renderEntityPage('Помещения', 'room', ['project_id', 'name', 'floor'], $this->factory->rooms()->all());
    }

    public function renderWorkItems(): void
    {
        if (! current_user_can('trn_manage_catalogs')) {
            wp_die('Forbidden');
        }
        $this->renderEntityPage('Работы', 'work_item', ['category_id', 'name_ru', 'name_sv', 'unit_code', 'norm_slow_per_hour', 'norm_medium_per_hour', 'norm_fast_per_hour', 'default_material_consumption_note', 'is_rot_eligible', 'is_active', 'sort_order'], $this->factory->workItems()->all(), 'is_active');
    }

    public function renderMaterials(): void
    {
        if (! current_user_can('trn_manage_catalogs')) {
            wp_die('Forbidden');
        }
        $this->renderEntityPage('Материалы', 'material', ['category_id', 'name_ru', 'name_sv', 'unit_code', 'coverage_per_unit', 'buy_price_minor', 'sell_price_minor', 'currency', 'sku', 'is_active'], $this->factory->materials()->all(), 'is_active');
    }

    public function renderEstimates(): void
    {
        if (! current_user_can('trn_manage_estimates')) {
            wp_die('Forbidden');
        }

        $selectedEstimateId = filter_input(INPUT_GET, 'estimate_id', FILTER_VALIDATE_INT);
        $selectedEstimateId = $selectedEstimateId !== false && $selectedEstimateId !== null ? (int) $selectedEstimateId : 0;
        $selectedSnapshotId = filter_input(INPUT_GET, 'snapshot_id', FILTER_VALIDATE_INT);
        $selectedSnapshotId = $selectedSnapshotId !== false && $selectedSnapshotId !== null ? (int) $selectedSnapshotId : 0;
        $estimates = $this->factory->estimates()->all();

        echo '<div class="wrap"><h1>Сметы</h1>';
        $this->renderAdminNoticeFromRequest();
        $this->renderCreateEstimateForm();

        echo '<h2>Список смет</h2><table class="widefat striped"><thead><tr><th>ID</th><th>project_id</th><th>title</th><th>status</th><th>currency</th><th>vat_rate_percent</th><th>labour_rate_minor</th><th>calculated_at</th><th>Actions</th></tr></thead><tbody>';
        foreach ($estimates as $estimate) {
            $url = admin_url('admin.php?page=trn_estimates&estimate_id=' . (int) $estimate['id']);
            echo '<tr>';
            echo '<td>' . esc_html((string) $estimate['id']) . '</td>';
            echo '<td>' . esc_html((string) $estimate['project_id']) . '</td>';
            echo '<td>' . esc_html((string) $estimate['title']) . '</td>';
            echo '<td>' . esc_html((string) $estimate['status']) . '</td>';
            echo '<td>' . esc_html((string) $estimate['currency']) . '</td>';
            echo '<td>' . esc_html((string) $estimate['vat_rate_percent']) . '</td>';
            echo '<td>' . esc_html((string) $estimate['labour_rate_minor']) . '</td>';
            echo '<td>' . esc_html((string) $estimate['calculated_at']) . '</td>';
            echo '<td><a class="button" href="' . esc_url($url) . '">Открыть</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        if ($selectedEstimateId > 0) {
            $this->renderEstimateBuilder($selectedEstimateId);
            if ($selectedSnapshotId > 0) {
                $snapshot = $this->factory->estimateSnapshots()->find($selectedSnapshotId);
                if ($snapshot === null) {
                    $this->renderInlineErrorNotice('Snapshot not found.');
                } elseif ((int) ($snapshot['estimate_id'] ?? 0) !== $selectedEstimateId) {
                    $this->renderInlineErrorNotice('Snapshot does not belong to the selected estimate.');
                } else {
                    $this->renderEstimateSnapshotDetail($snapshot);
                }
            }
        }

        echo '</div>';
    }

    public function renderSettings(): void
    {
        if (! current_user_can('trn_manage_backups')) {
            wp_die('Forbidden');
        }

        $settings = (new DocumentSettings())->get();

        echo '<div class="wrap"><h1>Настройки</h1>';
        $this->renderAdminNoticeFromRequest();
        echo '<p>Версия ядра: ' . esc_html((string) get_option('trn_core_version', 'unknown')) . '</p>';
        echo '<h2>Business document settings</h2>';
        echo '<form method="post">';
        wp_nonce_field('trn_document_settings_save');
        echo '<input type="hidden" name="trn_entity" value="document_settings">';
        echo '<input type="hidden" name="trn_action" value="save">';

        $this->renderDocumentSettingsFieldset('Company', [
            'company_name' => 'Company name',
            'company_legal_name' => 'Company legal name',
            'org_number' => 'Org number',
            'vat_number' => 'VAT number',
            'email' => 'Email',
            'phone' => 'Phone',
            'website' => 'Website',
        ], $settings);

        $this->renderDocumentSettingsFieldset('Address', [
            'address_line_1' => 'Address line 1',
            'address_line_2' => 'Address line 2',
            'postal_code' => 'Postal code',
            'city' => 'City',
            'country' => 'Country',
        ], $settings);

        $this->renderDocumentSettingsFieldset('Payment / bank', [
            'bank_name' => 'Bank name',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'plusgiro' => 'Plusgiro',
            'bankgiro' => 'Bankgiro',
            'swish' => 'Swish',
            'payment_terms_days' => 'Payment terms days',
        ], $settings);

        $this->renderDocumentSettingsTextareaFieldset('Default document text blocks', [
            'offert_intro_text' => 'Offert intro text',
            'offert_footer_text' => 'Offert footer text',
            'invoice_footer_text' => 'Invoice footer text',
            'credit_note_footer_text' => 'Credit note footer text',
        ], $settings);

        submit_button('Save settings');
        echo '</form>';

        $documentProfile = (new DocumentProfileProvider())->get();
        echo '<h2>Document profile / Företagsprofil / Профиль документов</h2>';
        echo '<form method="post">';
        wp_nonce_field('trn_document_profile_settings_save');
        echo '<input type="hidden" name="trn_entity" value="document_profile_settings">';
        echo '<input type="hidden" name="trn_action" value="save">';

        $this->renderDocumentProfileFieldset('Issuer / Company', [
            'company_name' => 'Company name',
            'org_number' => 'Org number',
            'vat_number' => 'VAT number',
            'email' => 'Email',
            'phone' => 'Phone',
            'website' => 'Website',
            'address_line' => 'Address line',
            'postal_code' => 'Postal code',
            'city' => 'City',
            'country' => 'Country',
            'bankgiro' => 'Bankgiro',
            'plusgiro' => 'Plusgiro',
            'swish' => 'Swish',
            'iban' => 'IBAN',
            'bic' => 'BIC',
        ], $documentProfile);

        $this->renderDocumentProfileFieldset('Commercial terms', [
            'payment_terms_days' => 'Payment terms days',
            'offert_valid_days' => 'Offert valid days',
        ], $documentProfile);

        $this->renderDocumentProfileTextareaFieldset('Document notes', [
            'invoice_note' => 'Invoice note',
            'offert_note' => 'Offert note',
        ], $documentProfile);

        submit_button('Save document profile');
        echo '</form></div>';
    }

    public function renderOfferts(): void
    {
        if (! current_user_can('trn_issue_offerts')) {
            wp_die('Forbidden');
        }

        $estimateFilter = filter_input(INPUT_GET, 'estimate_id', FILTER_UNSAFE_RAW);
        $statusFilter = filter_input(INPUT_GET, 'status', FILTER_UNSAFE_RAW);
        $documentNumberFilter = filter_input(INPUT_GET, 'document_number', FILTER_UNSAFE_RAW);
        $offertId = filter_input(INPUT_GET, 'offert_id', FILTER_VALIDATE_INT);
        $offertId = $offertId !== false && $offertId !== null ? (int) $offertId : 0;
        $view = filter_input(INPUT_GET, 'view', FILTER_UNSAFE_RAW);
        $view = is_string($view) ? sanitize_key($view) : '';

        if ($view === 'print' && $offertId > 0) {
            echo '<div class="wrap">';
            $this->renderOffertPrint($offertId);
            echo '</div>';

            return;
        }

        $filter = new OffertListFilter();
        $allOfferts = $this->factory->offerts()->all();
        $offerts = $filter->apply($allOfferts, $estimateFilter, $statusFilter, $documentNumberFilter);
        $hasActiveFilters = $filter->isEstimateIdFilterActive($estimateFilter)
            || $filter->isStatusFilterActive($statusFilter)
            || $filter->isDocumentNumberFilterActive($documentNumberFilter);

        echo '<div class="wrap"><h1>Offerter / Offerts / Оферты</h1>';
        $this->renderAdminNoticeFromRequest();
        $this->renderOffertFilterForm($estimateFilter, $statusFilter, $documentNumberFilter);
        echo '<p><strong>Total rows:</strong> ' . esc_html((string) count($offerts));
        if ($hasActiveFilters) {
            echo ' <em>(filtered results)</em>';
        }
        echo '</p>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>estimate_id</th><th>document_number</th><th>version_no</th><th>status</th><th>total_inc_vat_minor</th><th>issued_at</th><th>Actions</th></tr></thead><tbody>';
        foreach ($offerts as $offert) {
            $viewUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . (int) $offert['id']);
            $printUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . (int) $offert['id'] . '&view=print');
            $estimateUrl = admin_url('admin.php?page=trn_estimates&estimate_id=' . (int) $offert['estimate_id']);
            echo '<tr>';
            echo '<td>' . esc_html((string) $offert['id']) . '</td>';
            echo '<td>' . esc_html((string) $offert['estimate_id']) . '</td>';
            echo '<td>' . esc_html((string) $offert['document_number']) . '</td>';
            echo '<td>' . esc_html((string) $offert['version_no']) . '</td>';
            echo '<td>' . esc_html((string) $offert['status']) . '</td>';
            echo '<td>' . esc_html($this->formatMinorMoney($offert['total_inc_vat_minor'] ?? null, (string) ($offert['currency'] ?? 'SEK'))) . '</td>';
            echo '<td>' . esc_html((string) $offert['issued_at']) . '</td>';
            echo '<td><a class="button" href="' . esc_url($viewUrl) . '">Open/View</a> ';
            echo '<a class="button" href="' . esc_url($printUrl) . '" style="margin-left:6px;">Print / Printable view</a> ';
            echo '<a class="button" href="' . esc_url($estimateUrl) . '" style="margin-left:6px;">Open estimate</a> ';
            $this->renderOffertActionForm((int) $offert['id'], 'accept', 'Accept');
            $this->renderOffertActionForm((int) $offert['id'], 'reject', 'Reject');
            if (current_user_can('trn_archive_records')) {
                $this->renderOffertActionForm((int) $offert['id'], 'archive', 'Archive');
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        if ($offertId > 0) {
            $this->renderOffertDetail($offertId);
        }
        echo '</div>';
    }

    public function renderAuditLog(): void
    {
        $rows = $this->factory->auditLogs();
        echo '<div class="wrap"><h1>Журнал</h1>';
        $this->renderAdminNoticeFromRequest();
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>ID</th><th>Entity</th><th>Entity ID</th><th>Action</th><th>Actor</th><th>At</th><th>Changes</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html((string) $row['id']) . '</td><td>' . esc_html((string) $row['entity_type']) . '</td><td>' . esc_html((string) $row['entity_id']) . '</td><td>' . esc_html((string) $row['action']) . '</td><td>' . esc_html((string) $row['actor_user_id']) . '</td><td>' . esc_html((string) $row['created_at']) . '</td><td><code>' . esc_html((string) $row['changes_json']) . '</code></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function renderInvoices(): void
    {
        if (! current_user_can('trn_issue_invoices')) {
            wp_die('Forbidden');
        }

        $invoiceId = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);
        $invoiceId = $invoiceId !== false && $invoiceId !== null ? (int) $invoiceId : 0;
        $view = filter_input(INPUT_GET, 'view', FILTER_UNSAFE_RAW);
        $view = is_string($view) ? sanitize_key($view) : '';

        if ($view === 'print' && $invoiceId > 0) {
            echo '<div class="wrap">';
            $this->renderInvoicePrint($invoiceId);
            echo '</div>';

            return;
        }

        $rawFilters = [
            'offert_id' => filter_input(INPUT_GET, 'offert_id', FILTER_UNSAFE_RAW),
            'estimate_id' => filter_input(INPUT_GET, 'estimate_id', FILTER_UNSAFE_RAW),
            'status' => filter_input(INPUT_GET, 'status', FILTER_UNSAFE_RAW),
            'document_number' => filter_input(INPUT_GET, 'document_number', FILTER_UNSAFE_RAW),
        ];
        $invoiceFilter = new InvoiceListFilter();
        $allInvoices = $this->factory->invoices()->all();
        $invoices = $invoiceFilter->apply($allInvoices, $rawFilters);
        $formFilters = $invoiceFilter->normalizedForForm($rawFilters);
        $hasActiveFilters = $invoiceFilter->hasActiveFilters($rawFilters);
        $rowBuilder = new InvoiceRegisterRowBuilder(new InvoicePaymentSummaryCalculator());
        $summaryBuilder = new InvoiceRegisterSummaryBuilder();
        $invoicePayments = $this->factory->invoicePayments();
        $registerRows = [];

        foreach ($invoices as $invoice) {
            $invoiceId = (int) ($invoice['id'] ?? 0);
            $paymentRows = $invoiceId > 0 ? $invoicePayments->byInvoice($invoiceId) : [];
            $registerRows[] = $rowBuilder->build($invoice, $paymentRows);
        }

        $summary = $summaryBuilder->build($registerRows);

        echo '<div class="wrap"><h1>Fakturor / Invoices / Фактуры</h1>';
        $this->renderAdminNoticeFromRequest();
        $this->renderInvoiceFilterForm($formFilters);
        echo '<p><strong>Total rows:</strong> ' . esc_html((string) count($registerRows));
        if ($hasActiveFilters) {
            echo ' <em>(filtered results)</em>';
        }
        echo '</p>';
        $this->renderInvoiceLedgerSummary($summary, $this->invoiceListCurrency($invoices));

        echo '<table class="widefat striped"><thead><tr><th>id</th><th>offert_id</th><th>estimate_id</th><th>document_number</th><th>version_no</th><th>stored_status</th><th>computed_status</th><th>total_inc_vat</th><th>paid_total_minor</th><th>outstanding_minor</th><th>payment_count</th><th>issued_at</th><th>Actions</th></tr></thead><tbody>';
        if ($registerRows === []) {
            echo '<tr><td colspan="13">No invoices found for current filters.</td></tr>';
        }
        foreach ($registerRows as $row) {
            $invoiceIdValue = (int) ($row['id'] ?? 0);
            $offertIdValue = (int) ($row['offert_id'] ?? 0);
            $viewUrl = admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceIdValue);
            $printUrl = admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceIdValue . '&view=print');
            $offertUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . $offertIdValue);
            $paymentsUrl = admin_url('admin.php?page=trn_payments&invoice_id=' . $invoiceIdValue);
            $currency = (string) ($row['currency'] ?? 'SEK');
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['offert_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['estimate_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['document_number'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['version_no'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['stored_status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['computed_status'] ?? '')) . '</td>';
            echo '<td>' . esc_html($this->formatMinorMoney($row['total_inc_vat_minor'] ?? 0, $currency)) . '</td>';
            echo '<td>' . esc_html((string) ($row['paid_total_minor'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($row['outstanding_minor'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($row['payment_count'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($row['issued_at'] ?? '')) . '</td>';
            echo '<td><a class="button" href="' . esc_url($viewUrl) . '">Open/View</a>';
            echo '<a class="button" href="' . esc_url($printUrl) . '" style="margin-left:6px;">Print / Printable view</a>';
            echo '<a class="button" href="' . esc_url($paymentsUrl) . '" style="margin-left:6px;">Open payments register</a>';
            if ($offertIdValue > 0) {
                echo '<a class="button" href="' . esc_url($offertUrl) . '" style="margin-left:6px;">Open source offert</a>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        if ($invoiceId > 0) {
            $this->renderInvoiceDetail($invoiceId);
        }

        echo '</div>';
    }

    public function renderPayments(): void
    {
        if (! current_user_can('trn_record_payments')) {
            wp_die('Forbidden');
        }

        $rawFilters = [
            'invoice_id' => filter_input(INPUT_GET, 'invoice_id', FILTER_UNSAFE_RAW),
            'currency' => filter_input(INPUT_GET, 'currency', FILTER_UNSAFE_RAW),
            'method' => filter_input(INPUT_GET, 'method', FILTER_UNSAFE_RAW),
            'reference' => filter_input(INPUT_GET, 'reference', FILTER_UNSAFE_RAW),
        ];

        $filter = new PaymentListFilter();
        $formFilters = $filter->normalizedForForm($rawFilters);
        $hasActiveFilters = $filter->hasActiveFilters($rawFilters);
        $payments = $filter->apply($this->paymentRowsForList(), $rawFilters);
        $summary = (new PaymentListSummary())->summarize($payments);
        $calculator = new InvoicePaymentSummaryCalculator();
        $invoicePayments = $this->factory->invoicePayments();
        $invoiceRepository = $this->factory->invoices();

        echo '<div class="wrap"><h1>Betalningar / Payments / Оплаты</h1>';
        $this->renderAdminNoticeFromRequest();
        $this->renderPaymentFilterForm($formFilters);

        echo '<p><strong>Total rows:</strong> ' . esc_html((string) ($summary['total_rows'] ?? 0));
        if ($hasActiveFilters) {
            echo ' <em>(filtered results)</em>';
        }
        echo '</p>';
        echo '<p><strong>Total amount_minor:</strong> ' . esc_html((string) ($summary['total_amount_minor'] ?? 0)) . '</p>';
        echo '<p><strong>Unique invoices:</strong> ' . esc_html((string) ($summary['unique_invoice_count'] ?? 0)) . '</p>';
        $methodCounts = is_array($summary['method_counts'] ?? null) ? $summary['method_counts'] : [];
        echo '<p><strong>Rows by method:</strong> manual=' . esc_html((string) ($methodCounts['manual'] ?? 0));
        echo ', bank=' . esc_html((string) ($methodCounts['bank'] ?? 0));
        echo ', swish=' . esc_html((string) ($methodCounts['swish'] ?? 0));
        echo ', other=' . esc_html((string) ($methodCounts['other'] ?? 0)) . '</p>';

        echo '<table class="widefat striped"><thead><tr><th>id</th><th>invoice_id</th><th>payment_date</th><th>amount_minor</th><th>currency</th><th>method</th><th>reference</th><th>created_at</th><th>source_invoice_total_inc_vat</th><th>source_invoice_status</th><th>source_invoice_computed_status</th><th>source_invoice_outstanding_minor</th><th>Actions</th></tr></thead><tbody>';
        if ($payments === []) {
            echo '<tr><td colspan="13">No payments found for current filters.</td></tr>';
        }

        foreach ($payments as $payment) {
            $invoiceId = (int) ($payment['invoice_id'] ?? 0);
            $invoiceUrl = admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId);
            $sourceInvoice = $invoiceId > 0 ? $invoiceRepository->find($invoiceId) : null;
            $sourceInvoicePayments = $invoiceId > 0 ? $invoicePayments->byInvoice($invoiceId) : [];
            $sourceInvoiceSummary = is_array($sourceInvoice)
                ? $calculator->calculate($sourceInvoice, $sourceInvoicePayments)
                : ['computed_status' => '', 'outstanding_minor' => null];
            $sourceInvoiceCurrency = (string) ($sourceInvoice['currency'] ?? ($payment['currency'] ?? 'SEK'));

            echo '<tr>';
            echo '<td>' . esc_html((string) ($payment['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['invoice_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['payment_date'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['amount_minor'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['currency'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['method'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['reference'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['created_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html(is_array($sourceInvoice) ? $this->formatMinorMoney($sourceInvoice['total_inc_vat_minor'] ?? null, $sourceInvoiceCurrency) : '—') . '</td>';
            echo '<td>' . esc_html(is_array($sourceInvoice) ? (string) ($sourceInvoice['status'] ?? '') : '—') . '</td>';
            echo '<td>' . esc_html((string) ($sourceInvoiceSummary['computed_status'] ?? '')) . '</td>';
            echo '<td>' . esc_html(is_array($sourceInvoice) ? $this->formatMinorMoney($sourceInvoiceSummary['outstanding_minor'] ?? null, $sourceInvoiceCurrency) : '—') . '</td>';
            echo '<td><a class="button" href="' . esc_url($invoiceUrl) . '">Open invoice</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    public function renderDossier(): void
    {
        if (! current_user_can('read')) {
            wp_die('Forbidden');
        }

        $projectIdRaw = filter_input(INPUT_GET, 'project_id', FILTER_UNSAFE_RAW);
        $projectId = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
        $projectId = $projectId !== false && $projectId !== null ? (int) $projectId : 0;
        $hasFilter = $projectIdRaw !== null;

        echo '<div class="wrap"><h1>Dossier / Timeline / Досье</h1>';
        $this->renderAdminNoticeFromRequest();
        $this->renderDossierFilterForm($projectId);

        if (! $hasFilter || $projectId <= 0) {
            $this->renderEmptyState('Enter project_id to open dossier.');
            echo '</div>';

            return;
        }

        $project = $this->factory->projects()->find($projectId);
        if (! is_array($project)) {
            $this->renderInlineErrorNotice('Project not found for selected project_id.');
            echo '</div>';

            return;
        }

        $propertyId = (int) ($project['property_id'] ?? 0);
        $property = $propertyId > 0 ? $this->factory->properties()->find($propertyId) : null;
        $property = is_array($property) ? $property : [];
        $clientId = (int) ($property['client_id'] ?? 0);
        $client = $clientId > 0 ? $this->factory->clients()->find($clientId) : null;
        $client = is_array($client) ? $client : [];

        $estimates = [];
        foreach ($this->factory->estimates()->all() as $estimate) {
            if ((int) ($estimate['project_id'] ?? 0) === $projectId) {
                $estimates[] = $estimate;
            }
        }

        $paymentsByInvoiceId = [];
        $allInvoices = $this->factory->invoices()->all();
        foreach ($allInvoices as $invoice) {
            $invoiceId = (int) ($invoice['id'] ?? 0);
            if ($invoiceId > 0) {
                $paymentsByInvoiceId[$invoiceId] = $this->factory->invoicePayments()->byInvoice($invoiceId);
            }
        }

        $builder = new ProjectDossierBuilder(new InvoicePaymentSummaryCalculator());
        $normalizer = new ProjectDossierViewNormalizer();
        $dossier = $normalizer->normalize($builder->build(
            $project,
            $property,
            $client,
            $estimates,
            $this->factory->offerts()->all(),
            $allInvoices,
            $paymentsByInvoiceId
        ));

        echo '<h2>Project summary</h2>';
        $this->renderKeyValueTable([
            'id' => $dossier['project']['id'] ?? '',
            'name' => $dossier['project']['name'] ?? '',
            'code' => $dossier['project']['code'] ?? '',
            'property_id' => $dossier['project']['property_id'] ?? '',
        ]);

        echo '<h2>Property summary</h2>';
        $this->renderKeyValueTable([
            'id' => $dossier['property']['id'] ?? '',
            'name' => $dossier['property']['name'] ?? '',
            'address_line' => $dossier['property']['address_line'] ?? '',
            'city' => $dossier['property']['city'] ?? '',
            'postal_code' => $dossier['property']['postal_code'] ?? '',
            'client_id' => $dossier['property']['client_id'] ?? '',
        ]);

        echo '<h2>Client summary</h2>';
        $this->renderKeyValueTable([
            'id' => $dossier['client']['id'] ?? '',
            'name' => $dossier['client']['name'] ?? '',
            'org_number' => $dossier['client']['org_number'] ?? '',
            'email' => $dossier['client']['email'] ?? '',
            'phone' => $dossier['client']['phone'] ?? '',
        ]);

        $this->renderDossierEstimatesTable(is_array($dossier['estimates'] ?? null) ? $dossier['estimates'] : []);
        $this->renderDossierOffertsTable(is_array($dossier['offerts'] ?? null) ? $dossier['offerts'] : []);
        $this->renderDossierInvoicesTable(is_array($dossier['invoices'] ?? null) ? $dossier['invoices'] : []);
        $this->renderDossierPaymentsTable(is_array($dossier['payments'] ?? null) ? $dossier['payments'] : []);

        echo '<h2>Dossier summary</h2>';
        $this->renderKeyValueTable([
            'estimates_count' => (string) (($dossier['summary']['estimates_count'] ?? 0)),
            'offerts_count' => (string) (($dossier['summary']['offerts_count'] ?? 0)),
            'invoices_count' => (string) (($dossier['summary']['invoices_count'] ?? 0)),
            'payments_count' => (string) (($dossier['summary']['payments_count'] ?? 0)),
            'invoiced_total_minor' => (string) (($dossier['summary']['invoiced_total_minor'] ?? 0)),
            'paid_total_minor' => (string) (($dossier['summary']['paid_total_minor'] ?? 0)),
            'outstanding_total_minor' => (string) (($dossier['summary']['outstanding_total_minor'] ?? 0)),
            'fully_paid_invoices_count' => (string) (($dossier['summary']['fully_paid_invoices_count'] ?? 0)),
            'partially_paid_invoices_count' => (string) (($dossier['summary']['partially_paid_invoices_count'] ?? 0)),
            'archived_invoices_count' => (string) (($dossier['summary']['archived_invoices_count'] ?? 0)),
        ]);

        echo '</div>';
    }

    public function renderCreditNotes(): void
    {
        if (! current_user_can('trn_issue_credit_notes')) {
            wp_die('Forbidden');
        }

        $creditNoteId = filter_input(INPUT_GET, 'credit_note_id', FILTER_VALIDATE_INT);
        $creditNoteId = $creditNoteId !== false && $creditNoteId !== null ? (int) $creditNoteId : 0;
        $view = filter_input(INPUT_GET, 'view', FILTER_UNSAFE_RAW);
        $view = is_string($view) ? sanitize_key($view) : '';

        if ($view === 'print' && $creditNoteId > 0) {
            echo '<div class="wrap">';
            $this->renderCreditNotePrint($creditNoteId);
            echo '</div>';

            return;
        }

        $rawFilters = [
            'invoice_id' => filter_input(INPUT_GET, 'invoice_id', FILTER_UNSAFE_RAW),
            'status' => filter_input(INPUT_GET, 'status', FILTER_UNSAFE_RAW),
            'document_number' => filter_input(INPUT_GET, 'document_number', FILTER_UNSAFE_RAW),
        ];
        $filter = new CreditNoteListFilter();
        $creditNotes = $filter->apply($this->factory->creditNotes()->all(), $rawFilters);
        $formFilters = $filter->normalizedForForm($rawFilters);

        echo '<div class="wrap"><h1>Kreditnotor / Credit Notes / Кредит-ноты</h1>';
        $this->renderAdminNoticeFromRequest();
        $this->renderCreditNoteFilterForm($formFilters);

        echo '<table class="widefat striped"><thead><tr><th>id</th><th>invoice_id</th><th>document_number</th><th>version_no</th><th>status</th><th>total_inc_vat_minor</th><th>issued_at</th><th>Actions</th></tr></thead><tbody>';
        if ($creditNotes === []) {
            echo '<tr><td colspan="8">No credit notes found for current filters.</td></tr>';
        }

        foreach ($creditNotes as $creditNote) {
            $id = (int) ($creditNote['id'] ?? 0);
            $invoiceId = (int) ($creditNote['invoice_id'] ?? 0);
            $viewUrl = admin_url('admin.php?page=trn_credit_notes&credit_note_id=' . $id);
            $printUrl = admin_url('admin.php?page=trn_credit_notes&credit_note_id=' . $id . '&view=print');
            $invoiceUrl = admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId);
            $currency = (string) ($creditNote['currency'] ?? 'SEK');

            echo '<tr>';
            echo '<td>' . esc_html((string) ($creditNote['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) $invoiceId) . '</td>';
            echo '<td>' . esc_html((string) ($creditNote['document_number'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($creditNote['version_no'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($creditNote['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html($this->formatMinorMoney($creditNote['total_inc_vat_minor'] ?? null, $currency)) . '</td>';
            echo '<td>' . esc_html((string) ($creditNote['issued_at'] ?? '')) . '</td>';
            echo '<td><a class="button" href="' . esc_url($viewUrl) . '">Open/View</a>';
            echo '<a class="button" href="' . esc_url($printUrl) . '" style="margin-left:6px;">Print / Printable view</a>';
            if ($invoiceId > 0) {
                echo '<a class="button" href="' . esc_url($invoiceUrl) . '" style="margin-left:6px;">Open source invoice</a>';
            }
            if (current_user_can('trn_archive_records') && (string) ($creditNote['status'] ?? '') !== 'archived') {
                echo '<form method="post" style="display:inline-block; margin-left:6px;">';
                wp_nonce_field('trn_credit_note_archive');
                echo '<input type="hidden" name="trn_entity" value="credit_note">';
                echo '<input type="hidden" name="trn_action" value="archive">';
                echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '">';
                submit_button('Archive', 'secondary', 'submit', false);
                echo '</form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        if ($creditNoteId > 0) {
            $this->renderCreditNoteDetail($creditNoteId);
        }
        echo '</div>';
    }

    /** @param array<int, string> $fields @param array<int, array<string,mixed>> $rows */
    private function renderEntityPage(string $title, string $entity, array $fields, array $rows, string $statusField = 'status'): void
    {
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1>';
        $this->renderAdminNoticeFromRequest();
        echo '<h2>Создать</h2><form method="post">';
        wp_nonce_field('trn_' . $entity . '_create');
        echo '<input type="hidden" name="trn_entity" value="' . esc_attr($entity) . '"><input type="hidden" name="trn_action" value="create">';
        foreach ($fields as $field) {
            echo '<p><label>' . esc_html($field) . '<br><input class="regular-text" name="' . esc_attr($field) . '" value=""></label></p>';
        }
        submit_button('Create');
        echo '</form><h2>Список</h2><table class="widefat striped"><thead><tr>';
        foreach (array_merge(['id'], $fields, [$statusField]) as $col) {
            echo '<th>' . esc_html($col) . '</th>';
        }
        echo '<th>Actions</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach (array_merge(['id'], $fields, [$statusField]) as $col) {
                echo '<td>' . esc_html((string) ($row[$col] ?? '')) . '</td>';
            }
            echo '<td><form method="post" style="display:inline-block; margin-right:8px;">';
            wp_nonce_field('trn_' . $entity . '_archive');
            echo '<input type="hidden" name="trn_entity" value="' . esc_attr($entity) . '"><input type="hidden" name="trn_action" value="archive"><input type="hidden" name="id" value="' . esc_attr((string) $row['id']) . '">';
            submit_button('Archive', 'secondary', 'submit', false);
            echo '</form></td></tr>';

            echo '<tr><td colspan="' . esc_attr((string) (count($fields) + 4)) . '"><form method="post" style="padding:8px 0;">';
            wp_nonce_field('trn_' . $entity . '_update');
            echo '<input type="hidden" name="trn_entity" value="' . esc_attr($entity) . '"><input type="hidden" name="trn_action" value="update"><input type="hidden" name="id" value="' . esc_attr((string) $row['id']) . '">';
            foreach ($fields as $field) {
                echo '<label style="margin-right:10px;">' . esc_html($field) . ': <input name="' . esc_attr($field) . '" value="' . esc_attr((string) ($row[$field] ?? '')) . '"></label>';
            }
            submit_button('Update', 'primary', 'submit', false);
            echo '</form></td></tr>';
        }

        echo '</tbody></table></div>';
    }

    private function renderCreateEstimateForm(): void
    {
        echo '<h2>Создать смету</h2><form method="post">';
        wp_nonce_field('trn_estimate_create');
        echo '<input type="hidden" name="trn_entity" value="estimate"><input type="hidden" name="trn_action" value="create">';
        echo '<p><label>project_id<br><input name="project_id" class="regular-text"></label></p>';
        echo '<p><label>title<br><input name="title" class="regular-text"></label></p>';
        echo '<p><label>currency<br><input name="currency" class="regular-text" value="SEK"></label></p>';
        echo '<p><label>vat_rate_percent<br><input name="vat_rate_percent" class="regular-text" value="25"></label></p>';
        echo '<p><label>labour_rate_minor<br><input name="labour_rate_minor" class="regular-text" value="65000"></label></p>';
        submit_button('Create estimate');
        echo '</form>';
    }

    private function renderEstimateBuilder(int $estimateId): void
    {
        $estimate = $this->factory->estimates()->find($estimateId);
        if ($estimate === null) {
            echo '<p>Estimate not found.</p>';
            return;
        }

        $lines = $this->factory->estimateLines()->byEstimate($estimateId);
        $materialLines = $this->factory->estimateMaterialLines()->byEstimate($estimateId);
        $totals = (new EstimateTotalsCalculator())->calculate($lines, $materialLines, (float) $estimate['vat_rate_percent']);

        echo '<h2>Estimate #' . esc_html((string) $estimateId) . '</h2>';
        echo '<p><strong>title:</strong> ' . esc_html((string) $estimate['title']) . ' | <strong>labour_rate_minor:</strong> ' . esc_html((string) $estimate['labour_rate_minor']) . ' | <strong>vat_rate_percent:</strong> ' . esc_html((string) $estimate['vat_rate_percent']) . '</p>';

        $project = null;
        $property = null;
        $client = null;
        $projectId = (int) ($estimate['project_id'] ?? 0);
        if ($projectId > 0) {
            $project = $this->factory->projects()->find($projectId);
        }
        if (is_array($project)) {
            $propertyId = (int) ($project['property_id'] ?? 0);
            if ($propertyId > 0) {
                $property = $this->factory->properties()->find($propertyId);
            }
        }
        if (is_array($property)) {
            $clientId = (int) ($property['client_id'] ?? 0);
            if ($clientId > 0) {
                $client = $this->factory->clients()->find($clientId);
            }
        }

        echo '<h3>Project context</h3>';
        if (! is_array($project)) {
            echo '<p><em>Project not found.</em></p>';
        } else {
            echo '<h4>Project</h4>';
            $this->renderKeyValueTable([
                'id' => $project['id'] ?? '',
                'name' => $project['name'] ?? '',
                'code' => $project['code'] ?? '',
            ]);
        }

        if (! is_array($property)) {
            echo '<p><em>Property not found.</em></p>';
        } else {
            echo '<h4>Property</h4>';
            $this->renderKeyValueTable([
                'id' => $property['id'] ?? '',
                'name' => $property['name'] ?? '',
                'address_line' => $property['address_line'] ?? '',
                'city' => $property['city'] ?? '',
                'postal_code' => $property['postal_code'] ?? '',
            ]);
        }

        if (! is_array($client)) {
            echo '<p><em>Client not found.</em></p>';
        } else {
            echo '<h4>Client</h4>';
            $this->renderKeyValueTable([
                'id' => $client['id'] ?? '',
                'name' => $client['name'] ?? '',
                'org_number' => $client['org_number'] ?? '',
                'email' => $client['email'] ?? '',
                'phone' => $client['phone'] ?? '',
            ]);
        }

        echo '<h3>Commercial summary</h3>';
        $this->renderKeyValueTable([
            'work_lines_count' => (int) count($lines),
            'material_lines_count' => (int) count($materialLines),
            'labour_total' => $this->formatMinorMoney($totals['labour_total_minor'] ?? null, (string) ($estimate['currency'] ?? 'SEK')),
            'materials_total' => $this->formatMinorMoney($totals['materials_total_minor'] ?? null, (string) ($estimate['currency'] ?? 'SEK')),
            'subtotal_ex_vat' => $this->formatMinorMoney($totals['subtotal_ex_vat_minor'] ?? null, (string) ($estimate['currency'] ?? 'SEK')),
            'vat' => $this->formatMinorMoney($totals['vat_minor'] ?? null, (string) ($estimate['currency'] ?? 'SEK')),
            'total_inc_vat' => $this->formatMinorMoney($totals['total_inc_vat_minor'] ?? null, (string) ($estimate['currency'] ?? 'SEK')),
        ]);

        $offerts = $this->factory->offerts()->byEstimate($estimateId);
        $timelineBuilder = new EstimateDocumentTimelineBuilder();
        $invoicesByOffertId = [];
        $paymentsByInvoiceId = [];
        foreach ($offerts as $offert) {
            $offertId = (int) ($offert['id'] ?? 0);
            if ($offertId <= 0) {
                continue;
            }

            $offertInvoices = $this->factory->invoices()->byOffert($offertId);
            $invoicesByOffertId[$offertId] = $offertInvoices;
            foreach ($offertInvoices as $invoice) {
                $invoiceId = (int) ($invoice['id'] ?? 0);
                if ($invoiceId <= 0) {
                    continue;
                }

                $paymentsByInvoiceId[$invoiceId] = $this->factory->invoicePayments()->byInvoice($invoiceId);
            }
        }
        $snapshots = $this->factory->estimateSnapshots()->byEstimate($estimateId);
        $timeline = $timelineBuilder->build($estimateId, $snapshots, $offerts, $invoicesByOffertId, $paymentsByInvoiceId);
        echo '<h3>Document summary</h3>';
        $this->renderEstimateDocumentSummary($timeline['summary']);
        echo '<h3>Document timeline</h3>';
        $this->renderEstimateDocumentTimeline($timeline['rows']);

        echo '<h3>Issued offerts for this estimate</h3>';
        if ($offerts === []) {
            $this->renderEmptyState('No offerts yet.');
        } else {
            $this->renderOffertsForEstimateTable($offerts, $estimateId);
        }

        echo '<h3>Добавить work line</h3><form method="post">';
        wp_nonce_field('trn_estimate_line_create');
        echo '<input type="hidden" name="trn_entity" value="estimate_line"><input type="hidden" name="trn_action" value="create"><input type="hidden" name="estimate_id" value="' . esc_attr((string) $estimateId) . '">';
        foreach (['room_id', 'work_item_id', 'line_title_ru_snapshot', 'line_title_sv_snapshot', 'unit_code_snapshot', 'quantity', 'speed_profile', 'norm_per_hour_snapshot', 'complexity_coeff', 'surface_coeff', 'access_coeff', 'urgency_coeff', 'manual_hours_delta', 'sort_order'] as $field) {
            echo '<label style="margin-right:10px;">' . esc_html($field) . ': <input name="' . esc_attr($field) . '" value=""></label>';
        }
        submit_button('Add work line', 'secondary', 'submit', false);
        echo '</form>';

        echo '<h3>Добавить material line</h3><form method="post">';
        wp_nonce_field('trn_estimate_material_line_create');
        echo '<input type="hidden" name="trn_entity" value="estimate_material_line"><input type="hidden" name="trn_action" value="create"><input type="hidden" name="estimate_id" value="' . esc_attr((string) $estimateId) . '">';
        foreach (['estimate_line_id', 'material_id', 'material_name_ru_snapshot', 'material_name_sv_snapshot', 'unit_code_snapshot', 'quantity', 'coverage_snapshot', 'buy_price_minor_snapshot', 'sell_price_minor_snapshot', 'sort_order'] as $field) {
            echo '<label style="margin-right:10px;">' . esc_html($field) . ': <input name="' . esc_attr($field) . '" value=""></label>';
        }
        submit_button('Add material line', 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" style="margin-top:10px;">';
        wp_nonce_field('trn_estimate_recalculate_recalculate');
        echo '<input type="hidden" name="trn_entity" value="estimate_recalculate"><input type="hidden" name="trn_action" value="recalculate"><input type="hidden" name="estimate_id" value="' . esc_attr((string) $estimateId) . '">';
        submit_button('Recalculate');
        echo '</form>';

        if (current_user_can('trn_issue_offerts')) {
            echo '<form method="post" style="margin-top:10px;">';
            wp_nonce_field('trn_offert_issue');
            echo '<input type="hidden" name="trn_entity" value="offert"><input type="hidden" name="trn_action" value="issue"><input type="hidden" name="estimate_id" value="' . esc_attr((string) $estimateId) . '">';
            submit_button('Issue Offert', 'primary', 'submit', false);
            echo '</form>';
        }

        echo '<h3>Work lines</h3>';
        $this->renderEstimateLinesTable($lines);
        echo '<h3>Material lines</h3>';
        $this->renderMaterialLinesTable($materialLines);
        echo '<h3>Итоги</h3><ul>';
        foreach ($totals as $key => $value) {
            echo '<li>' . esc_html($key) . ': <strong>' . esc_html((string) $value) . '</strong></li>';
        }
        echo '</ul>';

        echo '<h3>Recalculation snapshots</h3>';
        if ($snapshots === []) {
            $this->renderEmptyState('No snapshots yet.');
        } else {
            echo '<table class="widefat striped"><thead><tr><th>ID</th><th>snapshot_type</th><th>actor_user_id</th><th>created_at</th><th>Actions</th></tr></thead><tbody>';
            foreach ($snapshots as $snapshot) {
                $snapshotId = (int) ($snapshot['id'] ?? 0);
                $detailUrl = admin_url(
                    'admin.php?page=trn_estimates&estimate_id=' . $estimateId . '&snapshot_id=' . $snapshotId
                );
                echo '<tr>';
                echo '<td>' . esc_html((string) $snapshotId) . '</td>';
                echo '<td>' . esc_html((string) ($snapshot['snapshot_type'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($snapshot['actor_user_id'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($snapshot['created_at'] ?? '')) . '</td>';
                echo '<td><a class="button" href="' . esc_url($detailUrl) . '">Open snapshot</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }

    /** @param array<string, int> $summary */
    private function renderEstimateDocumentSummary(array $summary): void
    {
        echo '<table class="widefat striped"><tbody>';
        $fields = [
            'snapshots_count',
            'offerts_count',
            'invoices_count',
            'payments_count',
            'invoiced_total_minor',
            'paid_total_minor',
            'outstanding_minor',
        ];
        foreach ($fields as $field) {
            echo '<tr><th>' . esc_html($field) . '</th><td>' . esc_html((string) ($summary[$field] ?? 0)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array<string, string|int>> $rows */
    private function renderEstimateDocumentTimeline(array $rows): void
    {
        if ($rows === []) {
            $this->renderEmptyState('No related documents found.');
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>type</th><th>source_id</th><th>document/reference</th><th>status</th><th>event_at</th><th>amount_minor</th><th>currency</th><th>actions</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['source_type'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['source_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['document_number_or_reference'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['event_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['amount_minor'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['currency'] ?? '')) . '</td>';

            $actionTarget = (string) ($row['action_target'] ?? '');
            echo '<td>';
            if ($actionTarget !== '') {
                echo '<a class="button" href="' . esc_url(admin_url($actionTarget)) . '">Open</a>';
            } else {
                echo esc_html__('Unavailable', 'trenor-core');
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderEstimateLinesTable(array $rows): void
    {
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>quantity</th><th>speed</th><th>complexity</th><th>surface</th><th>access</th><th>urgency</th><th>hours</th><th>labour subtotal</th><th>Actions</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html((string) $row['id']) . '</td><td>' . esc_html((string) $row['quantity']) . '</td><td>' . esc_html((string) $row['speed_profile']) . '</td><td>' . esc_html((string) $row['complexity_coeff']) . '</td><td>' . esc_html((string) $row['surface_coeff']) . '</td><td>' . esc_html((string) $row['access_coeff']) . '</td><td>' . esc_html((string) $row['urgency_coeff']) . '</td><td>' . esc_html((string) $row['calculated_hours']) . '</td><td>' . esc_html((string) $row['labour_subtotal_minor']) . '</td><td>';
            echo '<form method="post">';
            wp_nonce_field('trn_estimate_line_archive');
            echo '<input type="hidden" name="trn_entity" value="estimate_line"><input type="hidden" name="trn_action" value="archive"><input type="hidden" name="estimate_id" value="' . esc_attr((string) $row['estimate_id']) . '"><input type="hidden" name="id" value="' . esc_attr((string) $row['id']) . '">';
            submit_button('Archive', 'secondary', 'submit', false);
            echo '</form></td></tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderMaterialLinesTable(array $rows): void
    {
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>name</th><th>quantity</th><th>coverage</th><th>subtotal</th><th>Actions</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html((string) $row['id']) . '</td><td>' . esc_html((string) $row['material_name_ru_snapshot']) . '</td><td>' . esc_html((string) $row['quantity']) . '</td><td>' . esc_html((string) $row['coverage_snapshot']) . '</td><td>' . esc_html((string) $row['subtotal_minor']) . '</td><td>';
            echo '<form method="post">';
            wp_nonce_field('trn_estimate_material_line_archive');
            echo '<input type="hidden" name="trn_entity" value="estimate_material_line"><input type="hidden" name="trn_action" value="archive"><input type="hidden" name="estimate_id" value="' . esc_attr((string) $row['estimate_id']) . '"><input type="hidden" name="id" value="' . esc_attr((string) $row['id']) . '">';
            submit_button('Archive', 'secondary', 'submit', false);
            echo '</form></td></tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<string, mixed> $postPayload */
    private function handleEstimateLine(string $action, int $id, array $postPayload): void
    {
        $estimateId = (int) $this->postValue($postPayload, 'estimate_id');
        $repo = $this->factory->estimateLines();
        $data = $this->collectData($postPayload, ['estimate_id', 'room_id', 'work_item_id', 'line_title_ru_snapshot', 'line_title_sv_snapshot', 'unit_code_snapshot', 'quantity', 'speed_profile', 'norm_per_hour_snapshot', 'complexity_coeff', 'surface_coeff', 'access_coeff', 'urgency_coeff', 'manual_hours_delta', 'labour_rate_minor_snapshot', 'labour_subtotal_minor', 'sort_order']);

        if ($action === 'create') {
            $estimate = $this->factory->estimates()->find($estimateId);
            $data['labour_rate_minor_snapshot'] = $estimate['labour_rate_minor'] ?? 0;
            if (! empty($data['work_item_id'])) {
                $workItem = $this->factory->workItems()->find((int) $data['work_item_id']);
                if (is_array($workItem)) {
                    $speed = (string) ($data['speed_profile'] ?: 'medium');
                    $normField = 'norm_' . $speed . '_per_hour';
                    $data['line_title_ru_snapshot'] = $workItem['name_ru'];
                    $data['line_title_sv_snapshot'] = $workItem['name_sv'];
                    $data['unit_code_snapshot'] = $workItem['unit_code'];
                    $data['norm_per_hour_snapshot'] = (string) ($workItem[$normField] ?? 0);
                }
            }
            $repo->create($data);
        }

        if ($action === 'archive') {
            $repo->archive($id);
        }

        wp_safe_redirect(admin_url('admin.php?page=trn_estimates&estimate_id=' . $estimateId));
        exit;
    }

    /** @param array<string, mixed> $postPayload */
    private function handleEstimateMaterialLine(string $action, int $id, array $postPayload): void
    {
        $estimateId = (int) $this->postValue($postPayload, 'estimate_id');
        $repo = $this->factory->estimateMaterialLines();
        $data = $this->collectData($postPayload, ['estimate_id', 'estimate_line_id', 'material_id', 'material_name_ru_snapshot', 'material_name_sv_snapshot', 'unit_code_snapshot', 'quantity', 'coverage_snapshot', 'buy_price_minor_snapshot', 'sell_price_minor_snapshot', 'sort_order']);

        if ($action === 'create') {
            if (! empty($data['material_id'])) {
                $material = $this->factory->materials()->find((int) $data['material_id']);
                if (is_array($material)) {
                    $data['material_name_ru_snapshot'] = $material['name_ru'];
                    $data['material_name_sv_snapshot'] = $material['name_sv'];
                    $data['unit_code_snapshot'] = $material['unit_code'];
                    $data['coverage_snapshot'] = (string) $material['coverage_per_unit'];
                    $data['buy_price_minor_snapshot'] = (string) $material['buy_price_minor'];
                    $data['sell_price_minor_snapshot'] = (string) $material['sell_price_minor'];
                }
            }
            $data['subtotal_minor'] = (string) ((int) round((float) ($data['quantity'] ?? 0) * (int) ($data['sell_price_minor_snapshot'] ?? 0)));
            $repo->create($data);
        }

        if ($action === 'archive') {
            $repo->archive($id);
        }

        wp_safe_redirect(admin_url('admin.php?page=trn_estimates&estimate_id=' . $estimateId));
        exit;
    }

    private function recalculateEstimate(int $estimateId): void
    {
        $estimateRepo = $this->factory->estimates();
        $estimate = $estimateRepo->find($estimateId);

        if ($estimate === null) {
            wp_safe_redirect(admin_url('admin.php?page=trn_estimates&trn_result=error'));
            exit;
        }

        $lineRepo = $this->factory->estimateLines();
        $materialRepo = $this->factory->estimateMaterialLines();
        $lines = $lineRepo->byEstimate($estimateId);
        $materialLines = $materialRepo->byEstimate($estimateId);

        $calculator = new EstimateCalculator();

        try {
            foreach ($lines as $line) {
                $result = $calculator->calculateLabourLine($line);
                $lineRepo->updateEntity((int) $line['id'], [
                    'calculated_hours' => $result['hours'],
                    'labour_subtotal_minor' => $result['labour_subtotal_minor'],
                    'labour_rate_minor_snapshot' => (int) $estimate['labour_rate_minor'],
                ]);
            }
        } catch (EstimateCalculationException $exception) {
            wp_safe_redirect(admin_url('admin.php?page=trn_estimates&estimate_id=' . $estimateId . '&trn_result=error&trn_msg=' . rawurlencode($exception->getMessage())));
            exit;
        }

        foreach ($materialLines as $line) {
            $materialRepo->updateEntity((int) $line['id'], [
                'subtotal_minor' => (int) round((float) $line['quantity'] * (int) $line['sell_price_minor_snapshot']),
            ]);
        }

        $lines = $lineRepo->byEstimate($estimateId);
        $materialLines = $materialRepo->byEstimate($estimateId);

        $totals = (new EstimateTotalsCalculator())->calculate($lines, $materialLines, (float) $estimate['vat_rate_percent']);
        $estimateRepo->updateEntity($estimateId, [
            'project_id' => (int) $estimate['project_id'],
            'title' => (string) $estimate['title'],
            'status' => (string) $estimate['status'],
            'currency' => (string) $estimate['currency'],
            'vat_rate_percent' => (float) $estimate['vat_rate_percent'],
            'labour_rate_minor' => (int) $estimate['labour_rate_minor'],
            'notes' => (string) $estimate['notes'],
            'calculated_at' => current_time('mysql', true),
        ]);

        $snapshotService = new EstimateSnapshotService($this->factory->estimateSnapshots());
        $snapshotService->captureRecalculationSnapshot($estimateRepo->find($estimateId) ?? $estimate, $lines, $materialLines, $totals, get_current_user_id() ?: null);

        wp_safe_redirect(admin_url('admin.php?page=trn_estimates&estimate_id=' . $estimateId . '&trn_result=ok'));
        exit;
    }

    /** @param array<string, mixed> $postPayload */
    private function handleOffert(string $action, int $id, array $postPayload): void
    {
        $offertRepo = $this->factory->offerts();

        if ($action === 'issue') {
            $estimateId = (int) $this->postValue($postPayload, 'estimate_id');
            $estimate = $this->factory->estimates()->find($estimateId);
            if ($estimate === null) {
                wp_safe_redirect(admin_url('admin.php?page=trn_estimates&trn_result=error&trn_msg=' . rawurlencode('Estimate not found.')));
                exit;
            }

            $lines = $this->factory->estimateLines()->byEstimate($estimateId);
            $materialLines = $this->factory->estimateMaterialLines()->byEstimate($estimateId);
            if ($lines === [] && $materialLines === []) {
                wp_safe_redirect(admin_url('admin.php?page=trn_estimates&estimate_id=' . $estimateId . '&trn_result=error&trn_msg=' . rawurlencode('Cannot issue offert without labour/material lines.')));
                exit;
            }

            $totals = (new EstimateTotalsCalculator())->calculate($lines, $materialLines, (float) $estimate['vat_rate_percent']);
            $service = new OffertFromEstimateService($offertRepo, new DocumentSequenceGenerator());
            $payload = $service->buildPayload($estimate, $lines, $materialLines, $totals);
            $offertId = $offertRepo->create($payload);

            if ($offertId === null) {
                wp_safe_redirect(admin_url('admin.php?page=trn_estimates&estimate_id=' . $estimateId . '&trn_result=error&trn_msg=' . rawurlencode('Offert issue failed.')));
                exit;
            }

            wp_safe_redirect(admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId . '&trn_result=ok'));
            exit;
        }

        if (in_array($action, ['accept', 'reject', 'archive'], true)) {
            if ($action === 'archive' && ! current_user_can('trn_archive_records')) {
                wp_die(esc_html__('You do not have permissions to perform this action.', 'trenor-core'));
            }

            $isSuccess = $offertRepo->transitionStatus($id, $action === 'archive' ? 'archived' : $action . 'ed');
            $status = $isSuccess ? 'ok' : 'error';
            wp_safe_redirect(admin_url('admin.php?page=trn_offerts&trn_result=' . $status));
            exit;
        }

        if ($action === 'issue_invoice') {
            $offertId = (int) $this->postValue($postPayload, 'offert_id');
            $offert = $offertRepo->find($offertId);
            if ($offert === null) {
                wp_safe_redirect(admin_url('admin.php?page=trn_offerts&trn_result=error&trn_msg=' . rawurlencode('Offert not found.')));
                exit;
            }

            $snapshot = (new OffertSnapshotReader())->read($offert);
            $service = new InvoiceFromOffertService($this->factory->invoices(), new DocumentSequenceGenerator());

            try {
                $payload = $service->buildPayload($offert, $snapshot);
            } catch (RuntimeException $exception) {
                wp_safe_redirect(admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId . '&trn_result=error&trn_msg=' . rawurlencode($exception->getMessage())));
                exit;
            }

            $invoiceId = $this->factory->invoices()->create($payload);
            if ($invoiceId === null) {
                wp_safe_redirect(admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId . '&trn_result=error&trn_msg=' . rawurlencode('Invoice issue failed.')));
                exit;
            }

            wp_safe_redirect(admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId . '&trn_result=ok'));
            exit;
        }
    }

    /** @param array<string, mixed> $postPayload */
    private function handleInvoice(string $action, array $postPayload): void
    {
        if ($action !== 'issue') {
            return;
        }

        $offertId = (int) $this->postValue($postPayload, 'offert_id');
        $offert = $this->factory->offerts()->find($offertId);
        if ($offert === null) {
            wp_safe_redirect(admin_url('admin.php?page=trn_offerts&trn_result=error&trn_msg=' . rawurlencode('Offert not found.')));
            exit;
        }

        $snapshot = (new OffertSnapshotReader())->read($offert);
        $service = new InvoiceFromOffertService($this->factory->invoices(), new DocumentSequenceGenerator());

        try {
            $payload = $service->buildPayload($offert, $snapshot);
        } catch (RuntimeException $exception) {
            wp_safe_redirect(admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId . '&trn_result=error&trn_msg=' . rawurlencode($exception->getMessage())));
            exit;
        }

        $invoiceId = $this->factory->invoices()->create($payload);
        if ($invoiceId === null) {
            wp_safe_redirect(admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId . '&trn_result=error&trn_msg=' . rawurlencode('Invoice issue failed.')));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId . '&trn_result=ok'));
        exit;
    }

    /** @param array<string, mixed> $postPayload */
    private function handleInvoicePayment(string $action, array $postPayload): void
    {
        if ($action !== 'create') {
            return;
        }

        $invoiceId = (int) $this->postValue($postPayload, 'invoice_id');
        $service = new PaymentRecorderService(
            $this->factory->invoices(),
            $this->factory->invoicePayments(),
            new InvoicePaymentSummaryCalculator()
        );

        try {
            $service->record([
                'invoice_id' => $invoiceId,
                'payment_date' => $this->postValue($postPayload, 'payment_date'),
                'amount_minor' => (int) $this->postValue($postPayload, 'amount_minor'),
                'currency' => $this->postValue($postPayload, 'currency'),
                'method' => $this->postValue($postPayload, 'method'),
                'reference' => $this->postValue($postPayload, 'reference'),
                'note' => $this->postValue($postPayload, 'note'),
                'actor_user_id' => get_current_user_id(),
            ]);
        } catch (PaymentRegistrationException $exception) {
            wp_safe_redirect(admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId . '&trn_result=error&trn_msg=' . rawurlencode($exception->getMessage())));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId . '&trn_result=ok&trn_msg=' . rawurlencode('Payment recorded.')));
        exit;
    }

    /** @param array<string, mixed> $postPayload */
    private function handleCreditNote(string $action, array $postPayload): void
    {
        $repo = $this->factory->creditNotes();

        if ($action === 'issue') {
            $invoiceId = (int) $this->postValue($postPayload, 'invoice_id');
            $invoice = $this->factory->invoices()->find($invoiceId);
            if ($invoice === null) {
                wp_safe_redirect(admin_url('admin.php?page=trn_invoices&trn_result=error&trn_msg=' . rawurlencode('Source invoice not found.')));
                exit;
            }

            $service = new CreditNoteFromInvoiceService($repo, new DocumentSequenceGenerator());
            try {
                $payload = $service->buildPayload($invoice);
            } catch (RuntimeException $exception) {
                wp_safe_redirect(admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId . '&trn_result=error&trn_msg=' . rawurlencode($exception->getMessage())));
                exit;
            }

            $creditNoteId = $repo->create($payload);
            if ($creditNoteId === null) {
                wp_safe_redirect(admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId . '&trn_result=error&trn_msg=' . rawurlencode('Credit note issue failed.')));
                exit;
            }

            wp_safe_redirect(admin_url('admin.php?page=trn_credit_notes&credit_note_id=' . $creditNoteId . '&trn_result=ok'));
            exit;
        }

        if ($action === 'archive') {
            $creditNoteId = (int) $this->postValue($postPayload, 'id');
            $isSuccess = $repo->transitionStatus($creditNoteId, 'archived');
            wp_safe_redirect(admin_url('admin.php?page=trn_credit_notes&trn_result=' . ($isSuccess ? 'ok' : 'error')));
            exit;
        }
    }

    /** @param array<int, string> $fields @param array<string, mixed> $postPayload @return array<string, mixed> */
    private function collectData(array $postPayload, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->postValue($postPayload, $field);
        }
        return $data;
    }

    /** @param array<string, mixed> $postPayload */
    private function postValue(array $postPayload, string $field): string
    {
        $value = $postPayload[$field] ?? '';
        if (is_array($value)) {
            return '';
        }
        return (string) wp_unslash((string) $value);
    }

    /** @param array<string,mixed> $data */
    private function handleEntity(object $repository, string $redirectPage, string $action, int $id, array $data): void
    {
        $isSuccess = false;
        if ($action === 'create') {
            $isSuccess = $repository->create($data) !== null;
        }

        if ($action === 'update') {
            $isSuccess = $repository->updateEntity($id, $data);
        }

        if ($action === 'archive') {
            $isSuccess = $repository->archive($id);
        }

        $status = $isSuccess ? 'ok' : 'error';
        wp_safe_redirect(admin_url('admin.php?page=' . $redirectPage . '&trn_result=' . $status));
        exit;
    }

    private function requiredCapability(string $entity, string $action): string
    {
        if ($entity === 'client') {
            return $action === 'archive' ? 'trn_archive_records' : 'trn_manage_clients';
        }

        if (in_array($entity, ['property', 'project', 'room'], true)) {
            return $action === 'archive' ? 'trn_archive_records' : 'trn_manage_projects';
        }

        if (in_array($entity, ['work_item', 'material'], true)) {
            return $action === 'archive' ? 'trn_archive_records' : 'trn_manage_catalogs';
        }

        if (in_array($entity, ['estimate', 'estimate_line', 'estimate_material_line', 'estimate_recalculate'], true)) {
            return $action === 'archive' ? 'trn_archive_records' : 'trn_manage_estimates';
        }

        if ($entity === 'offert') {
            if ($action === 'archive') {
                return 'trn_archive_records';
            }

            return 'trn_issue_offerts';
        }

        if ($entity === 'invoice') {
            return 'trn_issue_invoices';
        }

        if ($entity === 'invoice_payment') {
            return 'trn_record_payments';
        }

        if ($entity === 'credit_note') {
            return $action === 'archive' ? 'trn_archive_records' : 'trn_issue_credit_notes';
        }

        if ($entity === 'document_settings' || $entity === 'document_profile_settings') {
            return 'trn_manage_backups';
        }

        return 'read';
    }

    private function renderOffertActionForm(int $offertId, string $action, string $label): void
    {
        echo '<form method="post" style="display:inline-block; margin-left:6px;">';
        wp_nonce_field('trn_offert_' . $action);
        echo '<input type="hidden" name="trn_entity" value="offert"><input type="hidden" name="trn_action" value="' . esc_attr($action) . '"><input type="hidden" name="id" value="' . esc_attr((string) $offertId) . '">';
        submit_button($label, 'secondary', 'submit', false);
        echo '</form>';
    }

    private function renderOffertDetail(int $offertId): void
    {
        $offert = $this->factory->offerts()->find($offertId);
        if ($offert === null) {
            echo '<h2>Offert detail</h2><p>Offert not found.</p>';
            return;
        }

        $offertsUrl = admin_url('admin.php?page=trn_offerts');
        $estimateId = (int) ($offert['estimate_id'] ?? 0);
        $printUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId . '&view=print');
        echo '<p><a href="' . esc_url($offertsUrl) . '">Back to offerts list</a>';
        echo ' | <a href="' . esc_url($printUrl) . '">Print / Printable view</a>';
        if ($estimateId > 0) {
            $estimateUrl = admin_url('admin.php?page=trn_estimates&estimate_id=' . $estimateId);
            $estimateOffertsUrl = admin_url('admin.php?page=trn_offerts&estimate_id=' . $estimateId);
            echo ' | <a href="' . esc_url($estimateUrl) . '">Open source estimate</a>';
            echo ' | <a href="' . esc_url($estimateOffertsUrl) . '">View all offerts for this estimate</a>';
        }
        echo '</p>';

        if ((string) ($offert['status'] ?? '') === 'accepted' && current_user_can('trn_issue_invoices')) {
            echo '<form method="post" style="margin:10px 0;">';
            wp_nonce_field('trn_offert_issue_invoice');
            echo '<input type="hidden" name="trn_entity" value="offert"><input type="hidden" name="trn_action" value="issue_invoice"><input type="hidden" name="offert_id" value="' . esc_attr((string) $offertId) . '">';
            submit_button('Issue Invoice', 'primary', 'submit', false);
            echo '</form>';
        }

        $reader = new OffertSnapshotReader();
        $snapshot = $reader->read($offert);

        $renderer = new OffertDetailRenderer();
        $renderer->render($offert, $snapshot);
    }

    private function renderInvoiceDetail(int $invoiceId): void
    {
        $invoice = $this->factory->invoices()->find($invoiceId);
        if ($invoice === null) {
            echo '<h2>Invoice detail</h2><p>Invoice not found.</p>';

            return;
        }

        $invoicesUrl = admin_url('admin.php?page=trn_invoices');
        $offertId = (int) ($invoice['offert_id'] ?? 0);
        $printUrl = admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId . '&view=print');
        echo '<p><a href="' . esc_url($invoicesUrl) . '">Back to invoices list</a>';
        echo ' | <a href="' . esc_url($printUrl) . '">Print / Printable view</a>';
        if ($offertId > 0) {
            $offertUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId);
            echo ' | <a href="' . esc_url($offertUrl) . '">Open source offert</a>';
        }
        echo ' | <a href="' . esc_url(admin_url('admin.php?page=trn_credit_notes&invoice_id=' . $invoiceId)) . '">View all credit notes for this invoice</a>';
        echo '</p>';

        if (current_user_can('trn_issue_credit_notes') && (string) ($invoice['status'] ?? '') !== 'archived') {
            echo '<form method="post" style="margin:10px 0;">';
            wp_nonce_field('trn_credit_note_issue');
            echo '<input type="hidden" name="trn_entity" value="credit_note"><input type="hidden" name="trn_action" value="issue"><input type="hidden" name="invoice_id" value="' . esc_attr((string) $invoiceId) . '">';
            submit_button('Issue credit note', 'secondary', 'submit', false);
            echo '</form>';
        }

        $snapshot = (new OffertSnapshotReader())->read($invoice);
        (new InvoiceDetailRenderer())->render($invoice, $snapshot);
        $this->renderInvoicePaymentSection($invoice);
    }

    private function renderCreditNoteDetail(int $creditNoteId): void
    {
        $creditNote = $this->factory->creditNotes()->find($creditNoteId);
        if ($creditNote === null) {
            echo '<h2>Credit note detail</h2><p>Credit note not found.</p>';

            return;
        }

        $creditNotesUrl = admin_url('admin.php?page=trn_credit_notes');
        $invoiceId = (int) ($creditNote['invoice_id'] ?? 0);
        $offertId = (int) ($creditNote['offert_id'] ?? 0);
        $estimateId = (int) ($creditNote['estimate_id'] ?? 0);

        $printUrl = admin_url('admin.php?page=trn_credit_notes&credit_note_id=' . $creditNoteId . '&view=print');
        echo '<p><a href="' . esc_url($creditNotesUrl) . '">Back to credit notes list</a>';
        echo ' | <a href="' . esc_url($printUrl) . '">Print / Printable view</a>';
        if ($invoiceId > 0) {
            echo ' | <a href="' . esc_url(admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId)) . '">Open source invoice</a>';
            echo ' | <a href="' . esc_url(admin_url('admin.php?page=trn_credit_notes&invoice_id=' . $invoiceId)) . '">View all credit notes for this invoice</a>';
        }
        if ($offertId > 0) {
            echo ' | <a href="' . esc_url(admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId)) . '">Open source offert</a>';
        }
        if ($estimateId > 0) {
            echo ' | <a href="' . esc_url(admin_url('admin.php?page=trn_estimates&estimate_id=' . $estimateId)) . '">Open source estimate</a>';
        }
        echo '</p>';

        $snapshot = (new CreditNoteSnapshotReader())->read($creditNote);
        (new CreditNoteDetailRenderer())->render($creditNote, $snapshot, $this->loadCreditNoteContext($creditNote));
    }

    /** @param array<string, mixed> $invoice */
    private function renderInvoicePaymentSection(array $invoice): void
    {
        $invoiceId = (int) ($invoice['id'] ?? 0);
        if ($invoiceId <= 0) {
            return;
        }

        $payments = $this->factory->invoicePayments()->byInvoice($invoiceId);
        $summary = (new InvoicePaymentSummaryCalculator())->calculate($invoice, $payments);

        echo '<h2>Payment summary</h2>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>invoice_total_minor</th><td>' . esc_html((string) $summary['invoice_total_minor']) . '</td></tr>';
        echo '<tr><th>paid_total_minor</th><td>' . esc_html((string) $summary['paid_total_minor']) . '</td></tr>';
        echo '<tr><th>outstanding_minor</th><td>' . esc_html((string) $summary['outstanding_minor']) . '</td></tr>';
        echo '<tr><th>payment_count</th><td>' . esc_html((string) $summary['payment_count']) . '</td></tr>';
        echo '<tr><th>current_status</th><td>' . esc_html((string) ($invoice['status'] ?? '')) . '</td></tr>';
        echo '<tr><th>computed_status</th><td>' . esc_html((string) $summary['computed_status']) . '</td></tr>';
        echo '</tbody></table>';

        echo '<h2>Payments</h2>';
        echo '<table class="widefat striped"><thead><tr><th>id</th><th>payment_date</th><th>amount_minor</th><th>currency</th><th>method</th><th>reference</th><th>note</th></tr></thead><tbody>';
        foreach ($payments as $payment) {
            echo '<tr>';
            echo '<td>' . esc_html((string) ($payment['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['payment_date'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['amount_minor'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['currency'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['method'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['reference'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($payment['note'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        $status = (string) ($invoice['status'] ?? '');
        if (! current_user_can('trn_record_payments') || ! in_array($status, ['issued', 'partially_paid'], true)) {
            return;
        }

        echo '<h2>Record payment</h2><form method="post">';
        wp_nonce_field('trn_invoice_payment_create');
        echo '<input type="hidden" name="trn_entity" value="invoice_payment">';
        echo '<input type="hidden" name="trn_action" value="create">';
        echo '<input type="hidden" name="invoice_id" value="' . esc_attr((string) $invoiceId) . '">';
        echo '<p><label>payment_date<br><input class="regular-text" name="payment_date" value="' . esc_attr((string) current_time('mysql', true)) . '"></label></p>';
        echo '<p><label>amount_minor<br><input class="regular-text" name="amount_minor" value=""></label></p>';
        echo '<p><label>currency<br><input class="regular-text" name="currency" value="' . esc_attr((string) ($invoice['currency'] ?? 'SEK')) . '"></label></p>';
        echo '<p><label>method<br><input class="regular-text" name="method" value="manual"></label></p>';
        echo '<p><label>reference<br><input class="regular-text" name="reference" value=""></label></p>';
        echo '<p><label>note<br><textarea class="large-text" name="note" rows="3"></textarea></label></p>';
        submit_button('Record payment');
        echo '</form>';
    }

    /** @return array<int, array<string, mixed>> */
    private function paymentRowsForList(): array
    {
        return $this->factory->invoicePayments()->all();
    }

    private function renderAdminNoticeFromRequest(): void
    {
        $result = filter_input(INPUT_GET, 'trn_result', FILTER_UNSAFE_RAW);
        $message = filter_input(INPUT_GET, 'trn_msg', FILTER_UNSAFE_RAW);
        if (! is_string($result) || $result === '') {
            return;
        }

        $className = 'notice-info';
        $defaultMessage = 'Operation result was returned.';
        if ($result === 'ok') {
            $className = 'notice-success';
            $defaultMessage = 'Operation completed successfully.';
        } elseif ($result === 'error') {
            $className = 'notice-error';
            $defaultMessage = 'Operation failed.';
        }
        $text = is_string($message) && $message !== '' ? $message : $defaultMessage;

        echo '<div class="notice ' . esc_attr($className) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    /** @param array<string, mixed> $snapshotRow */
    private function renderEstimateSnapshotDetail(array $snapshotRow): void
    {
        $snapshot = json_decode((string) ($snapshotRow['snapshot_json'] ?? ''), true);
        $snapshot = is_array($snapshot) ? $snapshot : [];

        $header = is_array($snapshot['header'] ?? null) ? $snapshot['header'] : [];
        $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
        $lines = is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];
        $materialLines = is_array($snapshot['material_lines'] ?? null) ? $snapshot['material_lines'] : [];

        echo '<h3>Snapshot detail</h3>';

        $summaryRows = [
            'snapshot id' => $snapshotRow['id'] ?? '',
            'estimate_id' => $snapshotRow['estimate_id'] ?? '',
            'snapshot_type' => $snapshotRow['snapshot_type'] ?? '',
            'actor_user_id' => $snapshotRow['actor_user_id'] ?? '',
            'created_at' => $snapshotRow['created_at'] ?? '',
        ];
        $this->renderKeyValueTable($summaryRows);

        echo '<h4>Header</h4>';
        $headerRows = [
            'id' => $header['id'] ?? '',
            'project_id' => $header['project_id'] ?? '',
            'title' => $header['title'] ?? '',
            'status' => $header['status'] ?? '',
            'currency' => $header['currency'] ?? '',
            'vat_rate_percent' => $header['vat_rate_percent'] ?? '',
            'labour_rate_minor' => $header['labour_rate_minor'] ?? '',
            'calculated_at' => $header['calculated_at'] ?? '',
        ];
        $this->renderKeyValueTable($headerRows);

        echo '<h4>Totals</h4>';
        echo '<table class="widefat striped"><tbody>';
        $totalRows = [
            'labour_total_minor' => $totals['labour_total_minor'] ?? 0,
            'materials_total_minor' => $totals['materials_total_minor'] ?? 0,
            'subtotal_ex_vat_minor' => $totals['subtotal_ex_vat_minor'] ?? 0,
            'vat_minor' => $totals['vat_minor'] ?? 0,
            'total_inc_vat_minor' => $totals['total_inc_vat_minor'] ?? 0,
        ];
        foreach ($totalRows as $label => $value) {
            echo '<tr><th style="width:220px;">' . esc_html($label) . '</th><td>' . esc_html($this->formatMinorValue($value)) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h4>Labour lines</h4>';
        if ($lines === []) {
            $this->renderEmptyState('No labour lines in snapshot.');
        } else {
            echo '<table class="widefat striped"><thead><tr><th>id</th><th>line_title_ru_snapshot</th><th>line_title_sv_snapshot</th><th>unit_code_snapshot</th><th>quantity</th><th>calculated_hours</th><th>labour_subtotal_minor</th></tr></thead><tbody>';
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }

                echo '<tr>';
                echo '<td>' . esc_html((string) ($line['id'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($line['line_title_ru_snapshot'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($line['line_title_sv_snapshot'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($line['unit_code_snapshot'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($line['quantity'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($line['calculated_hours'] ?? '')) . '</td>';
                echo '<td>' . esc_html($this->formatMinorValue($line['labour_subtotal_minor'] ?? 0)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h4>Material lines</h4>';
        if ($materialLines === []) {
            $this->renderEmptyState('No material lines in snapshot.');
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>id</th><th>material_name_ru_snapshot</th><th>material_name_sv_snapshot</th><th>unit_code_snapshot</th><th>quantity</th><th>subtotal_minor</th></tr></thead><tbody>';
        foreach ($materialLines as $materialLine) {
            if (! is_array($materialLine)) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html((string) ($materialLine['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($materialLine['material_name_ru_snapshot'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($materialLine['material_name_sv_snapshot'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($materialLine['unit_code_snapshot'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($materialLine['quantity'] ?? '')) . '</td>';
            echo '<td>' . esc_html($this->formatMinorValue($materialLine['subtotal_minor'] ?? 0)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
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

    private function renderEmptyState(string $text): void
    {
        echo '<p>' . esc_html($text) . '</p>';
    }

    private function renderInlineErrorNotice(string $message): void
    {
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }

    private function renderAdminNotice(string $message, string $type = 'info'): void
    {
        $allowedTypes = ['info', 'warning', 'error', 'success'];
        if (! in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

        echo '<div class="notice notice-' . esc_attr($type) . '"><p>' . esc_html($message) . '</p></div>';
    }

    /** @param array<int, array<string, mixed>> $offerts */
    private function renderOffertsForEstimateTable(array $offerts, int $estimateId = 0): void
    {
        echo '<table class="widefat striped"><thead><tr><th>id</th><th>document_number</th><th>version_no</th><th>status</th><th>issued_at</th><th>total_inc_vat_minor</th><th>Actions</th></tr></thead><tbody>';
        foreach ($offerts as $offert) {
            $offertUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . (int) ($offert['id'] ?? 0));
            $printUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . (int) ($offert['id'] ?? 0) . '&view=print');
            $filteredOffertsUrl = admin_url('admin.php?page=trn_offerts&estimate_id=' . $estimateId);
            echo '<tr>';
            echo '<td>' . esc_html((string) ($offert['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($offert['document_number'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($offert['version_no'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($offert['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($offert['issued_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($offert['total_inc_vat_minor'] ?? '')) . '</td>';
            echo '<td><a class="button" href="' . esc_url($offertUrl) . '">Open offert detail</a>';
            echo '<a class="button" href="' . esc_url($printUrl) . '" style="margin-left:6px;">Print / Printable view</a>';
            if ($estimateId > 0) {
                echo '<a class="button" href="' . esc_url($filteredOffertsUrl) . '" style="margin-left:6px;">Open filtered offert list</a>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private function renderOffertFilterForm(mixed $estimateId, mixed $status, mixed $documentNumber): void
    {
        $estimateValue = is_scalar($estimateId) ? (string) $estimateId : '';
        $statusValue = is_scalar($status) ? (string) $status : '';
        $documentNumberValue = is_scalar($documentNumber) ? (string) $documentNumber : '';
        $clearUrl = admin_url('admin.php?page=trn_offerts');

        echo '<form method="get" style="margin:10px 0;">';
        echo '<input type="hidden" name="page" value="trn_offerts">';
        echo '<label style="margin-right:8px;">estimate_id <input type="text" name="estimate_id" value="' . esc_attr($estimateValue) . '" class="small-text"></label>';
        echo '<label style="margin-right:8px;">status <select name="status">';
        echo '<option value=""></option>';
        foreach (['issued', 'accepted', 'rejected', 'archived'] as $allowedStatus) {
            echo '<option value="' . esc_attr($allowedStatus) . '"' . selected($statusValue, $allowedStatus, false) . '>' . esc_html($allowedStatus) . '</option>';
        }
        echo '</select></label>';
        echo '<label style="margin-right:8px;">document_number <input type="text" name="document_number" value="' . esc_attr($documentNumberValue) . '" class="regular-text"></label>';
        submit_button('Filter', 'secondary', 'submit', false);
        echo '<a class="button button-secondary" href="' . esc_url($clearUrl) . '" style="margin-left:6px;">Clear filters</a>';
        echo '</form>';
    }

    private function renderDossierFilterForm(int $projectId): void
    {
        $clearUrl = admin_url('admin.php?page=trn_dossier');
        $projectIdValue = $projectId > 0 ? (string) $projectId : '';

        echo '<form method="get" style="margin:10px 0;">';
        echo '<input type="hidden" name="page" value="trn_dossier">';
        echo '<label style="margin-right:8px;">project_id <input type="text" name="project_id" value="' . esc_attr($projectIdValue) . '" class="small-text"></label>';
        submit_button('Open', 'secondary', 'submit', false);
        echo '<a class="button button-secondary" href="' . esc_url($clearUrl) . '" style="margin-left:6px;">Clear</a>';
        echo '</form>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderDossierEstimatesTable(array $rows): void
    {
        echo '<h2>Estimates for this project</h2>';
        echo '<table class="widefat striped"><thead><tr><th>id</th><th>title</th><th>status</th><th>currency</th><th>vat_rate_percent</th><th>labour_rate_minor</th><th>calculated_at</th><th>Actions</th></tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="8">No estimates linked to this project.</td></tr>';
        }
        foreach ($rows as $row) {
            $estimateId = (int) ($row['id'] ?? 0);
            $estimateUrl = admin_url('admin.php?page=trn_estimates&estimate_id=' . $estimateId);
            $offertsUrl = admin_url('admin.php?page=trn_offerts&estimate_id=' . $estimateId);
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['title'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['currency'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['vat_rate_percent'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['labour_rate_minor'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['calculated_at'] ?? '')) . '</td>';
            echo '<td><a class="button" href="' . esc_url($estimateUrl) . '">Open estimate</a>';
            echo '<a class="button" href="' . esc_url($offertsUrl) . '" style="margin-left:6px;">Open filtered offerts list for this estimate</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderDossierOffertsTable(array $rows): void
    {
        echo '<h2>Offerts for estimates of this project</h2>';
        echo '<table class="widefat striped"><thead><tr><th>id</th><th>estimate_id</th><th>document_number</th><th>version_no</th><th>status</th><th>total_inc_vat_minor</th><th>issued_at</th><th>Actions</th></tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="8">No offerts linked to this project.</td></tr>';
        }
        foreach ($rows as $row) {
            $offertId = (int) ($row['id'] ?? 0);
            $estimateId = (int) ($row['estimate_id'] ?? 0);
            $offertUrl = admin_url('admin.php?page=trn_offerts&offert_id=' . $offertId);
            $offertsUrl = admin_url('admin.php?page=trn_offerts&estimate_id=' . $estimateId);
            $invoicesUrl = admin_url('admin.php?page=trn_invoices&offert_id=' . $offertId . '&estimate_id=' . $estimateId);
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['estimate_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['document_number'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['version_no'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['total_inc_vat_minor'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($row['issued_at'] ?? '')) . '</td>';
            echo '<td><a class="button" href="' . esc_url($offertUrl) . '">Open offert detail</a>';
            echo '<a class="button" href="' . esc_url($offertsUrl) . '" style="margin-left:6px;">Open filtered offerts list for this estimate</a>';
            echo '<a class="button" href="' . esc_url($invoicesUrl) . '" style="margin-left:6px;">Open invoices list filtered by offert/invoice context</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderDossierInvoicesTable(array $rows): void
    {
        echo '<h2>Invoices linked to those offerts/estimates</h2>';
        echo '<table class="widefat striped"><thead><tr><th>id</th><th>offert_id</th><th>estimate_id</th><th>document_number</th><th>version_no</th><th>status</th><th>total_inc_vat_minor</th><th>issued_at</th><th>paid_total_minor</th><th>outstanding_minor</th><th>payment_count</th><th>Actions</th></tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="12">No invoices linked to this project.</td></tr>';
        }
        foreach ($rows as $row) {
            $invoiceId = (int) ($row['id'] ?? 0);
            $invoiceUrl = admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId);
            $paymentsUrl = admin_url('admin.php?page=trn_payments&invoice_id=' . $invoiceId);
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['offert_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['estimate_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['document_number'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['version_no'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['total_inc_vat_minor'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($row['issued_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['paid_total_minor'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($row['outstanding_minor'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($row['payment_count'] ?? 0)) . '</td>';
            echo '<td><a class="button" href="' . esc_url($invoiceUrl) . '">Open invoice detail</a>';
            echo '<a class="button" href="' . esc_url($paymentsUrl) . '" style="margin-left:6px;">Open payments register filtered by invoice_id</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderDossierPaymentsTable(array $rows): void
    {
        echo '<h2>Payments linked to those invoices</h2>';
        echo '<table class="widefat striped"><thead><tr><th>id</th><th>invoice_id</th><th>payment_date</th><th>amount_minor</th><th>currency</th><th>method</th><th>reference</th><th>created_at</th><th>Actions</th></tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="9">No payments linked to this project.</td></tr>';
        }
        foreach ($rows as $row) {
            $invoiceId = (int) ($row['invoice_id'] ?? 0);
            $invoiceUrl = admin_url('admin.php?page=trn_invoices&invoice_id=' . $invoiceId);
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['invoice_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['payment_date'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['amount_minor'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($row['currency'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['method'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['reference'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
            echo '<td><a class="button" href="' . esc_url($invoiceUrl) . '">Open invoice detail</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<string, string> $filters */
    private function renderPaymentFilterForm(array $filters): void
    {
        $clearUrl = admin_url('admin.php?page=trn_payments');

        echo '<form method="get" style="margin:10px 0;">';
        echo '<input type="hidden" name="page" value="trn_payments">';
        echo '<label style="margin-right:8px;">invoice_id <input type="text" name="invoice_id" value="' . esc_attr($filters['invoice_id'] ?? '') . '" class="small-text"></label>';
        echo '<label style="margin-right:8px;">currency <input type="text" name="currency" value="' . esc_attr($filters['currency'] ?? '') . '" class="small-text"></label>';
        echo '<label style="margin-right:8px;">method <input type="text" name="method" value="' . esc_attr($filters['method'] ?? '') . '" class="small-text"></label>';
        echo '<label style="margin-right:8px;">reference <input type="text" name="reference" value="' . esc_attr($filters['reference'] ?? '') . '" class="regular-text"></label>';
        submit_button('Filter', 'secondary', 'submit', false);
        echo '<a class="button button-secondary" href="' . esc_url($clearUrl) . '" style="margin-left:6px;">Clear filters</a>';
        echo '</form>';
    }

    /** @param array<string, string> $filters */
    private function renderInvoiceFilterForm(array $filters): void
    {
        $clearUrl = admin_url('admin.php?page=trn_invoices');

        echo '<form method="get" style="margin:10px 0;">';
        echo '<input type="hidden" name="page" value="trn_invoices">';
        echo '<label style="margin-right:8px;">offert_id <input type="text" name="offert_id" value="' . esc_attr($filters['offert_id'] ?? '') . '" class="small-text"></label>';
        echo '<label style="margin-right:8px;">estimate_id <input type="text" name="estimate_id" value="' . esc_attr($filters['estimate_id'] ?? '') . '" class="small-text"></label>';
        echo '<label style="margin-right:8px;">status <select name="status">';
        echo '<option value=""></option>';
        foreach (['issued', 'partially_paid', 'paid', 'archived'] as $allowedStatus) {
            echo '<option value="' . esc_attr($allowedStatus) . '"' . selected($filters['status'] ?? '', $allowedStatus, false) . '>' . esc_html($allowedStatus) . '</option>';
        }
        echo '</select></label>';
        echo '<label style="margin-right:8px;">document_number <input type="text" name="document_number" value="' . esc_attr($filters['document_number'] ?? '') . '" class="regular-text"></label>';
        submit_button('Filter', 'secondary', 'submit', false);
        echo '<a class="button button-secondary" href="' . esc_url($clearUrl) . '" style="margin-left:6px;">Clear filters</a>';
        echo '</form>';
    }

    /** @param array<string, string> $filters */
    private function renderCreditNoteFilterForm(array $filters): void
    {
        $clearUrl = admin_url('admin.php?page=trn_credit_notes');

        echo '<form method="get" style="margin:10px 0;">';
        echo '<input type="hidden" name="page" value="trn_credit_notes">';
        echo '<label style="margin-right:8px;">invoice_id <input type="text" name="invoice_id" value="' . esc_attr($filters['invoice_id'] ?? '') . '" class="small-text"></label>';
        echo '<label style="margin-right:8px;">status <select name="status">';
        echo '<option value=""></option>';
        foreach (['issued', 'archived'] as $allowedStatus) {
            echo '<option value="' . esc_attr($allowedStatus) . '"' . selected($filters['status'] ?? '', $allowedStatus, false) . '>' . esc_html($allowedStatus) . '</option>';
        }
        echo '</select></label>';
        echo '<label style="margin-right:8px;">document_number <input type="text" name="document_number" value="' . esc_attr($filters['document_number'] ?? '') . '" class="regular-text"></label>';
        submit_button('Filter', 'secondary', 'submit', false);
        echo '<a class="button button-secondary" href="' . esc_url($clearUrl) . '" style="margin-left:6px;">Clear filters</a>';
        echo '</form>';
    }

    /** @param array<string, int> $summary */
    private function renderInvoiceLedgerSummary(array $summary, string $currency): void
    {
        echo '<div style="margin:10px 0; padding:10px; border:1px solid #ccd0d4; background:#fff;">';
        echo '<h2 style="margin-top:0;">Invoice Register Summary</h2>';
        echo '<table class="widefat striped" style="max-width:900px;"><tbody>';
        echo '<tr><th>invoices_count</th><td>' . esc_html((string) ($summary['invoices_count'] ?? 0)) . '</td></tr>';
        echo '<tr><th>issued_total_minor</th><td>' . esc_html($this->formatMinorMoney($summary['issued_total_minor'] ?? 0, $currency)) . '</td></tr>';
        echo '<tr><th>paid_total_minor</th><td>' . esc_html($this->formatMinorMoney($summary['paid_total_minor'] ?? 0, $currency)) . '</td></tr>';
        echo '<tr><th>outstanding_total_minor</th><td>' . esc_html($this->formatMinorMoney($summary['outstanding_total_minor'] ?? 0, $currency)) . '</td></tr>';
        echo '<tr><th>fully_paid_count</th><td>' . esc_html((string) ($summary['fully_paid_count'] ?? 0)) . '</td></tr>';
        echo '<tr><th>partially_paid_count</th><td>' . esc_html((string) ($summary['partially_paid_count'] ?? 0)) . '</td></tr>';
        echo '<tr><th>archived_count</th><td>' . esc_html((string) ($summary['archived_count'] ?? 0)) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';
    }

    /** @param array<int, array<string, mixed>> $invoices */
    private function invoiceListCurrency(array $invoices): string
    {
        foreach ($invoices as $invoice) {
            $currency = strtoupper(trim((string) ($invoice['currency'] ?? '')));
            if ($currency !== '') {
                return $currency;
            }
        }

        return 'SEK';
    }

    /** @param array<int, mixed> $rows */
    private function renderSnapshotLabourTable(array $rows, string $currency): void
    {
        if ($rows === []) {
            $this->renderEmptyState('No labour lines in snapshot.');
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>id</th><th>line_title_ru_snapshot</th><th>line_title_sv_snapshot</th><th>unit_code_snapshot</th><th>quantity</th><th>speed_profile</th><th>norm_per_hour_snapshot</th><th>calculated_hours</th><th>labour_subtotal_minor</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html($this->valueOrDash($row['id'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['line_title_ru_snapshot'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['line_title_sv_snapshot'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['unit_code_snapshot'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['quantity'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['speed_profile'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['norm_per_hour_snapshot'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['calculated_hours'] ?? null)) . '</td>';
            echo '<td>' . esc_html(is_numeric($row['labour_subtotal_minor'] ?? null) ? $this->formatMinorMoney($row['labour_subtotal_minor'], $currency) : '—') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, mixed> $rows */
    private function renderSnapshotMaterialTable(array $rows, string $currency): void
    {
        if ($rows === []) {
            $this->renderEmptyState('No material lines in snapshot.');
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>id</th><th>material_name_ru_snapshot</th><th>material_name_sv_snapshot</th><th>unit_code_snapshot</th><th>quantity</th><th>coverage_snapshot</th><th>sell_price_minor_snapshot</th><th>subtotal_minor</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html($this->valueOrDash($row['id'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['material_name_ru_snapshot'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['material_name_sv_snapshot'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['unit_code_snapshot'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['quantity'] ?? null)) . '</td>';
            echo '<td>' . esc_html($this->valueOrDash($row['coverage_snapshot'] ?? null)) . '</td>';
            echo '<td>' . esc_html(is_numeric($row['sell_price_minor_snapshot'] ?? null) ? $this->formatMinorMoney($row['sell_price_minor_snapshot'], $currency) : '—') . '</td>';
            echo '<td>' . esc_html(is_numeric($row['subtotal_minor'] ?? null) ? $this->formatMinorMoney($row['subtotal_minor'], $currency) : '—') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private function renderOffertPrint(int $offertId): void
    {
        $offert = $this->factory->offerts()->find($offertId);
        if ($offert === null) {
            echo '<h2>Offert print view</h2><p>Offert not found.</p>';

            return;
        }

        $reader = new OffertSnapshotReader();
        $snapshot = $reader->read($offert);
        $renderer = new OffertPrintRenderer();
        $renderer->render($offert, $snapshot, $this->loadOffertPrintContext($offert));
    }

    private function renderInvoicePrint(int $invoiceId): void
    {
        $invoice = $this->factory->invoices()->find($invoiceId);
        if ($invoice === null) {
            echo '<h2>Invoice print view</h2><p>Invoice not found.</p>';

            return;
        }

        $snapshot = (new OffertSnapshotReader())->read($invoice);
        $renderer = new InvoicePrintRenderer();
        $renderer->render($invoice, $snapshot, $this->loadInvoicePrintContext($invoice));
    }

    private function renderCreditNotePrint(int $creditNoteId): void
    {
        $creditNote = $this->factory->creditNotes()->find($creditNoteId);
        if ($creditNote === null) {
            echo '<h2>Credit note print view</h2><p>Credit note not found.</p>';

            return;
        }

        $snapshot = (new CreditNoteSnapshotReader())->read($creditNote);
        (new CreditNotePrintRenderer())->render($creditNote, $snapshot, $this->loadCreditNoteContext($creditNote));
    }

    /**
     * @param array<string, mixed> $creditNote
     * @return array{source_invoice: array<string, mixed>, source_offert: array<string, mixed>, source_estimate: array<string, mixed>, project: array<string, mixed>, property: array<string, mixed>, client: array<string, mixed>, document_settings: array<string, string>}
     */
    private function loadCreditNoteContext(array $creditNote): array
    {
        $context = [
            'source_invoice' => [],
            'source_offert' => [],
            'source_estimate' => [],
            'project' => [],
            'property' => [],
            'client' => [],
            'document_settings' => (new DocumentSettings())->get(),
        ];

        $invoiceId = (int) ($creditNote['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $sourceInvoice = $this->factory->invoices()->find($invoiceId);
            if ($sourceInvoice !== null) {
                $context['source_invoice'] = $sourceInvoice;
            }
        }

        $offertId = (int) ($creditNote['offert_id'] ?? 0);
        if ($offertId <= 0) {
            $offertId = (int) ($context['source_invoice']['offert_id'] ?? 0);
        }
        if ($offertId > 0) {
            $sourceOffert = $this->factory->offerts()->find($offertId);
            if ($sourceOffert !== null) {
                $context['source_offert'] = $sourceOffert;
            }
        }

        $estimateId = (int) ($creditNote['estimate_id'] ?? 0);
        if ($estimateId <= 0) {
            $estimateId = (int) ($context['source_invoice']['estimate_id'] ?? 0);
        }
        if ($estimateId <= 0) {
            $estimateId = (int) ($context['source_offert']['estimate_id'] ?? 0);
        }

        if ($estimateId <= 0) {
            return $context;
        }

        $estimate = $this->factory->estimates()->find($estimateId);
        if ($estimate === null) {
            return $context;
        }
        $context['source_estimate'] = $estimate;

        $projectId = (int) ($estimate['project_id'] ?? 0);
        if ($projectId <= 0) {
            return $context;
        }

        $project = $this->factory->projects()->find($projectId);
        if ($project === null) {
            return $context;
        }
        $context['project'] = $project;

        $propertyId = (int) ($project['property_id'] ?? 0);
        if ($propertyId <= 0) {
            return $context;
        }

        $property = $this->factory->properties()->find($propertyId);
        if ($property === null) {
            return $context;
        }
        $context['property'] = $property;

        $clientId = (int) ($property['client_id'] ?? 0);
        if ($clientId <= 0) {
            return $context;
        }

        $client = $this->factory->clients()->find($clientId);
        if ($client !== null) {
            $context['client'] = $client;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $invoice
     * @return array{
     *     source_offert: array<string, mixed>,
     *     source_estimate: array<string, mixed>,
     *     project: array<string, mixed>,
     *     property: array<string, mixed>,
     *     client: array<string, mixed>,
     *     payments: array<int, array<string, mixed>>,
     *     payment_summary: array<string, mixed>,
     *     document_profile: array<string, string|int>
     * }
     */
    private function loadInvoicePrintContext(array $invoice): array
    {
        $context = [
            'source_offert' => [],
            'source_estimate' => [],
            'project' => [],
            'property' => [],
            'client' => [],
            'payments' => [],
            'payment_summary' => [],
            'document_profile' => (new DocumentProfileProvider())->get(),
        ];

        $invoiceId = (int) ($invoice['id'] ?? 0);
        if ($invoiceId > 0) {
            $payments = $this->factory->invoicePayments()->byInvoice($invoiceId);
            $context['payments'] = $payments;
            $context['payment_summary'] = (new InvoicePaymentSummaryCalculator())->calculate($invoice, $payments);
        }

        $offertId = (int) ($invoice['offert_id'] ?? 0);
        if ($offertId > 0) {
            $sourceOffert = $this->factory->offerts()->find($offertId);
            if ($sourceOffert !== null) {
                $context['source_offert'] = $sourceOffert;
            }
        }

        $estimateId = (int) ($invoice['estimate_id'] ?? 0);
        if ($estimateId <= 0) {
            $estimateId = (int) ($context['source_offert']['estimate_id'] ?? 0);
        }
        if ($estimateId <= 0) {
            return $context;
        }

        $sourceEstimate = $this->factory->estimates()->find($estimateId);
        if ($sourceEstimate === null) {
            return $context;
        }
        $context['source_estimate'] = $sourceEstimate;

        $projectId = (int) ($sourceEstimate['project_id'] ?? 0);
        if ($projectId <= 0) {
            return $context;
        }

        $project = $this->factory->projects()->find($projectId);
        if ($project === null) {
            return $context;
        }
        $context['project'] = $project;

        $propertyId = (int) ($project['property_id'] ?? 0);
        if ($propertyId <= 0) {
            return $context;
        }

        $property = $this->factory->properties()->find($propertyId);
        if ($property === null) {
            return $context;
        }
        $context['property'] = $property;

        $clientId = (int) ($property['client_id'] ?? 0);
        if ($clientId <= 0) {
            return $context;
        }

        $client = $this->factory->clients()->find($clientId);
        if ($client !== null) {
            $context['client'] = $client;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $offert
     * @return array{estimate: array<string, mixed>, project: array<string, mixed>, property: array<string, mixed>, client: array<string, mixed>, document_profile: array<string, string|int>}
     */
    private function loadOffertPrintContext(array $offert): array
    {
        $context = [
            'estimate' => [],
            'project' => [],
            'property' => [],
            'client' => [],
            'document_profile' => (new DocumentProfileProvider())->get(),
        ];

        $estimateId = (int) ($offert['estimate_id'] ?? 0);
        if ($estimateId <= 0) {
            return $context;
        }

        $estimate = $this->factory->estimates()->find($estimateId);
        if ($estimate === null) {
            return $context;
        }
        $context['estimate'] = $estimate;

        $projectId = (int) ($estimate['project_id'] ?? 0);
        if ($projectId <= 0) {
            return $context;
        }

        $project = $this->factory->projects()->find($projectId);
        if ($project === null) {
            return $context;
        }
        $context['project'] = $project;

        $propertyId = (int) ($project['property_id'] ?? 0);
        if ($propertyId <= 0) {
            return $context;
        }

        $property = $this->factory->properties()->find($propertyId);
        if ($property === null) {
            return $context;
        }
        $context['property'] = $property;

        $clientId = (int) ($property['client_id'] ?? 0);
        if ($clientId <= 0) {
            return $context;
        }

        $client = $this->factory->clients()->find($clientId);
        if ($client !== null) {
            $context['client'] = $client;
        }

        return $context;
    }

    /** @param array<string, mixed> $postPayload */
    private function handleDocumentSettingsSave(array $postPayload): void
    {
        (new DocumentSettings())->save($postPayload);
        wp_safe_redirect(admin_url('admin.php?page=trn_settings&trn_result=ok'));
        exit;
    }

    /** @param array<string, mixed> $postPayload */
    private function handleDocumentProfileSettingsSave(array $postPayload): void
    {
        (new DocumentProfileProvider())->save($postPayload);
        wp_safe_redirect(admin_url('admin.php?page=trn_settings&trn_result=ok'));
        exit;
    }

    /** @param array<string, string> $labels @param array<string, string> $values */
    private function renderDocumentSettingsFieldset(string $title, array $labels, array $values): void
    {
        echo '<h3>' . esc_html($title) . '</h3>';
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($labels as $field => $label) {
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr($field) . '">' . esc_html($label) . '</label></th>';
            echo '<td><input class="regular-text" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="' . esc_attr((string) ($values[$field] ?? '')) . '"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<string, string> $labels @param array<string, string> $values */
    private function renderDocumentSettingsTextareaFieldset(string $title, array $labels, array $values): void
    {
        echo '<h3>' . esc_html($title) . '</h3>';
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($labels as $field => $label) {
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr($field) . '">' . esc_html($label) . '</label></th>';
            echo '<td><textarea class="large-text" rows="4" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '">' . esc_textarea((string) ($values[$field] ?? '')) . '</textarea></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<string, string> $labels @param array<string, string|int> $values */
    private function renderDocumentProfileFieldset(string $title, array $labels, array $values): void
    {
        echo '<h3>' . esc_html($title) . '</h3>';
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($labels as $field => $label) {
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr($field) . '">' . esc_html($label) . '</label></th>';
            echo '<td><input class="regular-text" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="' . esc_attr((string) ($values[$field] ?? '')) . '"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<string, string> $labels @param array<string, string|int> $values */
    private function renderDocumentProfileTextareaFieldset(string $title, array $labels, array $values): void
    {
        echo '<h3>' . esc_html($title) . '</h3>';
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($labels as $field => $label) {
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr($field) . '">' . esc_html($label) . '</label></th>';
            echo '<td><textarea class="large-text" rows="4" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '">' . esc_textarea((string) ($values[$field] ?? '')) . '</textarea></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * @param mixed $value
     */
    private function valueOrDash($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }

    /** @param mixed $value */
    private function formatMinorValue($value): string
    {
        return $this->formatMinorMoney($value, 'SEK');
    }

    /**
     * @param int|float|string|null $minor
     */
    private function formatMinorMoney($minor, string $currency = 'SEK'): string
    {
        if (! is_numeric($minor)) {
            return '—';
        }

        $major = ((float) $minor) / 100;
        return number_format($major, 2, '.', ',') . ' ' . strtoupper($currency);
    }
}
