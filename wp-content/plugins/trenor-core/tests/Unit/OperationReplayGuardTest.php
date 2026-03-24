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

            public int $nextUpdateResult = 1;

            public function insert(string $table, array $data, array $format = []): int
            {
                $this->insertHistory[] = ['table' => $table, 'data' => $data, 'format' => $format];

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
}
