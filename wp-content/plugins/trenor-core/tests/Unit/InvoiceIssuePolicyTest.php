<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\InvoiceIssuePolicy;

final class InvoiceIssuePolicyTest extends TestCase
{
    public function testAcceptedOffertAllowsInvoiceIssue(): void
    {
        $policy = new InvoiceIssuePolicy();

        self::assertTrue($policy->canIssueFromOffertStatus('accepted'));
    }

    public function testNonAcceptedOffertStatusesDenyInvoiceIssue(): void
    {
        $policy = new InvoiceIssuePolicy();

        self::assertFalse($policy->canIssueFromOffertStatus('issued'));
        self::assertFalse($policy->canIssueFromOffertStatus('rejected'));
        self::assertFalse($policy->canIssueFromOffertStatus('archived'));
    }
}
