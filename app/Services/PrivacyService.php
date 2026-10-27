<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Config\Config;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned tables named from $wpdb->prefix (WP 6.0 has no %i); every value goes through prepare(); live data, not cacheable.

/**
 * Class PrivacyService
 *
 * Handles scheduled data retention routines, automatic booking anonymization,
 * and expired inquiry purges.
 *
 * @package Tourivo\Services
 */
class PrivacyService
{
    /**
     * Booking statuses anonymized by the retention routine unless the settings say otherwise.
     *
     * @var string[]
     */
    public const DEFAULT_RETENTION_STATUSES = ['completed', 'cancelled'];

    /**
     * Statuses the retention setting may list.
     *
     * @var string[]
     */
    public const RETENTION_STATUS_CHOICES = ['completed', 'cancelled', 'confirmed', 'pending', 'on_hold'];

    /**
     * Days after a booking's trip ended before it is anonymized (0 = off).
     *
     * Older installs stored the period in months (anonymize_after_months); that still applies, as 30-day
     * months, until a day value is saved.
     *
     * @return int
     */
    public static function retentionDays(): int
    {
        $days = (int) Config::get('anonymize_after_days', 0);
        if ($days <= 0) {
            $days = (int) Config::get('anonymize_after_months', 0) * 30;
        }
        return max(0, $days);
    }

    /**
     * Booking statuses the retention routine anonymizes.
     *
     * @return string[]
     */
    public static function retentionStatuses(): array
    {
        $statuses = array_values(array_intersect(
            (array) Config::get('retention_statuses', self::DEFAULT_RETENTION_STATUSES),
            self::RETENTION_STATUS_CHOICES
        ));
        return $statuses ?: self::DEFAULT_RETENTION_STATUSES;
    }

    /**
     * Bookings due for anonymization: a retained status, not yet anonymized, and whose trip ended at least
     * $days days ago. The end is the latest check-out (or check-in) over all its items, so a package counts
     * from its last part; bookings without items fall back to their creation date.
     *
     * @param int $days
     * @param int $limit
     * @return array<int, object> Rows with id, customer_email, customer_name, customer_phone, trip_end.
     */
    protected static function retentionCandidates(int $days, int $limit = PHP_INT_MAX): array
    {
        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        $statuses = self::retentionStatuses();
        $cutoff   = current_datetime()->modify(sprintf('-%d days', $days))->format('Y-m-d');
        $in       = implode(',', array_fill(0, count($statuses), '%s'));

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.customer_email, b.customer_name, b.customer_phone,
                    COALESCE(MAX(DATE(COALESCE(i.check_out, i.check_in))), DATE(b.created_at)) AS trip_end
             FROM {$bookingsTable} b
             LEFT JOIN {$itemsTable} i ON i.booking_id = b.id
             WHERE b.booking_status IN ({$in})
               AND b.customer_name != 'Anonymized'
             GROUP BY b.id
             HAVING trip_end <= %s
             ORDER BY b.id ASC
             LIMIT %d",
            ...array_merge($statuses, [$cutoff, max(1, $limit)])
        ));
    }

    /**
     * Calculate counts of records eligible for retention actions (dry-run preview).
     *
     * @return array{bookings_to_anonymize: int, inquiries_to_delete: int}
     */
    public static function getRetentionDryRunCounts(): array
    {
        global $wpdb;

        $anonymizeDays   = self::retentionDays();
        $inquiriesMonths = (int) Config::get('delete_inquiries_after_months', 0);

        $bookingsCount  = 0;
        $inquiriesCount = 0;

        $inquiriesTable = $wpdb->prefix . 'tourivo_inquiries';

        if ($anonymizeDays > 0) {
            $bookingsCount = count(self::retentionCandidates($anonymizeDays));
        }

        if ($inquiriesMonths > 0) {
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$inquiriesMonths} months"));
            $inquiriesCount = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$inquiriesTable} 
                 WHERE created_at <= %s",
                $cutoffDate
            ));
        }

        return [
            'bookings_to_anonymize' => $bookingsCount,
            'inquiries_to_delete'   => $inquiriesCount,
        ];
    }

    /**
     * Run daily retention cron to anonymize old completed bookings and delete expired inquiries.
     * Batched to process at most 200 items per run to prevent timeout/memory spikes.
     *
     * @return array{anonymized_bookings: int, deleted_inquiries: int}
     */
    public static function runDailyRetention(): array
    {
        global $wpdb;

        $anonymizeDays   = self::retentionDays();
        $inquiriesMonths = (int) Config::get('delete_inquiries_after_months', 0);

        $anonymizedBookings = 0;
        $deletedInquiries   = 0;

        $bookingsTable  = $wpdb->prefix . 'tourivo_bookings';
        $inquiriesTable = $wpdb->prefix . 'tourivo_inquiries';

        // 1. Process Bookings Anonymization (Batch limit 200)
        if ($anonymizeDays > 0) {
            $rows = self::retentionCandidates($anonymizeDays, 200);

            foreach ($rows as $row) {
                // Scrub the traveler's own values out of every log entry before they disappear from the row.
                LogService::redactPersonalData((int) $row->id, [
                    (string) ($row->customer_email ?? ''),
                    (string) ($row->customer_name ?? ''),
                    (string) ($row->customer_phone ?? ''),
                ]);

                $anonEmail = 'anon-' . substr(md5((string)$row->id . ($row->customer_email ?? '')), 0, 12) . '@anonymized.invalid';

                $wpdb->update(
                    $bookingsTable,
                    [
                        'customer_name'   => 'Anonymized',
                        'customer_email'  => $anonEmail,
                        'customer_phone'  => '',
                        'billing_address' => '',
                        'customer_notes'  => '',
                        'admin_notes'     => '',
                        'transaction_id'  => '',
                        'ip_address'      => '',
                        'access_key'      => bin2hex(random_bytes(16)),
                    ],
                    ['id' => $row->id]
                );

                $anonymizedBookings++;
            }
        }

        // 2. Process Inquiries Purge (Batch limit 200)
        if ($inquiriesMonths > 0) {
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$inquiriesMonths} months"));
            $inquiryIds = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$inquiriesTable} 
                 WHERE created_at <= %s 
                 ORDER BY id ASC LIMIT 200",
                $cutoffDate
            ));

            if (!empty($inquiryIds)) {
                $placeholders = implode(',', array_fill(0, count($inquiryIds), '%d'));
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$inquiriesTable} WHERE id IN ($placeholders)",
                    ...$inquiryIds
                ));

                $deletedInquiries = count($inquiryIds);
            }
        }

        if ($anonymizedBookings > 0 || $deletedInquiries > 0) {
            LogService::log(
                0,
                'retention_cron',
                sprintf('Privacy retention routine processed: %d booking(s) anonymized, %d inquiry/inquiries purged.', $anonymizedBookings, $deletedInquiries),
                0
            );
        }

        return [
            'anonymized_bookings' => $anonymizedBookings,
            'deleted_inquiries'   => $deletedInquiries,
        ];
    }
}
