<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

use Trenor\Core\Domain\Service\AtaStatusTransitionPolicy;
use Trenor\Core\Domain\Service\AtaVersionProvider;

final class AtaRepository extends BaseRepository implements AtaVersionProvider
{
    protected function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'trn_atas';
    }

    protected function entityType(): string
    {
        return 'ata';
    }

    /** @return array<int, array<string, mixed>> */
    public function byProject(int $projectId): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE project_id = %d ORDER BY id DESC", $projectId), ARRAY_A) ?: [];
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'project_id' => (int) ($data['project_id'] ?? 0),
            'estimate_id' => $this->nullableInt($data['estimate_id'] ?? null),
            'offert_id' => $this->nullableInt($data['offert_id'] ?? null),
            'invoice_id' => $this->nullableInt($data['invoice_id'] ?? null),
            'document_number' => sanitize_text_field((string) ($data['document_number'] ?? '')),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'status' => sanitize_key((string) ($data['status'] ?? 'draft')),
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'scope_change_text' => sanitize_textarea_field((string) ($data['scope_change_text'] ?? '')),
            'amount_ex_vat_minor' => (int) ($data['amount_ex_vat_minor'] ?? 0),
            'vat_rate_percent' => (float) ($data['vat_rate_percent'] ?? 25),
            'vat_minor' => (int) ($data['vat_minor'] ?? 0),
            'total_inc_vat_minor' => (int) ($data['total_inc_vat_minor'] ?? 0),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'invoice_link_status' => sanitize_key((string) ($data['invoice_link_status'] ?? 'not_invoiced')),
            'snapshot_json' => (string) ($data['snapshot_json'] ?? ''),
            'issued_at' => $this->nullableDate($data['issued_at'] ?? null),
            'approved_at' => $this->nullableDate($data['approved_at'] ?? null),
            'archived_at' => $this->nullableDate($data['archived_at'] ?? null),
            'actor_user_id' => $this->nullableInt($data['actor_user_id'] ?? null),
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
            'project_id' => (int) ($data['project_id'] ?? 0),
            'document_number' => (string) ($data['document_number'] ?? ''),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'status' => (string) ($data['status'] ?? 'draft'),
        ]);

        return $id;
    }

    public function updateDraft(int $id, array $data): bool
    {
        global $wpdb;

        $row = $this->find($id);
        if (! is_array($row) || sanitize_key((string) ($row['status'] ?? '')) !== 'draft') {
            return false;
        }

        $updated = $wpdb->update(
            $this->table(),
            [
                'title' => sanitize_text_field((string) ($data['title'] ?? '')),
                'scope_change_text' => sanitize_textarea_field((string) ($data['scope_change_text'] ?? '')),
                'amount_ex_vat_minor' => (int) ($data['amount_ex_vat_minor'] ?? 0),
                'vat_rate_percent' => (float) ($data['vat_rate_percent'] ?? 25),
                'vat_minor' => (int) ($data['vat_minor'] ?? 0),
                'total_inc_vat_minor' => (int) ($data['total_inc_vat_minor'] ?? 0),
                'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
                'snapshot_json' => (string) ($data['snapshot_json'] ?? ''),
                'updated_at' => current_time('mysql', true),
                'actor_user_id' => $this->nullableInt($data['actor_user_id'] ?? null),
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%f', '%d', '%d', '%s', '%s', '%s', '%d'],
            ['%d']
        );

        if ($updated !== false && $updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'update_draft', []);

            return true;
        }

        return false;
    }

    public function transitionStatus(int $id, string $toStatus, ?int $actorUserId = null): bool
    {
        global $wpdb;

        $row = $this->find($id);
        if (! is_array($row)) {
            return false;
        }

        $from = sanitize_key((string) ($row['status'] ?? ''));
        $to = sanitize_key($toStatus);
        if (! (new AtaStatusTransitionPolicy())->canTransition($from, $to)) {
            return false;
        }

        $payload = [
            'status' => $to,
            'updated_at' => current_time('mysql', true),
            'actor_user_id' => $actorUserId,
        ];
        $formats = ['%s', '%s', '%d'];

        if ($to === 'issued') {
            $payload['issued_at'] = current_time('mysql', true);
            $formats[] = '%s';
        }

        if ($to === 'approved') {
            $payload['approved_at'] = current_time('mysql', true);
            $formats[] = '%s';
        }

        if ($to === 'archived') {
            $payload['archived_at'] = current_time('mysql', true);
            $formats[] = '%s';
        }

        $updated = $wpdb->update($this->table(), $payload, ['id' => $id], $formats, ['%d']);
        if ($updated !== false && $updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'transition_status', ['status' => $to]);

            return true;
        }

        return false;
    }

    public function linkInvoice(int $id, int $invoiceId): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'invoice_id' => $invoiceId,
                'invoice_link_status' => 'linked',
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%s', '%s'],
            ['%d']
        );

        if ($updated !== false && $updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'link_invoice', ['invoice_id' => $invoiceId]);

            return true;
        }

        return false;
    }

    public function nextVersionNo(int $projectId): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        $max = $wpdb->get_var($wpdb->prepare("SELECT MAX(version_no) FROM {$this->table()} WHERE project_id = %d", $projectId));

        return ((int) $max) + 1;
    }

    private function nullableInt(mixed $value): ?int
    {
        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function nullableDate(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
