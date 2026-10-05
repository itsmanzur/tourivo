<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Config\Config;

if (!defined('ABSPATH')) {
    exit;
}

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
     * Calculate counts of records eligible for retention actions (dry-run preview).
     *
     * @return array{bookings_to_anonymize: int, inquiries_to_delete: int}
     */
    public static function getRetentionDryRunCounts(): array
    {
        global $wpdb;

        $anonymizeMonths = (int) Config::get('anonymize_after_months', 0);
        $inquiriesMonths = (int) Config::get('delete_inquiries_after_months', 0);

        $bookingsCount  = 0;
        $inquiriesCount = 0;

        $bookingsTable  = $wpdb->prefix . 'tourivo_bookings';
        $inquiriesTable = $wpdb->prefix . 'tourivo_inquiries';

        if ($anonymizeMonths > 0) {
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$anonymizeMonths} months"));
            $bookingsCount = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$bookingsTable} 
                 WHERE booking_status IN ('completed', 'cancelled') 
                   AND customer_name != 'Anonymized' 
                   AND created_at <= %s",
                $cutoffDate
            ));
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

        $anonymizeMonths = (int) Config::get('anonymize_after_months', 0);
        $inquiriesMonths = (int) Config::get('delete_inquiries_after_months', 0);

        $anonymizedBookings = 0;
        $deletedInquiries   = 0;

        $bookingsTable  = $wpdb->prefix . 'tourivo_bookings';
        $inquiriesTable = $wpdb->prefix . 'tourivo_inquiries';

        // 1. Process Bookings Anonymization (Batch limit 200)
        if ($anonymizeMonths > 0) {
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$anonymizeMonths} months"));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, customer_email FROM {$bookingsTable} 
                 WHERE booking_status IN ('completed', 'cancelled') 
                   AND customer_name != 'Anonymized' 
                   AND created_at <= %s 
                 ORDER BY id ASC LIMIT 200",
                $cutoffDate
            ));

            foreach ($rows as $row) {
                $anonEmail = 'anon-' . substr(md5((string)$row->id . ($row->customer_email ?? '')), 0, 12) . '@anonymized.invalid';

                $wpdb->update(
                    $bookingsTable,
                    [
                        'customer_name'   => 'Anonymized',
                        'customer_email'  => $anonEmail,
                        'customer_phone'  => '',
                        'billing_address' => '',
                        'customer_notes'  => '',
                        'ip_address'      => '',
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
