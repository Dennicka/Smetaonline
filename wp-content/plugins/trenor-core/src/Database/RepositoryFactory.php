<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class RepositoryFactory
{
    public function clients(): ClientRepository
    {
        return new ClientRepository();
    }

    public function properties(): PropertyRepository
    {
        return new PropertyRepository();
    }

    public function projects(): ProjectRepository
    {
        return new ProjectRepository();
    }

    public function rooms(): RoomRepository
    {
        return new RoomRepository();
    }

    /** @return array<int, array<string, mixed>> */
    public function auditLogs(int $limit = 100): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'trn_audit_log';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internally constructed from $wpdb->prefix and plugin constant suffix.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A) ?: [];
    }
}
