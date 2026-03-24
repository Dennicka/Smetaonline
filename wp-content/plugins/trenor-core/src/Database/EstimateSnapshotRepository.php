<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

class EstimateSnapshotRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_estimate_snapshots';
    }

    protected function entityType(): string
    {
        return 'estimate_snapshot';
    }

    /** @return array<int, array<string, mixed>> */
    public function byEstimate(int $estimateId): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name internal.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE estimate_id = %d ORDER BY id DESC", $estimateId), ARRAY_A) ?: [];
    }

    public function create(array $data): ?int
    {
        global $wpdb;
        $ok = $wpdb->insert($this->table(), [
            'estimate_id' => (int) ($data['estimate_id'] ?? 0),
            'snapshot_type' => sanitize_key((string) ($data['snapshot_type'] ?? 'recalculation')),
            'snapshot_json' => (string) ($data['snapshot_json'] ?? '{}'),
            'created_at' => current_time('mysql', true),
            'actor_user_id' => isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null,
        ], ['%d', '%s', '%s', '%s', '%d']);
        if ($ok === false) {
            return null;
        }
        $id = (int) $wpdb->insert_id;
        if ($id > 0) {
            $this->auditLogger->log('estimate', (int) ($data['estimate_id'] ?? 0), 'snapshot_create', ['snapshot_id' => $id]);
            return $id;
        }
        return null;
    }

    public function updateEntity(int $id, array $data): bool
    {
        return false;
    }

    public function archive(int $id): bool
    {
        return false;
    }
}
