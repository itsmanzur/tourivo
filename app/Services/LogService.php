<?php

declare(strict_types=1);

namespace Tourivo\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LogService
 *
 * Handles audit logs, internal admin notes, and activity timeline for bookings.
 *
 * @package Tourivo\Services
 */
class LogService
{
    /**
     * Log a booking event.
     *
     * @param int $bookingId
     * @param string $action (e.g. 'created', 'status_change', 'note', 'payment')
     * @param string $details
     * @param int $userId
     * @return int Log ID
     */
    public static function log(int $bookingId, string $action, string $details, int $userId = 0): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tourivo_logs';

        if ($userId === 0 && is_user_logged_in()) {
            $userId = get_current_user_id();
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            $table,
            [
                'booking_id' => $bookingId,
                'action'     => sanitize_key($action),
                'user_id'    => $userId,
                'details'    => sanitize_textarea_field($details),
                'created_at' => current_time('mysql', 1),
            ],
            ['%d', '%s', '%d', '%s', '%s']
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Get timeline history and notes for a specific booking.
     *
     * @param int $bookingId
     * @return array<int, array<string, mixed>>
     */
    public static function getTimeline(int $bookingId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tourivo_logs';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC, id DESC",
                $bookingId
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return is_array($results) ? $results : [];
    }
}
