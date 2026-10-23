<?php

declare(strict_types=1);

namespace Tourivo\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookingItems
 *
 * A booking can hold several items (for example a package's tours and hotel nights). These helpers give
 * the screens, emails and exports one title and one date range for the whole booking.
 *
 * @package Tourivo\Support
 */
final class BookingItems
{
    /**
     * Line items of a booking, in the order they were booked.
     *
     * @return array<int, object>
     */
    public static function forBooking(int $bookingId): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_booking_items WHERE booking_id = %d ORDER BY id ASC", $bookingId));

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * One title for the booking: the item's own title, or the items joined ("Tour + Hotel - Room").
     * Add-ons can name the whole booking (e.g. a package) through the tourivo/booking_title filter.
     *
     * @param array<int, object|array<string, mixed>> $rows
     * @param array<string, mixed>                     $context Booking data when the title is built at creation.
     */
    public static function title(array $rows, int $bookingId = 0, array $context = []): string
    {
        $titles = [];
        foreach ($rows as $row) {
            $title = is_object($row) ? (string) ($row->item_title ?? '') : (string) ($row['item_title'] ?? $row['title'] ?? '');
            if ($title !== '') {
                $titles[] = $title;
            }
        }

        if (count($titles) > 3) {
            /* translators: 1: first item, 2: second item, 3: number of further items */
            $title = sprintf(__('%1$s + %2$s + %3$d more', 'tourivo'), $titles[0], $titles[1], count($titles) - 2);
        } else {
            $title = implode(' + ', $titles);
        }

        return (string) apply_filters('tourivo/booking_title', $title, $bookingId, $rows, $context);
    }

    /**
     * The first item with the booking's combined title and full date range, for screens built around one
     * item (lists, cards, calendar files). Null for a booking without items.
     *
     * @param array<int, object> $rows
     */
    public static function summaryRow(array $rows, int $bookingId = 0): ?object
    {
        if ($rows === []) {
            return null;
        }
        $summary = clone $rows[0];
        if (count($rows) > 1) {
            $span = self::span($rows);
            $summary->item_title = self::title($rows, $bookingId);
            $summary->check_in   = $span['check_in'] . ' 00:00:00';
            $summary->check_out  = $span['check_out'] . ' 00:00:00';
        }

        return $summary;
    }

    /**
     * Earliest start and latest end over all items (Y-m-d; end equals start for a single-day booking).
     *
     * @param array<int, object|array<string, mixed>> $rows
     * @return array{check_in: string, check_out: string}
     */
    public static function span(array $rows): array
    {
        $start = '';
        $end   = '';
        foreach ($rows as $row) {
            $in  = substr((string) (is_object($row) ? ($row->check_in ?? '') : ($row['check_in'] ?? '')), 0, 10);
            $out = substr((string) (is_object($row) ? ($row->check_out ?? '') : ($row['check_out'] ?? '')), 0, 10);
            if ($in === '') {
                continue;
            }
            $out   = $out !== '' ? $out : $in;
            $start = ($start === '' || $in < $start) ? $in : $start;
            $end   = ($end === '' || $out > $end) ? $out : $end;
        }

        return ['check_in' => $start, 'check_out' => $end];
    }
}
