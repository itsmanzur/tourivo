<?php

declare(strict_types=1);

namespace Tourivo\Core;

use Tourivo\Config\Config;
use Tourivo\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Installer
 *
 * Runs upon plugin activation: migrations, default settings, capability provisioning.
 *
 * @package Tourivo\Core
 */
class Installer
{
    /**
     * Run the installation / activation process.
     *
     * @return void
     */
    public static function activate(): void
    {
        // 1. Run custom table migrations
        Schema::migrate();

        // 2. Set default options if not existing
        if (!get_option('tourivo_settings')) {
            update_option('tourivo_settings', Config::getDefaults());
        }

        // 3. Set plugin installed timestamp & version
        if (!get_option('tourivo_installed_at')) {
            update_option('tourivo_installed_at', current_time('mysql'));
        }
        update_option('tourivo_version', TOURIVO_VERSION);

        // 4. Add custom user capabilities to administrator
        self::addCapabilities();

        // 5. Fire activation action hook for add-ons (like Tourivo Pro)
        do_action('tourivo/activated');

        // Flag rewrite rules to be flushed safely on next init hook
        update_option('tourivo_flush_rewrite', 1);
    }

    /**
     * Add custom Tourivo capabilities to Administrator role.
     *
     * @return void
     */
    protected static function addCapabilities(): void
    {
        $adminRole = get_role('administrator');
        if (!$adminRole) {
            return;
        }

        $capabilities = [
            'manage_tourivo',
            'manage_tourivo_bookings',
            'manage_tourivo_tours',
            'manage_tourivo_hotels',
            'manage_tourivo_settings',
        ];

        foreach ($capabilities as $cap) {
            $adminRole->add_cap($cap);
        }
    }
}
