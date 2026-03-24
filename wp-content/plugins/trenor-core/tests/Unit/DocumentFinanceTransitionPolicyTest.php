<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\DocumentFinanceTransitionPolicy;

final class DocumentFinanceTransitionPolicyTest extends TestCase
{
    public function testInvoiceTransitionsAllowOnlySupportedPaths(): void
    {
        $policy = new DocumentFinanceTransitionPolicy();

        self::assertTrue($policy->canTransitionInvoiceStatus('issued', 'partially_paid'));
        self::assertTrue($policy->canTransitionInvoiceStatus('issued', 'paid'));
        self::assertTrue($policy->canTransitionInvoiceStatus('issued', 'archived'));
        self::assertTrue($policy->canTransitionInvoiceStatus('partially_paid', 'paid'));
        self::assertTrue($policy->canTransitionInvoiceStatus('partially_paid', 'archived'));
        self::assertTrue($policy->canTransitionInvoiceStatus('paid', 'archived'));

        self::assertFalse($policy->canTransitionInvoiceStatus('archived', 'issued'));
        self::assertFalse($policy->canTransitionInvoiceStatus('paid', 'partially_paid'));
        self::assertFalse($policy->canTransitionInvoiceStatus('issued', 'issued'));
    }

    public function testPaymentAndCreditNoteIssuingStatusesAreConstrained(): void
    {
        $policy = new DocumentFinanceTransitionPolicy();

        self::assertTrue($policy->canRecordPaymentForInvoiceStatus('issued'));
        self::assertTrue($policy->canRecordPaymentForInvoiceStatus('partially_paid'));
        self::assertFalse($policy->canRecordPaymentForInvoiceStatus('paid'));
        self::assertFalse($policy->canRecordPaymentForInvoiceStatus('archived'));

        self::assertTrue($policy->canIssueCreditNoteFromInvoiceStatus('issued'));
        self::assertTrue($policy->canIssueCreditNoteFromInvoiceStatus('partially_paid'));
        self::assertTrue($policy->canIssueCreditNoteFromInvoiceStatus('paid'));
        self::assertFalse($policy->canIssueCreditNoteFromInvoiceStatus('archived'));

        self::assertTrue($policy->canIssueReminderFromInvoiceStatus('issued'));
        self::assertTrue($policy->canIssueReminderFromInvoiceStatus('partially_paid'));
        self::assertFalse($policy->canIssueReminderFromInvoiceStatus('paid'));
        self::assertFalse($policy->canIssueReminderFromInvoiceStatus('archived'));
    }

    public function testCreditNoteTransitionsAllowArchiveOnlyOnce(): void
    {
        $policy = new DocumentFinanceTransitionPolicy();

        self::assertTrue($policy->canTransitionCreditNoteStatus('issued', 'archived'));
        self::assertFalse($policy->canTransitionCreditNoteStatus('archived', 'archived'));
        self::assertFalse($policy->canTransitionCreditNoteStatus('archived', 'issued'));
    }

    public function testReminderTransitionsAllowArchiveOnlyOnce(): void
    {
        $policy = new DocumentFinanceTransitionPolicy();

        self::assertTrue($policy->canTransitionReminderStatus('issued', 'archived'));
        self::assertFalse($policy->canTransitionReminderStatus('archived', 'archived'));
        self::assertFalse($policy->canTransitionReminderStatus('archived', 'issued'));
    }
}
