<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

use Trenor\Core\Database\RepositoryFactory;

final class PageController
{
    private RepositoryFactory $factory;

    public function __construct(RepositoryFactory $factory)
    {
        $this->factory = $factory;
    }

    public function handleRequests(): void
    {
        if (! is_admin()) {
            return;
        }

        $entity = sanitize_key((string) ($_POST['trn_entity'] ?? ''));
        $action = sanitize_key((string) ($_POST['trn_action'] ?? ''));

        if ($entity === '' || $action === '') {
            return;
        }

        check_admin_referer('trn_' . $entity . '_' . $action);

        if ($entity === 'client') {
            $this->handleEntity($this->factory->clients(), 'trn_clients', ['name', 'org_number', 'email', 'phone']);
        }

        if ($entity === 'property') {
            $this->handleEntity($this->factory->properties(), 'trn_properties', ['client_id', 'name', 'address_line', 'city', 'postal_code']);
        }

        if ($entity === 'project') {
            $this->handleEntity($this->factory->projects(), 'trn_projects', ['property_id', 'name', 'code']);
        }

        if ($entity === 'room') {
            $this->handleEntity($this->factory->rooms(), 'trn_rooms', ['project_id', 'name', 'floor']);
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

        $rows = $this->factory->clients()->all();
        $fields = ['name', 'org_number', 'email', 'phone'];
        $this->renderEntityPage('Клиенты', 'client', $fields, $rows);
    }

    public function renderProperties(): void
    {
        if (! current_user_can('trn_manage_projects')) {
            wp_die('Forbidden');
        }

        $rows = $this->factory->properties()->all();
        $fields = ['client_id', 'name', 'address_line', 'city', 'postal_code'];
        $this->renderEntityPage('Объекты', 'property', $fields, $rows);
    }

    public function renderProjects(): void
    {
        if (! current_user_can('trn_manage_projects')) {
            wp_die('Forbidden');
        }

        $rows = $this->factory->projects()->all();
        $fields = ['property_id', 'name', 'code'];
        $this->renderEntityPage('Проекты', 'project', $fields, $rows);
    }

    public function renderRooms(): void
    {
        if (! current_user_can('trn_manage_projects')) {
            wp_die('Forbidden');
        }

        $rows = $this->factory->rooms()->all();
        $fields = ['project_id', 'name', 'floor'];
        $this->renderEntityPage('Помещения', 'room', $fields, $rows);
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
            echo '<tr>';
            echo '<td>' . esc_html((string) $row['id']) . '</td>';
            echo '<td>' . esc_html((string) $row['entity_type']) . '</td>';
            echo '<td>' . esc_html((string) $row['entity_id']) . '</td>';
            echo '<td>' . esc_html((string) $row['action']) . '</td>';
            echo '<td>' . esc_html((string) $row['actor_user_id']) . '</td>';
            echo '<td>' . esc_html((string) $row['created_at']) . '</td>';
            echo '<td><code>' . esc_html((string) $row['changes_json']) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /** @param array<int, string> $fields @param array<int, array<string,mixed>> $rows */
    private function renderEntityPage(string $title, string $entity, array $fields, array $rows): void
    {
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1>';
        echo '<h2>Создать</h2>';
        echo '<form method="post">';
        wp_nonce_field('trn_' . $entity . '_create');
        echo '<input type="hidden" name="trn_entity" value="' . esc_attr($entity) . '">';
        echo '<input type="hidden" name="trn_action" value="create">';

        foreach ($fields as $field) {
            echo '<p><label>' . esc_html($field) . '<br><input class="regular-text" name="' . esc_attr($field) . '" value=""></label></p>';
        }

        submit_button('Create');
        echo '</form>';

        echo '<h2>Список</h2><table class="widefat striped"><thead><tr>';
        foreach (array_merge(['id'], $fields, ['status']) as $col) {
            echo '<th>' . esc_html($col) . '</th>';
        }
        echo '<th>Actions</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach (array_merge(['id'], $fields, ['status']) as $col) {
                echo '<td>' . esc_html((string) ($row[$col] ?? '')) . '</td>';
            }
            echo '<td>';
            echo '<form method="post" style="display:inline-block; margin-right:8px;">';
            wp_nonce_field('trn_' . $entity . '_archive');
            echo '<input type="hidden" name="trn_entity" value="' . esc_attr($entity) . '">';
            echo '<input type="hidden" name="trn_action" value="archive">';
            echo '<input type="hidden" name="id" value="' . esc_attr((string) $row['id']) . '">';
            submit_button('Archive', 'secondary', 'submit', false);
            echo '</form>';
            echo '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    /** @param array<int,string> $fields */
    private function handleEntity(object $repository, string $redirectPage, array $fields): void
    {
        $action = sanitize_key((string) ($_POST['trn_action'] ?? ''));

        if ($action === 'create') {
            $data = [];
            foreach ($fields as $field) {
                $data[$field] = $_POST[$field] ?? '';
            }
            $repository->create($data);
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $data = [];
            foreach ($fields as $field) {
                $data[$field] = $_POST[$field] ?? '';
            }
            $repository->updateEntity($id, $data);
        }

        if ($action === 'archive') {
            $repository->archive((int) ($_POST['id'] ?? 0));
        }

        wp_safe_redirect(admin_url('admin.php?page=' . $redirectPage));
        exit;
    }
}
