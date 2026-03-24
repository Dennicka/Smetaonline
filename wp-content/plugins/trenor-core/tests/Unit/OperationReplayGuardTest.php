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

            /** @var array<int, string> */
            public array $queries = [];

            public int $nextQueryResult = 1;

            public function insert(string $table, array $data, array $format = []): int
            {
                $this->insertHistory[] = ['table' => $table, 'data' => $data, 'format' => $format];

                return 1;
            }

            public function prepare(string $query, ...$args): string
            {
                $escaped = array_map(
                    static fn ($value): string => is_numeric($value) ? (string) $value : "'" . addslashes((string) $value) . "'",
                    $args
                );

                return vsprintf($query, $escaped);
            }

            public function query(string $query): int
            {
                $this->queries[] = $query;

                return $this->nextQueryResult;
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
        self::assertCount(1, $wpdb->queries);
        self::assertStringContainsString("consumed_at IS NULL", $wpdb->queries[0]);

        $wpdb->nextQueryResult = 0;
        self::assertFalse($guard->consumeToken($token, 'issue_invoice', 'offert:12', 55));
    }

    public function testDifferentActionsAndScopesDoNotConflict(): void
    {
        /** @var object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $guard = new OperationReplayGuard();

        $token = $guard->issueToken('record_payment', 'invoice:77', 5);

        self::assertTrue($guard->consumeToken($token, 'record_payment', 'invoice:77', 5));
        self::assertStringContainsString("action_name = 'record_payment'", $wpdb->queries[0]);
        self::assertStringContainsString("scope_key = 'invoice:77'", $wpdb->queries[0]);
    }
}
