<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\InvoicePaymentRepository;

final class InvoicePaymentRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new InvoicePaymentWpdbStub());
    }

    public function testRepositoryMapsTableAndEntity(): void
    {
        $repo = new InvoicePaymentRepository();
        $table = new ReflectionMethod($repo, 'table');
        $table->setAccessible(true);
        $entity = new ReflectionMethod($repo, 'entityType');
        $entity->setAccessible(true);

        self::assertSame('wp_trn_invoice_payments', $table->invoke($repo));
        self::assertSame('invoice_payment', $entity->invoke($repo));
    }

    public function testTotalPaidByInvoiceReturnsExpectedValue(): void
    {
        /** @var InvoicePaymentWpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->sumByInvoice[11] = 3400;

        $repo = new InvoicePaymentRepository();

        self::assertSame(3400, $repo->totalPaidByInvoice(11));
        self::assertSame(0, $repo->totalPaidByInvoice(99));
    }

    public function testCreatePathReturnsInsertId(): void
    {
        $repo = new InvoicePaymentRepository();

        /** @var InvoicePaymentWpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->insert_id = 77;

        $id = $repo->create([
            'invoice_id' => 5,
            'payment_date' => '2026-03-24 12:00:00',
            'amount_minor' => 2500,
            'currency' => 'SEK',
            'method' => 'manual',
            'reference' => 'BANK-1',
            'note' => 'part payment',
            'actor_user_id' => 9,
        ]);

        self::assertSame(77, $id);
        self::assertSame('wp_trn_invoice_payments', $wpdb->insertedTable);
    }
}

final class InvoicePaymentWpdbStub
{
    public string $prefix = 'wp_';

    public int $insert_id = 1;

    public string $insertedTable = '';

    /** @var array<int, int> */
    public array $sumByInvoice = [];

    public function prepare(string $query, ...$args): string
    {
        $escaped = array_map(
            static fn ($value): string => is_numeric($value) ? (string) $value : "'" . addslashes((string) $value) . "'",
            $args
        );

        return vsprintf($query, $escaped);
    }

    public function insert(string $table, array $data, array $format = []): int
    {
        $this->insertedTable = $table;

        return 1;
    }

    public function get_var(string $query)
    {
        if (preg_match('/invoice_id\s*=\s*(\d+)/', $query, $matches) === 1) {
            return $this->sumByInvoice[(int) $matches[1]] ?? 0;
        }

        return 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function get_results(string $query, string $outputType): array
    {
        return [];
    }
}
