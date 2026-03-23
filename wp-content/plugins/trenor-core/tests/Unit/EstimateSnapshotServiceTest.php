<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Database\EstimateSnapshotRepository;
use Trenor\Core\Domain\Service\EstimateSnapshotService;

final class EstimateSnapshotServiceTest extends TestCase
{
    public function testSnapshotSavesFrozenState(): void
    {
        $repo = new class extends EstimateSnapshotRepository {
            public array $payloads = [];

            public function create(array $data): ?int
            {
                $this->payloads[] = $data;
                return count($this->payloads);
            }
        };

        $service = new EstimateSnapshotService($repo);

        $estimate = ['id' => 5, 'title' => 'Estimate 1'];
        $lines = [['id' => 10, 'calculated_hours' => 2.0]];
        $materials = [['id' => 11, 'subtotal_minor' => 1500]];
        $totals = ['total_inc_vat_minor' => 2000];

        $snapshotId = $service->captureRecalculationSnapshot($estimate, $lines, $materials, $totals, 7);

        $estimate['title'] = 'Changed';
        $lines[0]['calculated_hours'] = 9;

        self::assertSame(1, $snapshotId);
        self::assertCount(1, $repo->payloads);
        $saved = json_decode((string) $repo->payloads[0]['snapshot_json'], true);
        self::assertSame('Estimate 1', $saved['header']['title']);
        self::assertSame(2.0, $saved['lines'][0]['calculated_hours']);
    }
}
