<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\OffertStatusTransitionPolicy;

final class OffertStatusTransitionPolicyTest extends TestCase
{
    public function testAllowedTransitionsAreAccepted(): void
    {
        $policy = new OffertStatusTransitionPolicy();

        self::assertTrue($policy->canTransition('issued', 'accepted'));
        self::assertTrue($policy->canTransition('issued', 'rejected'));
        self::assertTrue($policy->canTransition('issued', 'archived'));
        self::assertTrue($policy->canTransition('accepted', 'archived'));
        self::assertTrue($policy->canTransition('rejected', 'archived'));
    }

    public function testDisallowedTransitionsAreDenied(): void
    {
        $policy = new OffertStatusTransitionPolicy();

        self::assertFalse($policy->canTransition('accepted', 'rejected'));
        self::assertFalse($policy->canTransition('rejected', 'accepted'));
        self::assertFalse($policy->canTransition('archived', 'accepted'));
        self::assertFalse($policy->canTransition('archived', 'rejected'));
        self::assertFalse($policy->canTransition('archived', 'issued'));
        self::assertFalse($policy->canTransition('unknown', 'accepted'));
    }
}
