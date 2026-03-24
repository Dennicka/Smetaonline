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
        $where = [
            'token' => $token,
            'action_name' => $action,
            'scope_key' => $scope,
            'consumed_at' => null,
        ];
        if ($actorUserId !== null) {
            $where['actor_user_id'] = $actorUserId;
        }
        $whereFormat = ['%s', '%s', '%s', '%s'];
        if ($actorUserId !== null) {
            $whereFormat[] = '%d';
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'trn_operation_tokens',
            ['consumed_at' => $consumedAt],
            $where,
            ['%s'],
            $whereFormat
        );

        return is_int($updated) && $updated > 0;
    }
}
