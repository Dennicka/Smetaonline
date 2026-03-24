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

    /**
     * @return array{status:'started'|'duplicate_completed'|'duplicate_in_progress',receipt_id?:int,entity_type?:string,entity_id?:int}
     */
    public function beginBusinessEffect(string $actionName, string $scopeKey, string $effectHash): array
    {
        global $wpdb;

        $action = sanitize_key($actionName);
        $scope = sanitize_text_field($scopeKey);
        $hash = sanitize_text_field($effectHash);
        $table = $wpdb->prefix . 'trn_operation_receipts';
        $now = current_time('mysql', true);

        $inserted = $wpdb->insert(
            $table,
            [
                'action_name' => $action,
                'scope_key' => $scope,
                'effect_hash' => $hash,
                'status' => 'processing',
                'result_entity_type' => null,
                'result_entity_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($inserted !== false) {
            $receiptId = (int) ($wpdb->insert_id ?? 0);

            return [
                'status' => 'started',
                'receipt_id' => $receiptId,
            ];
        }

        $existing = $this->findBusinessEffectReceipt($action, $scope, $hash);
        if ($existing === null) {
            return ['status' => 'duplicate_in_progress'];
        }

        if ((string) ($existing['status'] ?? '') === 'completed') {
            return [
                'status' => 'duplicate_completed',
                'entity_type' => (string) ($existing['result_entity_type'] ?? ''),
                'entity_id' => (int) ($existing['result_entity_id'] ?? 0),
            ];
        }

        return ['status' => 'duplicate_in_progress'];
    }

    public function completeBusinessEffect(int $receiptId, string $entityType, int $entityId): void
    {
        global $wpdb;

        if ($receiptId <= 0 || $entityId <= 0) {
            return;
        }

        $wpdb->update(
            $wpdb->prefix . 'trn_operation_receipts',
            [
                'status' => 'completed',
                'result_entity_type' => sanitize_key($entityType),
                'result_entity_id' => $entityId,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $receiptId],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );
    }

    public function abandonBusinessEffect(int $receiptId): void
    {
        global $wpdb;

        if ($receiptId <= 0) {
            return;
        }

        $table = $wpdb->prefix . 'trn_operation_receipts';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id = %d AND status = %s", $receiptId, 'processing'));
    }

    private function findBusinessEffectReceipt(string $actionName, string $scopeKey, string $effectHash): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'trn_operation_receipts';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE action_name = %s AND scope_key = %s AND effect_hash = %s LIMIT 1", $actionName, $scopeKey, $effectHash), ARRAY_A);

        return is_array($row) ? $row : null;
    }
}
