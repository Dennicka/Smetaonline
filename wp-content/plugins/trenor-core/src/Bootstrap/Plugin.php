<?php

declare(strict_types=1);

namespace Trenor\Core\Bootstrap;

use Trenor\Core\Admin\Menu;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\Migrator;
use Trenor\Core\Database\RepositoryFactory;

final class Plugin
{
    public const VERSION = '0.1.0';
    public const VERSION_OPTION = 'trn_core_version';

    private static string $pluginFile;

    public static function boot(string $pluginFile): void
    {
        self::$pluginFile = $pluginFile;

        $autoload = dirname($pluginFile) . '/vendor/autoload.php';
        if (is_readable($autoload)) {
            require_once $autoload;
        } else {
            require_once dirname($pluginFile) . '/autoload.php';
        }

        register_activation_hook($pluginFile, [self::class, 'activate']);
        register_deactivation_hook($pluginFile, [self::class, 'deactivate']);

        add_action('plugins_loaded', [self::class, 'onPluginsLoaded']);
    }

    public static function activate(): void
    {
        (new Migrator())->migrate();
        RoleRegistrar::register();
        update_option(self::VERSION_OPTION, self::VERSION);
    }

    public static function deactivate(): void
    {
        RoleRegistrar::unregisterCapsFromAdministrator();
    }

    public static function onPluginsLoaded(): void
    {
        RoleRegistrar::register();

        $currentVersion = (string) get_option(self::VERSION_OPTION, '0.0.0');
        if (version_compare($currentVersion, self::VERSION, '<')) {
            (new Migrator())->migrate();
            update_option(self::VERSION_OPTION, self::VERSION);
        }

        if (is_admin()) {
            $factory = new RepositoryFactory();
            $controller = new PageController($factory);
            (new Menu($controller))->register();
        }
    }
}
