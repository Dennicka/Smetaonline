<?php

declare(strict_types=1);

namespace Trenor\Core\Bootstrap;

final class RoleRegistrar
{
    /**
     * @return array<string, bool>
     */
    public static function capabilities(): array
    {
        return [
            'trn_manage_clients' => true,
            'trn_manage_projects' => true,
            'trn_manage_estimates' => true,
            'trn_issue_offerts' => true,
            'trn_issue_invoices' => true,
            'trn_issue_credit_notes' => true,
            'trn_record_payments' => true,
            'trn_manage_catalogs' => true,
            'trn_manage_prices' => true,
            'trn_manage_templates' => true,
            'trn_view_margin' => true,
            'trn_archive_records' => true,
            'trn_manage_backups' => true,
        ];
    }

    public static function register(): void
    {
        $allCaps = self::capabilities();

        add_role('trn_owner_admin', 'Owner/Admin', array_merge(['read' => true], $allCaps));

        add_role('trn_manager', 'Manager', [
            'read' => true,
            'trn_manage_clients' => true,
            'trn_manage_projects' => true,
            'trn_manage_estimates' => true,
            'trn_issue_offerts' => true,
            'trn_issue_invoices' => true,
            'trn_issue_credit_notes' => true,
            'trn_record_payments' => true,
            'trn_manage_catalogs' => true,
            'trn_manage_prices' => true,
            'trn_manage_templates' => true,
            'trn_view_margin' => true,
            'trn_archive_records' => true,
        ]);

        add_role('trn_worker', 'Worker', [
            'read' => true,
            'trn_manage_estimates' => true,
            'trn_manage_templates' => true,
        ]);

        add_role('trn_viewer_accountant', 'Viewer/Accountant', [
            'read' => true,
            'trn_issue_invoices' => true,
            'trn_issue_credit_notes' => true,
            'trn_record_payments' => true,
            'trn_view_margin' => true,
        ]);

        $administrator = get_role('administrator');
        if ($administrator !== null) {
            foreach (array_keys($allCaps) as $capability) {
                $administrator->add_cap($capability);
            }
        }
    }

    public static function unregisterCapsFromAdministrator(): void
    {
        $administrator = get_role('administrator');
        if ($administrator === null) {
            return;
        }

        foreach (array_keys(self::capabilities()) as $capability) {
            $administrator->remove_cap($capability);
        }
    }
}
