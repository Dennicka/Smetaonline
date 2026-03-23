<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

abstract class BaseRepository
{
    protected AuditLogger $auditLogger;

    public function __construct(?AuditLogger $auditLogger = null)
    {
        $this->auditLogger = $auditLogger ?? new AuditLogger();
    }

    abstract protected function table(): string;

    abstract protected function entityType(): string;

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internally constructed from $wpdb->prefix and plugin constant suffix.
        return $wpdb->get_results("SELECT * FROM {$this->table()} ORDER BY id DESC", ARRAY_A) ?: [];
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internally constructed from $wpdb->prefix and plugin constant suffix.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function archive(int $id): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'status' => 'archived',
                'archived_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated !== false && $updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'archive', []);
            return true;
        }

        return false;
    }
}
