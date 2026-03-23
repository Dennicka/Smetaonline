<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class AuditLogger
{
    public function log(string $entityType, int $entityId, string $action, array $changes): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'trn_audit_log',
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'actor_user_id' => get_current_user_id() ?: null,
                'changes_json' => wp_json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => current_time('mysql', true),
            ],
            ['%s', '%d', '%s', '%d', '%s', '%s']
        );
    }
}
