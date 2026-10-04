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
                $status = $assocArgs['status'] ?? '';
                $format = $assocArgs['format'] ?? 'table';

                $where = '1=1';
                $params = [];

                if (!empty($status)) {
                    $where .= ' AND booking_status = %s';
                    $params[] = $status;
                }

                $query = "SELECT id, booking_code, customer_name, customer_email, total_amount, currency, booking_status, payment_status, created_at FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT {$limit}";

                if (!empty($params)) {
                    $query = $wpdb->prepare($query, ...$params);
                }

                $results = $wpdb->get_results($query, ARRAY_A);

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

                $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
                if (!$booking) {
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
                    WP_CLI::error(sprintf(__('Invalid status "%s". Valid statuses are: %s', 'tourivo'), $newStatus, implode(', ', $validStatuses)));
                    return;
                }

                $old = $wpdb->get_var($wpdb->prepare("SELECT booking_status FROM {$table} WHERE id = %d", $id));
                if (!$old) {
                    WP_CLI::error(sprintf(__('Booking #%d not found.', 'tourivo'), $id));
                    return;
                }

                $updated = $wpdb->update($table, ['booking_status' => $newStatus, 'updated_at' => current_time('mysql', 1)], ['id' => $id], ['%s', '%s'], ['%d']);

                if ($updated !== false) {
                    do_action('tourivo/booking_status_changed', $id, $old, $newStatus);
                    WP_CLI::success(sprintf(__('Booking #%1$d status updated from "%2$s" to "%3$s".', 'tourivo'), $id, $old, $newStatus));
                } else {
                    WP_CLI::error(__('Failed to update booking status in database.', 'tourivo'));
                }
                break;

            default:
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

        WP_CLI::line(sprintf(__('Item: #%1$d (%2$s) | Date: %3$s', 'tourivo'), $itemId, get_the_title($itemId), $checkIn));
        if ($avail['available']) {
            WP_CLI::success(sprintf(__('Available! Remaining capacity: %d spots.', 'tourivo'), $avail['available_spots']));
        } else {
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
        $table = $wpdb->prefix . 'tourivo_bookings';

        $totalBookings   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $confirmedCount  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE booking_status = 'confirmed'");
        $pendingCount    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE booking_status = 'pending'");
        $cancelledCount  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE booking_status = 'cancelled'");
        $grossRevenue    = (float) $wpdb->get_var("SELECT SUM(total_amount) FROM {$table} WHERE payment_status = 'paid'");

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
