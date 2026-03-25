<?php

declare(strict_types=1);

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$GLOBALS['trn_test_options'] = [];

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

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim($value);
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return trim($url);
    }
}

if (! function_exists('esc_textarea')) {
    function esc_textarea(string $text): string
    {
        return $text;
    }
}

if (! function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['trn_test_options'][$option] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $option, mixed $value): bool
    {
        $GLOBALS['trn_test_options'][$option] = $value;

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        unset($GLOBALS['trn_test_options'][$option]);

        return true;
    }
}

if (! function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): string
    {
        return '2026-03-24 00:00:00';
    }
}

if (! function_exists('wp_upload_dir')) {
    /** @return array<string, string> */
    function wp_upload_dir(): array
    {
        $override = $GLOBALS['trn_test_wp_upload_dir'] ?? null;
        if (is_array($override) && isset($override['basedir']) && is_string($override['basedir'])) {
            return ['basedir' => $override['basedir']];
        }

        return ['basedir' => sys_get_temp_dir()];
    }
}

if (! function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool
    {
        return @mkdir($target, 0775, true) || is_dir($target);
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return 0;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        $capabilityMap = $GLOBALS['trn_test_current_user_caps'] ?? null;
        if (! is_array($capabilityMap)) {
            return false;
        }

        return (bool) ($capabilityMap[$capability] ?? false);
    }
}

if (! function_exists('trn_set_test_current_user_caps')) {
    /** @param array<string, bool> $caps */
    function trn_set_test_current_user_caps(array $caps): void
    {
        $GLOBALS['trn_test_current_user_caps'] = $caps;
    }
}

if (! function_exists('trn_reset_test_current_user_caps')) {
    function trn_reset_test_current_user_caps(): void
    {
        unset($GLOBALS['trn_test_current_user_caps']);
    }
}

if (! function_exists('trn_set_test_wpdb')) {
    function trn_set_test_wpdb(object $wpdb): void
    {
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Centralized test-only wpdb injection helper.
        $GLOBALS['wpdb'] = $wpdb;
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(string $value): string
    {
        return $value;
    }
}
