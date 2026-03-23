<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use Trenor\Core\Database\EstimateSnapshotRepository;
use Trenor\Core\Support\Snapshot;

final class EstimateSnapshotService
{
    private EstimateSnapshotRepository $snapshotRepository;

    public function __construct(EstimateSnapshotRepository $snapshotRepository)
    {
        $this->snapshotRepository = $snapshotRepository;
    }

    /** @param array<string, mixed> $estimate @param array<int, array<string, mixed>> $lines @param array<int, array<string, mixed>> $materialLines @param array<string, mixed> $totals */
    public function captureRecalculationSnapshot(array $estimate, array $lines, array $materialLines, array $totals, ?int $actorUserId = null): ?int
    {
        $payload = Snapshot::freeze([
            'header' => $estimate,
            'lines' => $lines,
            'material_lines' => $materialLines,
            'totals' => $totals,
        ]);

        return $this->snapshotRepository->create([
            'estimate_id' => (int) ($estimate['id'] ?? 0),
            'snapshot_type' => 'recalculation',
            'snapshot_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'actor_user_id' => $actorUserId,
        ]);
    }
}
