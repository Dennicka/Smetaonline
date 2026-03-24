<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\OperationReplayGuard;
use Trenor\Core\Tests\Support\WpdbStub;

final class OperationReplayGuardTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testIssuedTokenCanBeConsumedOnlyOnceAndActionScopeSpecific(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $guard = new OperationReplayGuard();

        $token = $guard->issueToken('issue_invoice', 'offert:12', 55);

        self::assertNotSame('', $token);
        self::assertCount(1, $wpdb->insertHistory);
        self::assertSame('wp_trn_operation_tokens', $wpdb->insertHistory[0]['table']);
        self::assertSame('issue_invoice', $wpdb->insertHistory[0]['data']['action_name']);
        self::assertSame('offert:12', $wpdb->insertHistory[0]['data']['scope_key']);

        self::assertTrue($guard->consumeToken($token, 'issue_invoice', 'offert:12', 55));
        self::assertCount(1, $wpdb->updatedRows);
        self::assertSame('wp_trn_operation_tokens', $wpdb->updatedRows[0]['table']);
        self::assertSame('issue_invoice', $wpdb->updatedRows[0]['where']['action_name']);
        self::assertSame('offert:12', $wpdb->updatedRows[0]['where']['scope_key']);
        self::assertNull($wpdb->updatedRows[0]['where']['consumed_at']);

        $wpdb->updateResult = 0;
        self::assertFalse($guard->consumeToken($token, 'issue_invoice', 'offert:12', 55));
    }

    public function testDifferentActionsAndScopesDoNotConflict(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $guard = new OperationReplayGuard();

        $token = $guard->issueToken('record_payment', 'invoice:77', 5);

        self::assertTrue($guard->consumeToken($token, 'record_payment', 'invoice:77', 5));
        self::assertSame('record_payment', $wpdb->updatedRows[0]['where']['action_name']);
        self::assertSame('invoice:77', $wpdb->updatedRows[0]['where']['scope_key']);
    }

    public function testBusinessEffectIsStartedAndCanBeCompleted(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $guard = new OperationReplayGuard();

        $result = $guard->beginBusinessEffect('issue_offert', 'estimate:12', 'hash-a');

        self::assertSame('started', $result['status']);
        self::assertGreaterThan(0, (int) ($result['receipt_id'] ?? 0));
        self::assertSame('wp_trn_operation_receipts', $wpdb->insertHistory[0]['table']);

        $guard->completeBusinessEffect((int) $result['receipt_id'], 'offert', 44);

        self::assertSame('wp_trn_operation_receipts', $wpdb->updatedRows[0]['table']);
        self::assertSame('completed', $wpdb->updatedRows[0]['data']['status']);
        self::assertSame(44, $wpdb->updatedRows[0]['data']['result_entity_id']);
    }

    public function testDuplicateCompletedBusinessEffectReturnsExistingEntity(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->failReceiptInsert = true;
        $wpdb->receipts = [[
            'id' => 11,
            'status' => 'completed',
            'result_entity_type' => 'invoice',
            'result_entity_id' => 33,
        ]];

        $guard = new OperationReplayGuard();
        $result = $guard->beginBusinessEffect('issue_invoice', 'offert:5', 'hash-b');

        self::assertSame('duplicate_completed', $result['status']);
        self::assertSame('invoice', $result['entity_type']);
        self::assertSame(33, $result['entity_id']);
    }

    public function testDuplicateInProgressBusinessEffectIsReported(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->failReceiptInsert = true;
        $wpdb->receipts = [[
            'id' => 10,
            'status' => 'processing',
            'result_entity_type' => null,
            'result_entity_id' => null,
        ]];

        $guard = new OperationReplayGuard();
        $result = $guard->beginBusinessEffect('issue_credit_note', 'invoice:9', 'hash-c');

        self::assertSame('duplicate_in_progress', $result['status']);
    }

    public function testAbandonProcessingBusinessEffectDeletesReceipt(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $guard = new OperationReplayGuard();

        $guard->abandonBusinessEffect(99);

        self::assertCount(1, $wpdb->queryHistory);
        self::assertStringContainsString('DELETE FROM wp_trn_operation_receipts', $wpdb->queryHistory[0]);
    }
}
