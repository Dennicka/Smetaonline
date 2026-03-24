<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\OffertPrintViewModel;

final class OffertPrintViewModelTest extends TestCase
{
    private OffertPrintViewModel $viewModel;

    protected function setUp(): void
    {
        $this->viewModel = new OffertPrintViewModel();
    }

    public function testBuildIncludesIssuerRecipientAndProjectBlocks(): void
    {
        $result = $this->viewModel->build(
            ['estimate_id' => 10, 'issued_at' => '2026-03-01 09:00:00'],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            [
                'project' => ['name' => 'Project A', 'code' => 'PA-10'],
                'property' => ['name' => 'Property A', 'address_line' => 'Main 1', 'city' => 'Stockholm', 'postal_code' => '11111'],
                'client' => ['name' => 'Client A', 'org_number' => '556677-8899', 'email' => 'a@example.com', 'phone' => '12345'],
                'document_profile' => ['company_name' => 'Issuer AB'],
            ]
        );

        self::assertSame('Issuer AB', $result['issuer']['company_name']);
        self::assertSame('Client A', $result['recipient']['client_name']);
        self::assertSame('Project A', $result['project_object']['project_name']);
        self::assertSame('Property A', $result['project_object']['property_name']);
    }

    public function testBuildDerivesOffertValidUntilFromIssuedAtAndProfileDays(): void
    {
        $result = $this->viewModel->build(
            ['issued_at' => '2026-02-14 13:00:00'],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            ['document_profile' => ['offert_valid_days' => '20']]
        );

        self::assertSame('2026-03-06', $result['document']['offert_valid_until']);
        self::assertSame('2026-03-06', $result['terms_acceptance']['offert_valid_until']);
    }

    public function testBuildIncludesTermsAndAcceptancePlaceholdersSafely(): void
    {
        $result = $this->viewModel->build(
            [],
            ['header' => [], 'totals' => [], 'lines' => [], 'material_lines' => [], 'metadata' => []],
            ['document_profile' => ['offert_note' => 'Thanks', 'offert_valid_days' => '15']]
        );

        self::assertSame('Thanks', $result['terms_acceptance']['offert_note']);
        self::assertSame('15', $result['terms_acceptance']['offert_valid_days']);
        self::assertSame('', $result['terms_acceptance']['accepted_by']);
        self::assertSame('', $result['terms_acceptance']['accepted_at']);
        self::assertSame('', $result['terms_acceptance']['signature']);
    }

    public function testBuildNormalizesMissingInputsSafely(): void
    {
        $result = $this->viewModel->build(
            ['estimate_id' => 10],
            ['header' => [], 'totals' => [], 'lines' => [null], 'material_lines' => ['invalid'], 'metadata' => []],
            ['project' => ['name' => 'Project A'], 'document_profile' => ['offert_valid_days' => ['invalid']]]
        );

        self::assertSame('', $result['document']['document_number']);
        self::assertSame('', $result['document']['offert_valid_until']);
        self::assertSame('Project A', $result['project_object']['project_name']);
        self::assertSame([], $result['labour_lines']);
        self::assertSame([], $result['material_lines']);
        self::assertSame('', $result['recipient']['client_name']);
    }
}
