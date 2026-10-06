<?php

declare(strict_types=1);

namespace Tourivo\Core;

use Tourivo\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Uninstaller
 *
 * Runs when the plugin is deleted via WordPress admin, if erase on uninstall is enabled.
 *
 * @package Tourivo\Core
 */
class Uninstaller
{
    /**
     * Run the uninstallation process.
     *
     * @return void
     */
    public static function uninstall(): void
    {
        global $wpdb;

        // 1. Remove custom capabilities
        $adminRole = get_role('administrator');
        if ($adminRole) {
            $capabilities = [
                'manage_tourivo',
                'manage_tourivo_bookings',
                'manage_tourivo_tours',
                'manage_tourivo_hotels',
                'manage_tourivo_settings',
                'manage_tourivo_checkin',
            ];
            foreach ($capabilities as $cap) {
                $adminRole->remove_cap($cap);
            }
        }

        // 2. Clear scheduled hooks
        wp_clear_scheduled_hook('tourivo_daily_cleanup');
        wp_clear_scheduled_hook('tourivo_sync_ical_feeds');
        wp_clear_scheduled_hook('tourivo_cleanup_expired_holds');
        wp_clear_scheduled_hook('tourivo_expire_stale_bookings');
        wp_clear_scheduled_hook('tourivo_daily_privacy_retention');

        $settings = get_option('tourivo_settings', []);
        $eraseDataOnDelete = !empty($settings['erase_data_on_uninstall']);

        // Only purge tables and options if user explicitly requested full cleanup
        if ($eraseDataOnDelete) {
            Schema::dropTables();
            delete_option('tourivo_settings');
            delete_option('tourivo_installed_at');
            delete_option('tourivo_version');
            delete_option('tourivo_db_version');
            delete_option('tourivo_flush_rewrite');
            delete_option('tourivo_notification_email');

            // Purge rate-limiting, hold sessions, and cached transients
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_trv_%' 
                    OR option_name LIKE '_transient_timeout_trv_%'"
            );

            // Purge checkout hold records
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like('tourivo_hold_') . '%'
                )
            );

            // Purge Tourivo user meta
            $wpdb->query(
                "DELETE FROM {$wpdb->usermeta} 
                 WHERE meta_key IN ('_tourivo_wishlist', 'tourivo_dismiss_welcome_notice')"
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        do_action('tourivo/uninstalled');
    }
}
