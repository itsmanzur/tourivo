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
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Action types whose free-text details can embed personal data (recipient addresses, cancellation
     * reasons, staff notes) and must therefore be scrubbed when a booking is anonymized.
     */
    public const PII_ACTIONS = ['email_sent', 'email_failed', 'cancel_requested', 'internal_note'];

    /**
     * Replace the details of PII-bearing log entries of a booking with a neutral placeholder, and scrub the
     * traveler's own values (and any email address) from every other entry of that booking.
     *
     * The audit trail itself (that something happened, and when) is kept.
     *
     * @param int      $bookingId
     * @param string[] $knownValues The traveler's name, email, phone... as stored before anonymization.
     * @return void
     */
    public static function redactPersonalData(int $bookingId, array $knownValues = []): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tourivo_logs';

        $placeholders = implode(',', array_fill(0, count(self::PII_ACTIONS), '%s'));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET details = %s WHERE booking_id = %d AND action IN ({$placeholders})",
            ...array_merge(['[redacted]', $bookingId], self::PII_ACTIONS)
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

        // Values shorter than 3 characters would match unrelated words.
        $needles = array_values(array_filter(array_map('trim', $knownValues), static fn (string $v): bool => mb_strlen($v) >= 3));

        foreach (self::getTimeline($bookingId) as $entry) {
            $details = (string) ($entry['details'] ?? '');
            if ($details === '' || $details === '[redacted]') {
                continue;
            }

            $clean = (string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $details);
            foreach ($needles as $needle) {
                $clean = str_ireplace($needle, '[redacted]', $clean);
            }

            if ($clean !== $details) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One log row, by primary key.
                $wpdb->update($table, ['details' => $clean], ['id' => (int) $entry['id']], ['%s'], ['%d']);
            }
        }
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
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

        return is_array($results) ? $results : [];
    }
}
