<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DatabaseServiceProvider
 *
 * Registers database services and manages automatic schema migrations on updates.
 *
 * @package Tourivo\Providers
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Schema::class, fn () => new Schema());
    }

    public function boot(): void
    {
        // Check if database upgrade is needed on admin init
        $this->addAction('admin_init', [$this, 'maybeRunMigrations']);

        // Run migrations when Tourivo plugin is updated
        $this->addAction('upgrader_process_complete', [$this, 'onUpgraderProcessComplete'], 10, 2);
    }

    /**
     * Run migrations automatically if database version is outdated.
     *
     * @return void
     */
    public function maybeRunMigrations(): void
    {
        $installedVersion = get_option(Schema::DB_VERSION_OPTION, '0.0.0');

        if (version_compare((string) $installedVersion, Schema::CURRENT_DB_VERSION, '<')) {
            Schema::migrate();
        }
    }

    /**
     * Run migrations on plugin upgrade completion.
     *
     * @param mixed $upgrader
     * @param array<string, mixed> $options
     * @return void
     */
    public function onUpgraderProcessComplete(mixed $upgrader, array $options): void
    {
        if (
            isset($options['action'], $options['type']) &&
            $options['action'] === 'update' &&
            $options['type'] === 'plugin'
        ) {
            $plugins = isset($options['plugins']) && is_array($options['plugins']) ? $options['plugins'] : [];
            if (in_array(plugin_basename(TOURIVO_PLUGIN_FILE), $plugins, true)) {
                Schema::migrate();
            }
        }
    }
}
