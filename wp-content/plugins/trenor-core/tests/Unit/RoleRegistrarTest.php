<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Bootstrap\RoleRegistrar;

final class RoleRegistrarTest extends TestCase
{
    public function testCapabilitiesContainsDocumentAndFinanceBaselineCapabilities(): void
    {
        $capabilities = RoleRegistrar::capabilities();

        self::assertArrayHasKey('trn_manage_estimates', $capabilities);
        self::assertArrayHasKey('trn_issue_offerts', $capabilities);
        self::assertArrayHasKey('trn_issue_invoices', $capabilities);
        self::assertArrayHasKey('trn_record_payments', $capabilities);
        self::assertArrayHasKey('trn_issue_credit_notes', $capabilities);
        self::assertArrayHasKey('trn_archive_records', $capabilities);
        self::assertArrayHasKey('trn_manage_templates', $capabilities);
        self::assertArrayHasKey('trn_manage_backups', $capabilities);
    }

    public function testRoleCapabilityMatrixIsHardenedForWorkerAndViewer(): void
    {
        $map = RoleRegistrar::roleCapabilityMap();

        self::assertArrayHasKey('trn_worker', $map);
        self::assertArrayHasKey('trn_viewer', $map);

        self::assertArrayHasKey('trn_manage_estimates', $map['trn_worker']);
        self::assertArrayNotHasKey('trn_issue_invoices', $map['trn_worker']);
        self::assertArrayNotHasKey('trn_record_payments', $map['trn_worker']);
        self::assertArrayNotHasKey('trn_issue_credit_notes', $map['trn_worker']);
        self::assertArrayNotHasKey('trn_manage_templates', $map['trn_worker']);

        self::assertSame(['read' => true], $map['trn_viewer']);
    }

    public function testAccountantAndManagerGetOnlyExpectedCapabilities(): void
    {
        $map = RoleRegistrar::roleCapabilityMap();

        self::assertArrayHasKey('trn_accountant', $map);
        self::assertArrayHasKey('trn_manager', $map);

        self::assertArrayHasKey('trn_issue_invoices', $map['trn_accountant']);
        self::assertArrayHasKey('trn_record_payments', $map['trn_accountant']);
        self::assertArrayHasKey('trn_issue_credit_notes', $map['trn_accountant']);
        self::assertArrayNotHasKey('trn_issue_offerts', $map['trn_accountant']);
        self::assertArrayNotHasKey('trn_manage_estimates', $map['trn_accountant']);
        self::assertArrayNotHasKey('trn_archive_records', $map['trn_accountant']);

        self::assertArrayHasKey('trn_issue_offerts', $map['trn_manager']);
        self::assertArrayHasKey('trn_archive_records', $map['trn_manager']);
        self::assertArrayHasKey('trn_manage_templates', $map['trn_manager']);
        self::assertArrayNotHasKey('trn_manage_backups', $map['trn_manager']);
    }

    public function testOwnerAdminRoleRetainsAllCapabilities(): void
    {
        $map = RoleRegistrar::roleCapabilityMap();

        self::assertArrayHasKey('trn_owner_admin', $map);

        $ownerAdminCapabilities = array_keys(array_filter($map['trn_owner_admin']));
        foreach (array_keys(RoleRegistrar::capabilities()) as $capability) {
            self::assertContains($capability, $ownerAdminCapabilities);
        }
    }
}
