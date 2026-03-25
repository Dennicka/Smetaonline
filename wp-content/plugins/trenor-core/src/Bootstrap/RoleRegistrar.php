<?php

declare(strict_types=1);

namespace Trenor\Core\Bootstrap;

final class RoleRegistrar
{
    /** @var array<string, array<string, bool>> */
    private const ROLE_CAPABILITY_MAP = [
        'trn_owner_admin' => [
            'read' => true,
            'trn_view_operational_reports' => true,
            'trn_manage_clients' => true,
            'trn_manage_projects' => true,
            'trn_manage_estimates' => true,
            'trn_issue_offerts' => true,
            'trn_issue_invoices' => true,
            'trn_issue_credit_notes' => true,
            'trn_issue_reminders' => true,
            'trn_record_payments' => true,
            'trn_manage_catalogs' => true,
            'trn_manage_prices' => true,
            'trn_manage_templates' => true,
            'trn_view_margin' => true,
            'trn_archive_records' => true,
            'trn_manage_backups' => true,
        ],
        'trn_manager' => [
            'read' => true,
            'trn_view_operational_reports' => true,
            'trn_manage_clients' => true,
            'trn_manage_projects' => true,
            'trn_manage_estimates' => true,
            'trn_issue_offerts' => true,
            'trn_issue_invoices' => true,
            'trn_issue_credit_notes' => true,
            'trn_issue_reminders' => true,
            'trn_record_payments' => true,
            'trn_manage_catalogs' => true,
            'trn_manage_prices' => true,
            'trn_manage_templates' => true,
            'trn_view_margin' => true,
            'trn_archive_records' => true,
        ],
        'trn_accountant' => [
            'read' => true,
            'trn_view_operational_reports' => true,
            'trn_issue_invoices' => true,
            'trn_issue_credit_notes' => true,
            'trn_issue_reminders' => true,
            'trn_record_payments' => true,
            'trn_view_margin' => true,
        ],
        'trn_worker' => [
            'read' => true,
            'trn_manage_estimates' => true,
        ],
        'trn_viewer' => [
            'read' => true,
        ],
        // Backward-compatibility alias for legacy deployments.
        'trn_viewer_accountant' => [
            'read' => true,
            'trn_view_operational_reports' => true,
            'trn_issue_invoices' => true,
            'trn_issue_credit_notes' => true,
            'trn_issue_reminders' => true,
            'trn_record_payments' => true,
            'trn_view_margin' => true,
        ],
    ];

    /** @var array<string, string> */
    private const ROLE_LABELS = [
        'trn_owner_admin' => 'Owner/Admin',
        'trn_manager' => 'Manager',
        'trn_accountant' => 'Accountant',
        'trn_worker' => 'Worker',
        'trn_viewer' => 'Viewer',
        'trn_viewer_accountant' => 'Viewer/Accountant',
    ];

    /**
     * @return array<string, bool>
     */
    public static function capabilities(): array
    {
        return self::allCapabilities();
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function roleCapabilityMap(): array
    {
        return self::ROLE_CAPABILITY_MAP;
    }

    public static function register(): void
    {
        $allCaps = self::allCapabilities();

        foreach (self::roleCapabilityMap() as $role => $capabilities) {
            remove_role($role);
            add_role($role, self::ROLE_LABELS[$role], $capabilities);
        }

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

    /**
     * @return array<string, bool>
     */
    private static function allCapabilities(): array
    {
        $allCapabilities = [];
        foreach (self::ROLE_CAPABILITY_MAP as $capabilities) {
            foreach ($capabilities as $capability => $enabled) {
                if ($capability === 'read' || $enabled !== true) {
                    continue;
                }

                $allCapabilities[$capability] = true;
            }
        }

        return $allCapabilities;
    }
}
