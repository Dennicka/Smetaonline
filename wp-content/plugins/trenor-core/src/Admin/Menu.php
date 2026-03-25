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
        add_action('admin_enqueue_scripts', [$this, 'enqueueShellAssets']);
    }

    public function addMenus(): void
    {
        add_menu_page('Smeta', 'Smeta', 'read', 'trn_dashboard', [$this->controller, 'renderDashboard'], 'dashicons-building', 30);
        add_submenu_page('trn_dashboard', 'Dashboard', 'Workspace', 'read', 'trn_dashboard', [$this->controller, 'renderDashboard']);
        add_submenu_page('trn_dashboard', 'Dossier / Timeline / Досье', 'Dossier / Timeline', 'trn_manage_estimates', 'trn_dossier', [$this->controller, 'renderDossier']);

        add_submenu_page('trn_dashboard', 'Сметы', 'Estimates', 'trn_manage_estimates', 'trn_estimates', [$this->controller, 'renderEstimates']);
        add_submenu_page('trn_dashboard', 'Offerter / Offerts / Оферты', 'Offerts / Avtal / ÄTA', 'trn_issue_offerts', 'trn_offerts', [$this->controller, 'renderOfferts']);
        add_submenu_page('trn_dashboard', 'Fakturor / Invoices / Фактуры', 'Invoices', 'trn_issue_invoices', 'trn_invoices', [$this->controller, 'renderInvoices']);
        add_submenu_page('trn_dashboard', 'Betalningar / Payments / Оплаты', 'Payments', 'trn_record_payments', 'trn_payments', [$this->controller, 'renderPayments']);
        add_submenu_page('trn_dashboard', 'Påminnelser / Reminders / Напоминания', 'Reminders', 'trn_issue_reminders', 'trn_reminders', [$this->controller, 'renderReminders']);
        add_submenu_page('trn_dashboard', 'Kreditnotor / Credit Notes / Кредит-ноты', 'Credit Notes', 'trn_issue_credit_notes', 'trn_credit_notes', [$this->controller, 'renderCreditNotes']);
        add_submenu_page('trn_dashboard', 'Operational Reports / Export', 'Operational Reports', 'trn_view_operational_reports', 'trn_operational_reports', [$this->controller, 'renderOperationalReports']);

        add_submenu_page('trn_dashboard', 'Suppliers / Price import', 'Suppliers / Prices / Import', 'trn_manage_prices', 'trn_suppliers_prices', [$this->controller, 'renderSuppliersPrices']);
        add_submenu_page('trn_dashboard', 'Клиенты', 'Clients', 'trn_manage_clients', 'trn_clients', [$this->controller, 'renderClients']);
        add_submenu_page('trn_dashboard', 'Объекты', 'Properties', 'trn_manage_projects', 'trn_properties', [$this->controller, 'renderProperties']);
        add_submenu_page('trn_dashboard', 'Проекты', 'Projects', 'trn_manage_projects', 'trn_projects', [$this->controller, 'renderProjects']);
        add_submenu_page('trn_dashboard', 'Помещения', 'Rooms', 'trn_manage_projects', 'trn_rooms', [$this->controller, 'renderRooms']);
        add_submenu_page('trn_dashboard', 'Работы', 'Work items', 'trn_manage_catalogs', 'trn_work_items', [$this->controller, 'renderWorkItems']);
        add_submenu_page('trn_dashboard', 'Материалы', 'Materials', 'trn_manage_catalogs', 'trn_materials', [$this->controller, 'renderMaterials']);

        add_submenu_page('trn_dashboard', 'Настройки', 'Settings / Backup', 'trn_manage_templates', 'trn_settings', [$this->controller, 'renderSettings']);
        add_submenu_page('trn_dashboard', 'Журнал', 'Audit log', 'trn_archive_records', 'trn_audit_log', [$this->controller, 'renderAuditLog']);
    }

    public function enqueueShellAssets(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW);
        $page = is_string($page) ? sanitize_key($page) : '';
        if (! str_starts_with($page, 'trn_')) {
            return;
        }

        wp_enqueue_style(
            'trn-admin-shell',
            plugins_url('../../assets/admin-shell.css', __FILE__),
            [],
            '1.0.0'
        );
    }
}
