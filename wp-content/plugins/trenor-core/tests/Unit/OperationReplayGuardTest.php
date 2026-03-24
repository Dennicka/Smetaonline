<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\OperationReplayGuard;

final class OperationReplayGuardTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new class () {
            public string $prefix = 'wp_';

            /** @var array<int, array<string, mixed>> */
            public array $insertHistory = [];

            /** @var array<int, array<string, mixed>> */
            public array $updateHistory = [];

            /** @var array<int, string> */
            public array $queryHistory = [];

            /** @var array<int, array<string, mixed>> */
            public array $receipts = [];

            public int $nextUpdateResult = 1;

            public int $insert_id = 1;

            public bool $failReceiptInsert = false;

            public function insert(string $table, array $data, array $format = []): int|false
            {
                $this->insertHistory[] = ['table' => $table, 'data' => $data, 'format' => $format];
                if ($table === 'wp_trn_operation_receipts') {
                    if ($this->failReceiptInsert) {
                        return false;
                    }

                    $data['id'] = $this->insert_id++;
                    $this->receipts[] = $data;
                }

                return 1;
            }

            public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int
            {
                $this->updateHistory[] = [
                    'table' => $table,
                    'data' => $data,
                    'where' => $where,
                    'format' => $format,
                    'where_format' => $whereFormat,
                ];

                return $this->nextUpdateResult;
            }

            public function prepare(string $query, ...$args): string
            {
                $escaped = array_map(
                    static fn ($value): string => is_numeric($value) ? (string) $value : "'" . addslashes((string) $value) . "'",
                    $args
                );

                return vsprintf($query, $escaped);
            }

            public function get_row(string $query, string $output = ARRAY_A): ?array
            {
                if ($this->receipts === []) {
                    return null;
                }

                return $this->receipts[0];
            }

            public function query(string $query): int
            {
                $this->queryHistory[] = $query;

                return 1;
            }
        });
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
        self::assertCount(1, $wpdb->updateHistory);
        self::assertSame('wp_trn_operation_tokens', $wpdb->updateHistory[0]['table']);
        self::assertSame('issue_invoice', $wpdb->updateHistory[0]['where']['action_name']);
        self::assertSame('offert:12', $wpdb->updateHistory[0]['where']['scope_key']);
        self::assertNull($wpdb->updateHistory[0]['where']['consumed_at']);

        $wpdb->nextUpdateResult = 0;
        self::assertFalse($guard->consumeToken($token, 'issue_invoice', 'offert:12', 55));
    }

    public function testDifferentActionsAndScopesDoNotConflict(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $guard = new OperationReplayGuard();

        $token = $guard->issueToken('record_payment', 'invoice:77', 5);

        self::assertTrue($guard->consumeToken($token, 'record_payment', 'invoice:77', 5));
        self::assertSame('record_payment', $wpdb->updateHistory[0]['where']['action_name']);
        self::assertSame('invoice:77', $wpdb->updateHistory[0]['where']['scope_key']);
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

        self::assertSame('wp_trn_operation_receipts', $wpdb->updateHistory[0]['table']);
        self::assertSame('completed', $wpdb->updateHistory[0]['data']['status']);
        self::assertSame(44, $wpdb->updateHistory[0]['data']['result_entity_id']);
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
