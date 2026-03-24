<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\AvtalIssuePolicy;

final class AvtalIssuePolicyTest extends TestCase
{
    public function testCanIssueOnlyFromAcceptedOffert(): void
    {
        $policy = new AvtalIssuePolicy();

        self::assertTrue($policy->canIssueFromOffertStatus('accepted'));
        self::assertFalse($policy->canIssueFromOffertStatus('issued'));
        self::assertFalse($policy->canIssueFromOffertStatus('rejected'));
        self::assertFalse($policy->canIssueFromOffertStatus('archived'));
    }
}
