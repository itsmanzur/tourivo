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
     * Every WP-Cron / Action Scheduler hook Tourivo schedules (including legacy ones).
     *
     * @var string[]
     */
    public const SCHEDULED_HOOKS = [
        'tourivo_cleanup_expired_holds',
        'tourivo_expire_stale_bookings',
        'tourivo_daily_reminders',
        'tourivo_process_email_delivery',
        'tourivo_process_webhook_delivery',
        'tourivo_daily_privacy_retention',
        'tourivo_daily_cleanup',
        'tourivo_sync_ical_feeds',
    ];

    /**
     * Run the deactivation process.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        self::unscheduleAll();

        // Fire deactivation action hook
        do_action('tourivo/deactivated');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Remove every scheduled Tourivo event, whatever its arguments.
     *
     * wp_clear_scheduled_hook() only matches events scheduled with the same (empty)
     * args, so queued email/webhook deliveries would survive it. wp_unschedule_hook()
     * removes all of them; queued Action Scheduler actions are removed as well.
     *
     * @return void
     */
    public static function unscheduleAll(): void
    {
        foreach (self::SCHEDULED_HOOKS as $hook) {
            wp_unschedule_hook($hook);
            if (function_exists('as_unschedule_all_actions')) {
                as_unschedule_all_actions($hook);
            }
        }
    }
}
