<?php

declare(strict_types=1);

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($value, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $value): string
    {
        return strtolower(trim($value));
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim($value);
    }
}

if (! function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): string
    {
        return '2026-03-24 00:00:00';
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return 0;
    }
}

if (! function_exists('trn_set_test_wpdb')) {
    function trn_set_test_wpdb(object $wpdb): void
    {
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Centralized test-only wpdb injection helper.
        $GLOBALS['wpdb'] = $wpdb;
    }
}
