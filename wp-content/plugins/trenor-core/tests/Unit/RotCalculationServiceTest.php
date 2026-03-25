<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\RotCalculationService;

final class RotCalculationServiceTest extends TestCase
{
    public function testCalculatesRotFromEligibleLabourOnly(): void
    {
        $service = new RotCalculationService();
        $summary = $service->buildSummary(
            [
                'rot_requested' => 1,
                'housing_type' => 'smahus',
                'rot_is_new_build' => 0,
                'rot_property_reference' => 'FAST-123',
                'rot_buyers_json' => '[{"name":"A","personal_identity":"19800101-1234","share_percent":100}]',
            ],
            [
                ['work_item_id' => 10, 'labour_subtotal_minor' => 100000],
                ['work_item_id' => 11, 'labour_subtotal_minor' => 80000],
            ],
            250000,
            ['10' => true, '11' => false]
        );

        self::assertSame('preliminary_approved', $summary['rot_eligibility_status']);
        self::assertSame(100000, $summary['rot_eligible_labour_minor']);
        self::assertSame(30000, $summary['preliminary_rot_minor']);
        self::assertSame(220000, $summary['amount_after_preliminary_rot_minor']);
    }

    public function testDeniesUnsupportedHousingAndNewBuild(): void
    {
        $service = new RotCalculationService();

        $unsupported = $service->buildSummary(
            ['rot_requested' => 1, 'housing_type' => 'villa_unknown', 'rot_buyers_json' => '[]'],
            [['work_item_id' => 10, 'labour_subtotal_minor' => 100000]],
            100000,
            ['10' => true]
        );
        self::assertSame('denied', $unsupported['rot_eligibility_status']);
        self::assertSame('unsupported_housing_type', $unsupported['rot_ineligibility_reason']);
        self::assertSame(0, $unsupported['preliminary_rot_minor']);

        $newBuild = $service->buildSummary(
            [
                'rot_requested' => 1,
                'housing_type' => 'smahus',
                'rot_is_new_build' => 1,
                'rot_buyers_json' => '[{"personal_identity":"19800101-1234"}]',
            ],
            [['work_item_id' => 10, 'labour_subtotal_minor' => 100000]],
            100000,
            ['10' => true]
        );
        self::assertSame('denied', $newBuild['rot_eligibility_status']);
        self::assertSame('new_build_not_eligible', $newBuild['rot_ineligibility_reason']);
    }

    public function testCapsPerBuyerAllocation(): void
    {
        $service = new RotCalculationService();
        $summary = $service->buildSummary(
            [
                'rot_requested' => 1,
                'housing_type' => 'smahus',
                'rot_buyers_json' => '[{"personal_identity":"19800101-1234","share_percent":50},{"personal_identity":"19850101-4321","share_percent":50}]',
            ],
            [['work_item_id' => 10, 'labour_subtotal_minor' => 40000000]],
            50000000,
            ['10' => true]
        );

        self::assertSame(40000000, $summary['rot_eligible_labour_minor']);
        self::assertSame(10000000, $summary['preliminary_rot_minor']);
        self::assertSame(2, $summary['rot_buyer_count']);
        self::assertCount(2, $summary['rot_allocation']);
        self::assertSame(5000000, $summary['rot_allocation'][0]['allocated_rot_minor']);
        self::assertSame(5000000, $summary['rot_allocation'][1]['allocated_rot_minor']);
    }
}
