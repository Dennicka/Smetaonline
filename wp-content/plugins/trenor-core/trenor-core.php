<?php

/**
 * Plugin Name: Trenor Core
 * Description: Core domain and infrastructure plugin for Smetaonline.
 * Version: 0.1.0
 * Requires PHP: 8.3
 * Requires at least: 6.9
 * Author: Smetaonline
 * Text Domain: trenor-core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/Bootstrap/Plugin.php';

Trenor\Core\Bootstrap\Plugin::boot(__FILE__);
