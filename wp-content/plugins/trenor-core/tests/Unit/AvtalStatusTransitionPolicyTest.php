<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\AvtalStatusTransitionPolicy;

final class AvtalStatusTransitionPolicyTest extends TestCase
{
    public function testAllowsArchiveFromIssued(): void
    {
        $policy = new AvtalStatusTransitionPolicy();

        self::assertTrue($policy->canTransition('issued', 'archived'));
    }

    public function testDeniesInvalidTransitions(): void
    {
        $policy = new AvtalStatusTransitionPolicy();

        self::assertFalse($policy->canTransition('issued', 'issued'));
        self::assertFalse($policy->canTransition('archived', 'issued'));
        self::assertFalse($policy->canTransition('archived', 'archived'));
    }
}
