<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\AtaStatusTransitionPolicy;

final class AtaStatusTransitionPolicyTest extends TestCase
{
    public function testAllowsExpectedTransitions(): void
    {
        $policy = new AtaStatusTransitionPolicy();

        self::assertTrue($policy->canTransition('draft', 'issued'));
        self::assertTrue($policy->canTransition('issued', 'approved'));
        self::assertTrue($policy->canTransition('issued', 'rejected'));
        self::assertTrue($policy->canTransition('approved', 'archived'));
    }

    public function testDeniesInvalidTransitions(): void
    {
        $policy = new AtaStatusTransitionPolicy();

        self::assertFalse($policy->canTransition('draft', 'approved'));
        self::assertFalse($policy->canTransition('approved', 'issued'));
        self::assertFalse($policy->canTransition('archived', 'draft'));
    }
}
