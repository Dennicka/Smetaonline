<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

use Trenor\Core\Database\RepositoryFactory;
use Trenor\Core\Domain\Service\DocumentSequenceGenerator;
use Trenor\Core\Domain\Exception\EstimateCalculationException;
use Trenor\Core\Domain\Service\EstimateCalculator;
use Trenor\Core\Domain\Service\EstimateSnapshotService;
use Trenor\Core\Domain\Service\EstimateTotalsCalculator;
use Trenor\Core\Domain\Service\OffertFromEstimateService;

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
        echo '<div class="wrap"><h1>Настройки</h1>';
        $this->renderAdminNoticeFromRequest();
        echo '<p>Версия ядра: ' . esc_html((string) get_option('trn_core_version', 'unknown')) . '</p></div>';
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

        $snapshots = $this->factory->estimateSnapshots()->byEstimate($estimateId);
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

        $reader = new OffertSnapshotReader();
        $snapshot = $reader->read($offert);

        $renderer = new OffertDetailRenderer();
        $renderer->render($offert, $snapshot);
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

    /**
     * @param array<string, mixed> $offert
     * @return array{estimate: array<string, mixed>, project: array<string, mixed>, property: array<string, mixed>, client: array<string, mixed>}
     */
    private function loadOffertPrintContext(array $offert): array
    {
        $context = [
            'estimate' => [],
            'project' => [],
            'property' => [],
            'client' => [],
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
