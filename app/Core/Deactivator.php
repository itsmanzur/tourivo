<?php

declare(strict_types=1);

namespace Tourivo\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Deactivator
 *
 * Runs upon plugin deactivation: clears scheduled cron jobs, flushes rewrite rules.
 *
 * @package Tourivo\Core
 */
class Deactivator
{
    /**
     * Run the deactivation process.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        // Clear any scheduled cron hooks
        wp_clear_scheduled_hook('tourivo_cleanup_expired_holds');
        wp_clear_scheduled_hook('tourivo_daily_reminders');
        wp_clear_scheduled_hook('tourivo_process_email_delivery');
        wp_clear_scheduled_hook('tourivo_daily_privacy_retention');

        // Fire deactivation action hook
        do_action('tourivo/deactivated');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
