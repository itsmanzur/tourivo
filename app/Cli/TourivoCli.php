<?php

declare(strict_types=1);

namespace Tourivo\Cli;

use Tourivo\Database\Schema;
use Tourivo\Models\Tour;
use Tourivo\Models\Hotel;
use Tourivo\Models\Room;
use Tourivo\Services\InventoryService;
use WP_CLI;
use WP_CLI_Command;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manage Tourivo bookings, inventory, migrations, and stats from the command line.
 *
 * ## EXAMPLES
 *
 *     # List latest bookings
 *     wp tourivo booking list --limit=10
 *
 *     # Get details of booking #12
 *     wp tourivo booking get 12
 *
 *     # Update booking status
 *     wp tourivo booking set-status 12 confirmed
 *
 *     # Run database migrations
 *     wp tourivo db migrate
 *
 *     # View system booking statistics
 *     wp tourivo stats
 *
 * @package Tourivo\Cli
 */
class TourivoCli extends WP_CLI_Command
{
    /**
     * Manage Tourivo bookings.
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform: list, get, set-status.
     *
     * [<id>]
     * : Booking ID (required for 'get' and 'set-status').
     *
     * [<status>]
     * : New booking status (required for 'set-status': pending, confirmed, completed, cancelled, on_hold).
     *
     * [--status=<status>]
     * : Filter by status (for 'list').
     *
     * [--limit=<limit>]
     * : Number of bookings to retrieve (default: 20).
     *
     * [--format=<format>]
     * : Output format: table, json, csv, yaml, ids, count. (default: table).
     *
     * ## EXAMPLES
     *
     *     wp tourivo booking list --status=confirmed
     *     wp tourivo booking get 45
     *     wp tourivo booking set-status 45 confirmed
     *
     * @subcommand booking
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     * @return void
     */
    public function booking(array $args, array $assocArgs): void
    {
        global $wpdb;
        $action = $args[0] ?? 'list';
        $table  = $wpdb->prefix . 'tourivo_bookings';

        switch ($action) {
            case 'list':
                $limit  = isset($assocArgs['limit']) ? max(1, (int) $assocArgs['limit']) : 20;
                $status = isset($assocArgs['status']) ? sanitize_key($assocArgs['status']) : '';
                $format = $assocArgs['format'] ?? 'table';

                if (!empty($status)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT id, booking_code, customer_name, customer_email, total_amount, currency, booking_status, payment_status, created_at FROM {$wpdb->prefix}tourivo_bookings WHERE booking_status = %s ORDER BY id DESC LIMIT %d",
                            $status,
                            $limit
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT id, booking_code, customer_name, customer_email, total_amount, currency, booking_status, payment_status, created_at FROM {$wpdb->prefix}tourivo_bookings ORDER BY id DESC LIMIT %d",
                            $limit
                        ),
                        ARRAY_A
                    );
                }

                if (empty($results)) {
                    WP_CLI::log(__('No bookings found matching your criteria.', 'tourivo'));
                    return;
                }

                WP_CLI\Utils\format_items($format, $results, ['id', 'booking_code', 'customer_name', 'customer_email', 'total_amount', 'currency', 'booking_status', 'payment_status', 'created_at']);
                break;

            case 'get':
                $id = isset($args[1]) ? (int) $args[1] : 0;
                if ($id <= 0) {
                    WP_CLI::error(__('Please provide a valid numeric booking ID. Example: wp tourivo booking get 12', 'tourivo'));
                    return;
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d", $id), ARRAY_A);
                if (!$booking) {
                    /* translators: %d: Numeric booking ID */
                    WP_CLI::error(sprintf(__('Booking #%d not found.', 'tourivo'), $id));
                    return;
                }

                $format = $assocArgs['format'] ?? 'table';
                if ($format === 'json') {
                    WP_CLI::line((string) wp_json_encode($booking, JSON_PRETTY_PRINT));
                } else {
                    $displayData = [];
                    foreach ($booking as $key => $val) {
                        $displayData[] = ['Field' => $key, 'Value' => (string) $val];
                    }
                    WP_CLI\Utils\format_items('table', $displayData, ['Field', 'Value']);
                }
                break;

            case 'set-status':
                $id        = isset($args[1]) ? (int) $args[1] : 0;
                $newStatus = isset($args[2]) ? sanitize_text_field($args[2]) : '';

                if ($id <= 0 || empty($newStatus)) {
                    WP_CLI::error(__('Usage: wp tourivo booking set-status <id> <status>', 'tourivo'));
                    return;
                }

                $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'on_hold'];
                if (!in_array($newStatus, $validStatuses, true)) {
                    /* translators: 1: Invalid status string, 2: Comma-separated list of valid statuses */
                    WP_CLI::error(sprintf(__('Invalid status "%1$s". Valid statuses are: %2$s', 'tourivo'), $newStatus, implode(', ', $validStatuses)));
                    return;
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $old = $wpdb->get_var($wpdb->prepare("SELECT booking_status FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d", $id));
                if (!$old) {
                    /* translators: %d: Numeric booking ID */
                    WP_CLI::error(sprintf(__('Booking #%d not found.', 'tourivo'), $id));
                    return;
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $updated = $wpdb->update($table, ['booking_status' => $newStatus, 'updated_at' => current_time('mysql', 1)], ['id' => $id], ['%s', '%s'], ['%d']);

                if ($updated !== false) {
                    do_action('tourivo/booking_status_changed', $id, $old, $newStatus);
                    /* translators: 1: Booking ID, 2: Old status, 3: New status */
                    WP_CLI::success(sprintf(__('Booking #%1$d status updated from "%2$s" to "%3$s".', 'tourivo'), $id, $old, $newStatus));
                } else {
                    WP_CLI::error(__('Failed to update booking status in database.', 'tourivo'));
                }
                break;

            default:
                /* translators: %s: Action name */
                WP_CLI::error(sprintf(__('Unknown booking action "%s". Use list, get, or set-status.', 'tourivo'), $action));
                break;
        }
    }

