<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;

final class PageControllerCapabilityMappingTest extends TestCase
{
    /**
     * @param array{0:string,1:string} $input
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sensitiveActionProvider')]
    public function testSensitiveActionsUseExpectedCapabilities(array $input, string $expectedCapability): void
    {
        [$entity, $action] = $input;
        $controller = new PageController(new RepositoryFactory());

        $reflection = new ReflectionClass($controller);
        $requiredCapabilityMethod = $reflection->getMethod('requiredCapability');
        $requiredCapabilityMethod->setAccessible(true);

        $actualCapability = $requiredCapabilityMethod->invoke($controller, $entity, $action);

        self::assertSame($expectedCapability, $actualCapability);
    }

    /** @return array<string, array{0: array{0:string,1:string}, 1: string}> */
    public static function sensitiveActionProvider(): array
    {
        return [
            'estimate create' => [['estimate', 'create'], 'trn_manage_estimates'],
            'estimate archive' => [['estimate', 'archive'], 'trn_archive_records'],
            'offert issue' => [['offert', 'issue'], 'trn_issue_offerts'],
            'offert pdf download' => [['offert', 'download_pdf'], 'trn_issue_offerts'],
            'offert accept' => [['offert', 'accept'], 'trn_issue_offerts'],
            'offert reject' => [['offert', 'reject'], 'trn_issue_offerts'],
            'offert archive' => [['offert', 'archive'], 'trn_archive_records'],
            'invoice issue' => [['invoice', 'issue'], 'trn_issue_invoices'],
            'invoice pdf download' => [['invoice', 'download_pdf'], 'trn_issue_invoices'],
            'payment record' => [['invoice_payment', 'record'], 'trn_record_payments'],
            'credit note issue' => [['credit_note', 'issue'], 'trn_issue_credit_notes'],
            'credit note pdf download' => [['credit_note', 'download_pdf'], 'trn_issue_credit_notes'],
            'credit note archive' => [['credit_note', 'archive'], 'trn_archive_records'],
            'reminder issue' => [['reminder', 'issue'], 'trn_issue_reminders'],
            'reminder pdf download' => [['reminder', 'download_pdf'], 'trn_issue_reminders'],
            'reminder archive' => [['reminder', 'archive'], 'trn_archive_records'],
            'avtal issue' => [['avtal', 'issue'], 'trn_issue_offerts'],
            'avtal pdf download' => [['avtal', 'download_pdf'], 'trn_issue_offerts'],
            'avtal archive' => [['avtal', 'archive'], 'trn_archive_records'],
            'document settings save' => [['document_settings', 'save'], 'trn_manage_templates'],
            'document profile save' => [['document_profile_settings', 'save'], 'trn_manage_templates'],
        ];
    }
}
