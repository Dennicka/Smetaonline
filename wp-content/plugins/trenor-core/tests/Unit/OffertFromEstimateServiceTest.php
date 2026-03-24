<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Trenor\Core\Database\OffertRepository;
use Trenor\Core\Domain\Service\DocumentSequenceGenerator;
use Trenor\Core\Domain\Service\OffertFromEstimateService;

final class OffertFromEstimateServiceTest extends TestCase
{
    public function testBuildPayloadCreatesImmutableSnapshot(): void
    {
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';
            private int $sequence = 0;

            public function prepare(string $query, ...$args): string
            {
                $escaped = array_map(static fn ($value): string => is_numeric($value) ? (string) $value : "'" . addslashes((string) $value) . "'", $args);
                return vsprintf($query, $escaped);
            }

            public function get_var(string $query)
            {
                if ($query === 'SELECT LAST_INSERT_ID()') {
                    return $this->sequence;
                }

                if (str_contains($query, 'MAX(version_no)')) {
                    return '1';
                }

                return null;
            }

            public function query(string $query)
            {
                if (str_starts_with($query, 'START TRANSACTION') || str_starts_with($query, 'COMMIT') || str_starts_with($query, 'ROLLBACK')) {
                    return 1;
                }

                if (str_contains($query, 'INSERT INTO')) {
                    return 1;
                }

                if (str_contains($query, 'UPDATE')) {
                    $this->sequence++;
                    return 1;
                }

                return false;
            }
        };

        $service = new OffertFromEstimateService(new OffertRepository(), new DocumentSequenceGenerator($GLOBALS['wpdb']));

        $header = ['id' => 44, 'title' => 'Kitchen refresh', 'currency' => 'SEK', 'vat_rate_percent' => 25.0];
        $lines = [['id' => 1, 'quantity' => 1.5, 'labour_subtotal_minor' => 90000]];
        $materials = [['id' => 7, 'quantity' => 2.25, 'subtotal_minor' => 30000]];
        $totals = ['labour_total_minor' => 90000, 'materials_total_minor' => 30000, 'subtotal_ex_vat_minor' => 120000, 'vat_minor' => 30000, 'total_inc_vat_minor' => 150000];

        $payload = $service->buildPayload($header, $lines, $materials, $totals, new DateTimeImmutable('2026-03-10 09:30:00'));

        $header['title'] = 'Changed later';
        $lines[0]['quantity'] = 99.0;
        $materials[0]['quantity'] = 99.0;
        $totals['total_inc_vat_minor'] = 0;

        self::assertStringStartsWith('OFF-', (string) $payload['document_number']);
        self::assertSame(2, $payload['version_no']);

        $snapshot = json_decode((string) $payload['snapshot_json'], true);
        self::assertIsArray($snapshot);
        self::assertSame('Kitchen refresh', $snapshot['header']['title']);
        self::assertSame(1.5, $snapshot['lines'][0]['quantity']);
        self::assertSame(2.25, $snapshot['material_lines'][0]['quantity']);
        self::assertSame(150000, $snapshot['totals']['total_inc_vat_minor']);
        self::assertSame(2, $snapshot['metadata']['offert_version_no']);
        self::assertSame((string) $payload['document_number'], $snapshot['metadata']['document_number']);
    }
}
