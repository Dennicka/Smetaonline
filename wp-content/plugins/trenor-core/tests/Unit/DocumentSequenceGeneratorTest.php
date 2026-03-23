<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\DocumentSequenceGenerator;

final class DocumentSequenceGeneratorTest extends TestCase
{
    public function testGeneratesIncrementingSequences(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';

            /** @var array<string, int> */
            private array $values = [];
            private int $lastInsertId = 0;

            public function prepare(string $query, ...$args): string
            {
                $escaped = array_map(static fn ($value): string => is_numeric($value) ? (string) $value : "'" . addslashes((string) $value) . "'", $args);
                return vsprintf(str_replace(['%s', '%d'], ['%s', '%d'], $query), $escaped);
            }

            public function query(string $query)
            {
                if (str_starts_with($query, 'START TRANSACTION') || str_starts_with($query, 'COMMIT') || str_starts_with($query, 'ROLLBACK')) {
                    return 1;
                }

                if (str_contains($query, 'INSERT INTO')) {
                    preg_match("/VALUES \('([^']+)', '([0-9]{6})'/", $query, $matches);
                    $key = ($matches[1] ?? '') . '|' . ($matches[2] ?? '');
                    if (! isset($this->values[$key])) {
                        $this->values[$key] = 0;
                    }

                    return 1;
                }

                if (str_contains($query, 'UPDATE')) {
                    preg_match("/WHERE doc_type = '([^']+)' AND yyyymm = '([0-9]{6})'/", $query, $matches);
                    $key = ($matches[1] ?? '') . '|' . ($matches[2] ?? '');
                    $this->values[$key] = ($this->values[$key] ?? 0) + 1;
                    $this->lastInsertId = $this->values[$key];

                    return 1;
                }

                return false;
            }

            // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- Mock mirrors wpdb API naming.
            public function get_var(string $query)
            {
                if ($query === 'SELECT LAST_INSERT_ID()') {
                    return $this->lastInsertId;
                }

                return null;
            }
        };

        $generator = new DocumentSequenceGenerator($wpdb);

        $first = $generator->next('inv', new \DateTimeImmutable('2026-03-01'));
        $second = $generator->next('inv', new \DateTimeImmutable('2026-03-02'));

        self::assertSame('INV-202603-00001', $first);
        self::assertSame('INV-202603-00002', $second);
    }
}
