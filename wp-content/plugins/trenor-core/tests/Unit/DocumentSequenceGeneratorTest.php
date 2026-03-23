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
            private array $rows = [];

            public function prepare(string $query, ...$args): string
            {
                return vsprintf(str_replace(['%s', '%d'], ['%s', '%d'], $query), $args);
            }

            public function get_row(string $query, string $output): ?array
            {
                preg_match("/doc_type = ([A-Za-z]+) AND yyyymm = (\d+)/", $query, $m);
                $key = ($m[1] ?? '') . '|' . ($m[2] ?? '');
                return $this->rows[$key] ?? null;
            }

            public function update(string $table, array $data, array $where): void
            {
                foreach ($this->rows as $key => $row) {
                    if ((int) $row['id'] === (int) $where['id']) {
                        $this->rows[$key] = array_merge($row, $data);
                    }
                }
            }

            public function insert(string $table, array $data): void
            {
                $key = $data['doc_type'] . '|' . $data['yyyymm'];
                $this->rows[$key] = ['id' => count($this->rows) + 1, 'current_value' => $data['current_value']];
            }
        };

        $generator = new DocumentSequenceGenerator($wpdb);

        $first = $generator->next('inv', new \DateTimeImmutable('2026-03-01'));
        $second = $generator->next('inv', new \DateTimeImmutable('2026-03-02'));

        self::assertSame('INV-202603-00001', $first);
        self::assertSame('INV-202603-00002', $second);
    }
}
