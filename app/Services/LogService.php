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
     * Action types whose free-text details can embed personal data (recipient addresses, cancellation
     * reasons, staff notes) and must therefore be scrubbed when a booking is anonymized.
     */
    public const PII_ACTIONS = ['email_sent', 'email_failed', 'cancel_requested', 'internal_note'];

    /**
     * Replace the details of PII-bearing log entries of a booking with a neutral placeholder.
     *
     * The audit trail itself (that something happened, and when) is kept.
     *
     * @param int $bookingId
     * @return void
     */
    public static function redactPersonalData(int $bookingId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tourivo_logs';

        $placeholders = implode(',', array_fill(0, count(self::PII_ACTIONS), '%s'));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET details = %s WHERE booking_id = %d AND action IN ({$placeholders})",
            ...array_merge(['[redacted]', $bookingId], self::PII_ACTIONS)
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
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