    /**
     * Run Tourivo database migrations.
     *
     * ## EXAMPLES
     *
     *     wp tourivo db migrate
     *
     * @subcommand db
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     * @return void
     */
    public function db(array $args, array $assocArgs): void
    {
        $sub = $args[0] ?? 'migrate';

        if ($sub === 'migrate') {
            WP_CLI::log(__('Running Tourivo database migrations...', 'tourivo'));
            Schema::migrate();
            WP_CLI::success(__('Tourivo database schema migrated successfully.', 'tourivo'));
        } else {
            /* translators: %s: Unknown command name */
            WP_CLI::error(sprintf(__('Unknown db command "%s". Use: wp tourivo db migrate', 'tourivo'), $sub));
        }
    }

    /**
     * Check inventory availability for a tour or room.
     *
     * ## OPTIONS
     *
     * <item_id>
     * : Tour or Room Post ID.
     *
     * [--check_in=<date>]
     * : Check-in date (YYYY-MM-DD). Default: today.
     *
     * [--check_out=<date>]
     * : Check-out date (YYYY-MM-DD) for hotels/rooms.
     *
     * [--time_slot=<slot>]
     * : Time slot (default: all_day).
     *
     * ## EXAMPLES
     *
     *     wp tourivo inventory 15 --check_in=2026-11-01
     *
     * @subcommand inventory
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     * @return void
     */
    public function inventory(array $args, array $assocArgs): void
    {
        $itemId = isset($args[0]) ? (int) $args[0] : 0;
        if ($itemId <= 0) {
            WP_CLI::error(__('Please specify a valid item ID. Example: wp tourivo inventory 15', 'tourivo'));
            return;
        }

        $postType = get_post_type($itemId);
        $itemType = ($postType === 'tourivo_room') ? 'room' : 'tour';
        $checkIn  = $assocArgs['check_in'] ?? current_time('Y-m-d');
        $checkOut = $assocArgs['check_out'] ?? null;
        $timeSlot = $assocArgs['time_slot'] ?? 'all_day';

        $inventoryRepo = new \Tourivo\Repositories\InventoryRepository();
        $inventoryService = new InventoryService($inventoryRepo);

        $avail = $inventoryService->checkAvailability($itemId, $itemType, $checkIn, $checkOut, $timeSlot, 1);

        /* translators: 1: Item ID, 2: Item title, 3: Date string */
        WP_CLI::line(sprintf(__('Item: #%1$d (%2$s) | Date: %3$s', 'tourivo'), $itemId, get_the_title($itemId), $checkIn));
        if ($avail['available']) {
            /* translators: %d: Remaining capacity count */
            WP_CLI::success(sprintf(__('Available! Remaining capacity: %d spots.', 'tourivo'), $avail['available_spots']));
        } else {
            /* translators: %s: Error message reason */
            WP_CLI::warning(sprintf(__('Unavailable: %s', 'tourivo'), $avail['message']));
        }
    }

    /**
     * Display high-level booking and inventory analytics.
     *
     * ## EXAMPLES
     *
     *     wp tourivo stats
     *
     * @subcommand stats
     * @return void
     */
    public function stats(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $totalBookings   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tourivo_bookings");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $confirmedCount  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tourivo_bookings WHERE booking_status = 'confirmed'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pendingCount    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tourivo_bookings WHERE booking_status = 'pending'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $cancelledCount  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tourivo_bookings WHERE booking_status = 'cancelled'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $grossRevenue    = (float) $wpdb->get_var("SELECT SUM(total_amount) FROM {$wpdb->prefix}tourivo_bookings WHERE payment_status = 'paid'");

        $toursCount      = (int) wp_count_posts('tourivo_tour')->publish;
        $hotelsCount     = (int) wp_count_posts('tourivo_hotel')->publish;
        $roomsCount      = (int) wp_count_posts('tourivo_room')->publish;

        $rows = [
            ['Metric' => 'Total Bookings', 'Value' => (string) $totalBookings],
            ['Metric' => 'Confirmed Bookings', 'Value' => (string) $confirmedCount],
            ['Metric' => 'Pending Bookings', 'Value' => (string) $pendingCount],
            ['Metric' => 'Cancelled Bookings', 'Value' => (string) $cancelledCount],
            ['Metric' => 'Gross Paid Revenue', 'Value' => '$' . number_format($grossRevenue, 2)],
            ['Metric' => 'Published Tours', 'Value' => (string) $toursCount],
            ['Metric' => 'Published Hotels', 'Value' => (string) $hotelsCount],
            ['Metric' => 'Published Rooms', 'Value' => (string) $roomsCount],
        ];

        WP_CLI\Utils\format_items('table', $rows, ['Metric', 'Value']);
    }
}
