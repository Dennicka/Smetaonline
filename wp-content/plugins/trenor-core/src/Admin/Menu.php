<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class Menu
{
    private PageController $controller;

    public function __construct(PageController $controller)
    {
        $this->controller = $controller;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenus']);
        add_action('admin_init', [$this->controller, 'handleRequests']);
    }

    public function addMenus(): void
    {
        add_menu_page('Smeta', 'Smeta', 'read', 'trn_dashboard', [$this->controller, 'renderDashboard'], 'dashicons-building', 30);
        add_submenu_page('trn_dashboard', 'Dashboard', 'Dashboard', 'read', 'trn_dashboard', [$this->controller, 'renderDashboard']);
        add_submenu_page('trn_dashboard', 'Клиенты', 'Клиенты', 'trn_manage_clients', 'trn_clients', [$this->controller, 'renderClients']);
        add_submenu_page('trn_dashboard', 'Объекты', 'Объекты', 'trn_manage_projects', 'trn_properties', [$this->controller, 'renderProperties']);
        add_submenu_page('trn_dashboard', 'Проекты', 'Проекты', 'trn_manage_projects', 'trn_projects', [$this->controller, 'renderProjects']);
        add_submenu_page('trn_dashboard', 'Помещения', 'Помещения', 'trn_manage_projects', 'trn_rooms', [$this->controller, 'renderRooms']);
        add_submenu_page('trn_dashboard', 'Работы', 'Работы', 'trn_manage_catalogs', 'trn_work_items', [$this->controller, 'renderWorkItems']);
        add_submenu_page('trn_dashboard', 'Материалы', 'Материалы', 'trn_manage_catalogs', 'trn_materials', [$this->controller, 'renderMaterials']);
        add_submenu_page('trn_dashboard', 'Сметы', 'Сметы', 'trn_manage_estimates', 'trn_estimates', [$this->controller, 'renderEstimates']);
        add_submenu_page('trn_dashboard', 'Настройки', 'Настройки', 'trn_manage_backups', 'trn_settings', [$this->controller, 'renderSettings']);
        add_submenu_page('trn_dashboard', 'Журнал', 'Журнал', 'trn_archive_records', 'trn_audit_log', [$this->controller, 'renderAuditLog']);
    }
}
