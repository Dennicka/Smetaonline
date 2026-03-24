<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class OperationReplayGuard
{
    public function issueToken(string $actionName, string $scopeKey, ?int $actorUserId = null): string
    {
        global $wpdb;

        $token = bin2hex(random_bytes(16));
        $wpdb->insert(
            $wpdb->prefix . 'trn_operation_tokens',
            [
                'token' => $token,
                'action_name' => sanitize_key($actionName),
                'scope_key' => sanitize_text_field($scopeKey),
                'actor_user_id' => $actorUserId,
                'consumed_at' => null,
                'created_at' => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $token;
    }

    public function consumeToken(string $token, string $actionName, string $scopeKey, ?int $actorUserId = null): bool
    {
        global $wpdb;

        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $action = sanitize_key($actionName);
        $scope = sanitize_text_field($scopeKey);
        $consumedAt = current_time('mysql', true);
        $table = $wpdb->prefix . 'trn_operation_tokens';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
        $query = $wpdb->prepare(
            "UPDATE {$table} SET consumed_at = %s WHERE token = %s AND action_name = %s AND scope_key = %s AND consumed_at IS NULL",
            $consumedAt,
            $token,
            $action,
            $scope
        );

        if ($actorUserId !== null) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
            $query = $wpdb->prepare(
                "UPDATE {$table} SET consumed_at = %s WHERE token = %s AND action_name = %s AND scope_key = %s AND actor_user_id = %d AND consumed_at IS NULL",
                $consumedAt,
                $token,
                $action,
                $scope,
                $actorUserId
            );
        }

        $updated = $wpdb->query($query);

        return is_int($updated) && $updated > 0;
    }
}
