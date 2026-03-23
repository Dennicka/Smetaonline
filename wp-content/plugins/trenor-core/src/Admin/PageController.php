<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

use Trenor\Core\Database\RepositoryFactory;
use Trenor\Core\Domain\Exception\EstimateCalculationException;
use Trenor\Core\Domain\Service\EstimateCalculator;
use Trenor\Core\Domain\Service\EstimateSnapshotService;
use Trenor\Core\Domain\Service\EstimateTotalsCalculator;

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
    }

    public function renderDashboard(): void
    {
        echo '<div class="wrap"><h1>Smeta / Dashboard</h1><p>Core plugin active.</p></div>';
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

        $selectedEstimateId = (int) ($_GET['estimate_id'] ?? 0);
        $estimates = $this->factory->estimates()->all();

        echo '<div class="wrap"><h1>Сметы</h1>';
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
        }

        echo '</div>';
    }

    public function renderSettings(): void
    {
        echo '<div class="wrap"><h1>Настройки</h1><p>Версия ядра: ' . esc_html((string) get_option('trn_core_version', 'unknown')) . '</p></div>';
    }

    public function renderAuditLog(): void
    {
        $rows = $this->factory->auditLogs();
        echo '<div class="wrap"><h1>Журнал</h1><table class="widefat striped"><thead><tr>';
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
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1><h2>Создать</h2><form method="post">';
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

        echo '<h3>Work lines</h3>';
        $this->renderEstimateLinesTable($lines);
        echo '<h3>Material lines</h3>';
        $this->renderMaterialLinesTable($materialLines);
        echo '<h3>Итоги</h3><ul>';
        foreach ($totals as $key => $value) {
            echo '<li>' . esc_html($key) . ': <strong>' . esc_html((string) $value) . '</strong></li>';
        }
        echo '</ul>';
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

        return 'read';
    }
}
