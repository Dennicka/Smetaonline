<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

use Trenor\Core\Domain\Service\AvtalStatusTransitionPolicy;
use Trenor\Core\Domain\Service\AvtalVersionProvider;

final class AvtalRepository extends BaseRepository implements AvtalVersionProvider
{
    protected function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'trn_avtals';
    }

    protected function entityType(): string
    {
        return 'avtal';
    }

    /** @return array<int, array<string, mixed>> */
    public function byOffert(int $offertId): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE offert_id = %d ORDER BY version_no DESC, id DESC", $offertId), ARRAY_A) ?: [];
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'offert_id' => (int) ($data['offert_id'] ?? 0),
            'estimate_id' => (int) ($data['estimate_id'] ?? 0),
            'project_id' => (int) ($data['project_id'] ?? 0),
            'client_id' => (int) ($data['client_id'] ?? 0),
            'document_number' => sanitize_text_field((string) ($data['document_number'] ?? '')),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'status' => sanitize_key((string) ($data['status'] ?? 'issued')),
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'vat_rate_percent' => (float) ($data['vat_rate_percent'] ?? 25),
            'total_inc_vat_minor' => (int) ($data['total_inc_vat_minor'] ?? 0),
            'snapshot_json' => (string) ($data['snapshot_json'] ?? ''),
            'issued_at' => (string) ($data['issued_at'] ?? $now),
            'actor_user_id' => (int) ($data['actor_user_id'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($ok === false) {
            return null;
        }

        $id = (int) $wpdb->insert_id;
        if ($id <= 0) {
            return null;
        }

        $this->auditLogger->log($this->entityType(), $id, 'create', [
            'offert_id' => (int) ($data['offert_id'] ?? 0),
            'estimate_id' => (int) ($data['estimate_id'] ?? 0),
            'document_number' => (string) ($data['document_number'] ?? ''),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'status' => (string) ($data['status'] ?? 'issued'),
        ]);

        return $id;
    }

    public function nextVersionNo(int $offertId): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        $max = $wpdb->get_var($wpdb->prepare("SELECT MAX(version_no) FROM {$this->table()} WHERE offert_id = %d", $offertId));

        return ((int) $max) + 1;
    }

    public function transitionStatus(int $id, string $nextStatus): bool
    {
        global $wpdb;

        $row = $this->find($id);
        if (! is_array($row)) {
            return false;
        }

        $currentStatus = sanitize_key((string) ($row['status'] ?? ''));
        $normalizedNext = sanitize_key($nextStatus);
        if (! (new AvtalStatusTransitionPolicy())->canTransition($currentStatus, $normalizedNext)) {
            return false;
        }

        $payload = [
            'status' => $normalizedNext,
            'updated_at' => current_time('mysql', true),
        ];
        $formats = ['%s', '%s'];
        if ($normalizedNext === 'archived') {
            $payload['archived_at'] = current_time('mysql', true);
            $formats[] = '%s';
        }

        $updated = $wpdb->update(
            $this->table(),
            $payload,
            ['id' => $id],
            $formats,
            ['%d']
        );

        if ($updated !== false && $updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'transition_status', ['status' => $normalizedNext]);

            return true;
        }

        return false;
    }
}
