<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Trenor\Core\Domain\Service\ReverseChargePolicy;

final class ReverseChargePolicyTest extends TestCase
{
    public function testAllowsStandardModesWithoutBusinessIdentity(): void
    {
        (new ReverseChargePolicy())->assertEstimateEligibility(['tax_mode' => 'private_consumer', 'rot_requested' => 0], []);
        self::assertTrue(true);
    }

    public function testRejectsReverseChargeWithRot(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reverse charge cannot be combined with ROT.');

        (new ReverseChargePolicy())->assertEstimateEligibility(
            ['tax_mode' => 'business_reverse_charge', 'rot_requested' => 1],
            ['company_name' => 'AB', 'org_number' => '556677-8899', 'vat_number' => 'SE556677889901']
        );
    }

    public function testRejectsReverseChargeWithoutRequiredBusinessIdentity(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reverse charge requires company name, organisation number, and VAT number.');

        (new ReverseChargePolicy())->assertEstimateEligibility(
            ['tax_mode' => 'business_reverse_charge', 'rot_requested' => 0],
            ['company_name' => 'AB', 'org_number' => '', 'vat_number' => '']
        );
    }
}
