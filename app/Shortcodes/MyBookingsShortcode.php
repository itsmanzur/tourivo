<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

use Tourivo\Config\Config;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MyBookingsShortcode
 *
 * Provides the Customer Account / "My Bookings" Self-Service Portal:
 * - Displays upcoming, past/completed, and cancelled itineraries.
 * - Enforces strict customer ownership isolation (customer_id OR customer_email matching current user).
 * - Instant access to token-secured vouchers and .ics calendar sync files.
 * - In-app booking cancellation request / direct self-cancel flow with reason input.
 * - Expandable itemized pricing and booking details drawer/modal.
 * - Welcoming authentication card with login/register links and Track Booking fallback for guests.
 *
 * Shortcode: [tourivo_my_bookings]
 *
 * @package Tourivo\Shortcodes
 */
class MyBookingsShortcode
{
    /**
     * Register shortcode and AJAX endpoints.
     *
     * @return void
     */
    public static function register(): void
    {
        add_shortcode('tourivo_my_bookings', [self::class, 'render']);

        // Authenticated AJAX handlers
        add_action('wp_ajax_tourivo_my_bookings_cancel', [self::class, 'handleCancelBookingAjax']);
        add_action('wp_ajax_tourivo_my_bookings_details', [self::class, 'handleBookingDetailsAjax']);
        add_action('wp_ajax_tourivo_get_my_bookings_nonce', [self::class, 'handleGetNonceAjax']);

        // Unauthenticated AJAX fallback handlers
        add_action('wp_ajax_nopriv_tourivo_my_bookings_cancel', [self::class, 'handleUnauthorizedAjax']);
        add_action('wp_ajax_nopriv_tourivo_my_bookings_details', [self::class, 'handleUnauthorizedAjax']);
        add_action('wp_ajax_nopriv_tourivo_get_my_bookings_nonce', [self::class, 'handleGetNonceAjax']);
    }

    /**
     * Render the My Bookings portal.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public static function render(array|string $atts = []): string
    {
        $attributes = shortcode_atts([
            'title'       => __('My Bookings & Trips', 'tourivo'),
            'per_page'    => 10,
            'show_stats'  => 'yes',
            'default_tab' => 'all',
        ], (array) $atts, 'tourivo_my_bookings');

        $perPage    = max(1, min(50, (int) $attributes['per_page']));
        $showStats  = strtolower((string) $attributes['show_stats']) !== 'no';
        $defaultTab = in_array($attributes['default_tab'], ['all', 'upcoming', 'completed', 'cancelled'], true)
            ? (string) $attributes['default_tab']
            : 'all';

        $lookupPageId = (int) Config::get('lookup_page_id', 0);
        $lookupUrl    = $lookupPageId > 0 ? (string) get_permalink($lookupPageId) : home_url('/track-booking/');

        // 1. Guest visitor state: Render welcoming login/register card with Track Booking fallback
        if (!is_user_logged_in()) {
            ob_start();
            tourivo_get_template('my-bookings.php', [
                'isGuest'      => true,
                'title'        => sanitize_text_field((string) $attributes['title']),
                'loginUrl'     => wp_login_url(get_permalink()),
                'registerUrl'  => function_exists('wp_registration_url') ? wp_registration_url() : '',
                'canRegister'  => (bool) get_option('users_can_register', false),
                'lookupUrl'    => esc_url_raw($lookupUrl),
                'primaryColor' => (string) Config::get('primary_color', '#0d9488'),
            ]);
            return ob_get_clean() ?: '';
        }

        // 2. Logged-in traveler state: Retrieve their records
        $currentUser = wp_get_current_user();
        $userId      = (int) $currentUser->ID;
        $userEmail   = (string) $currentUser->user_email;

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        $stats = self::getUserBookingStats($userId, $userEmail);

        // Fetch bookings matching current traveler
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} 
             WHERE customer_id = %d OR (customer_id = 0 AND customer_email = %s AND customer_email != '') 
             ORDER BY id DESC LIMIT %d",
            $userId,
            $userEmail,
            $perPage
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $bookingIds = [];
        foreach ($bookings as $b) {
            $bookingIds[] = (int) $b->id;
        }

        $itemsByBooking = [];
        if (!empty($bookingIds)) {
            $placeholders = implode(',', array_fill(0, count($bookingIds), '%d'));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $allItems = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$itemsTable} WHERE booking_id IN ($placeholders) ORDER BY id ASC",
                ...$bookingIds
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter

            foreach ($allItems as $item) {
                $itemsByBooking[(int) $item->booking_id][] = $item;
            }
        }

        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');
        $today          = gmdate('Y-m-d');
        $formattedList  = [];

        foreach ($bookings as $booking) {
            $bookingId     = (int) $booking->id;
            $items         = $itemsByBooking[$bookingId] ?? [];
            $bookingStatus = (string) $booking->booking_status;
            $paymentStatus = (string) $booking->payment_status;

            // Determine if trip is upcoming or completed
            // Several items (a package) show as one card with the combined title and dates.
            $firstItem = \Tourivo\Support\BookingItems::summaryRow($items, $bookingId);
            $checkInDate = $firstItem && !empty($firstItem->check_in)
                ? gmdate('Y-m-d', strtotime((string) $firstItem->check_in))
                : gmdate('Y-m-d', strtotime((string) $booking->created_at));

            $category = 'upcoming';
            if ($bookingStatus === 'cancelled') {
                $category = 'cancelled';
            } elseif ($bookingStatus === 'completed' || ($bookingStatus === 'confirmed' && $checkInDate < $today)) {
                $category = 'completed';
            }

            // Secure HMAC voucher & calendar sync tokens
            $voucherToken = BookingLookupShortcode::generateVoucherToken($bookingId, (string) $booking->customer_email, $booking->access_key ?? null);
            $voucherUrl   = add_query_arg([
                'action' => 'tourivo_print_voucher',
                'code'   => $booking->booking_code,
                'token'  => $voucherToken,
            ], admin_url('admin-post.php'));

            $calendarUrl = add_query_arg([
                'action' => 'tourivo_download_ics',
                'code'   => $booking->booking_code,
                'token'  => $voucherToken,
            ], admin_url('admin-post.php'));

            $cancelRequested = !empty($booking->cancel_requested_at) && $bookingStatus !== 'cancelled';
            $canCancel       = in_array($bookingStatus, ['pending', 'confirmed', 'on_hold'], true) && !$cancelRequested;

            $formattedList[] = [
                'id'               => $bookingId,
                'booking_code'     => (string) $booking->booking_code,
                'created_at'       => gmdate('M d, Y', strtotime((string) $booking->created_at)),
                'booking_status'   => $bookingStatus,
                'payment_status'   => $paymentStatus,
                'payment_method'   => ucwords(str_replace('_', ' ', (string) $booking->payment_method)),
                'total_amount'     => Money::format((float) $booking->total_amount, $currencySymbol),
                'due_amount'       => Money::format((float) $booking->due_amount, $currencySymbol),
                'category'         => $category,
                'items'            => $items,
                'first_item'       => $firstItem,
                'voucher_url'      => esc_url_raw($voucherUrl),
                'calendar_url'     => esc_url_raw($calendarUrl),
                'can_cancel'       => $canCancel,
                'cancel_requested' => $cancelRequested,
            ];
        }

        ob_start();
        tourivo_get_template('my-bookings.php', [
            'isGuest'       => false,
            'title'         => sanitize_text_field((string) $attributes['title']),
            'currentUser'   => $currentUser,
            'showStats'     => $showStats,
            'stats'         => $stats,
            'defaultTab'    => $defaultTab,
            'bookings'      => $formattedList,
            'lookupUrl'     => esc_url_raw($lookupUrl),
            'nonce'         => wp_create_nonce('tourivo_my_bookings_nonce'),
            'primaryColor'  => (string) Config::get('primary_color', '#0d9488'),
        ]);

        return ob_get_clean() ?: '';
    }

    /**
     * Compute summary statistics for a user's reservations.
     *
     * @param int    $userId
     * @param string $userEmail
     * @return array{total: int, upcoming: int, completed: int, cancelled: int, total_spent: string}
     */
    public static function getUserBookingStats(int $userId, string $userEmail): array
    {
        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bookings = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, booking_status, total_amount, created_at
             FROM {$bookingsTable}
             WHERE customer_id = %d OR (customer_id = 0 AND customer_email = %s AND customer_email != '')",
            $userId,
            $userEmail
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $bookingIds = [];
        foreach ($bookings as $b) {
            $bookingIds[] = (int) $b->id;
        }

        $earliestCheckIns = [];
        if (!empty($bookingIds)) {
            $placeholders = implode(',', array_fill(0, count($bookingIds), '%d'));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $items = (array) $wpdb->get_results($wpdb->prepare(
                "SELECT booking_id, check_in FROM {$itemsTable} WHERE booking_id IN ($placeholders)",
                ...$bookingIds
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter

            foreach ($items as $it) {
                $bId = (int) $it->booking_id;
                if (!empty($it->check_in)) {
                    if (!isset($earliestCheckIns[$bId]) || (string) $it->check_in < $earliestCheckIns[$bId]) {
                        $earliestCheckIns[$bId] = (string) $it->check_in;
                    }
                }
            }
        }

        $total      = count($bookings);
        $upcoming   = 0;
        $completed  = 0;
        $cancelled  = 0;
        $totalSpent = 0.0;
        $today      = gmdate('Y-m-d');

        foreach ($bookings as $row) {
            $status   = (string) $row->booking_status;
            $bId      = (int) $row->id;
            $checkInRaw = $earliestCheckIns[$bId] ?? (string) $row->created_at;
            $checkIn  = gmdate('Y-m-d', strtotime($checkInRaw));

            if ($status === 'cancelled') {
                $cancelled++;
            } elseif ($status === 'completed' || ($status === 'confirmed' && $checkIn < $today)) {
                $completed++;
                $totalSpent += (float) $row->total_amount;
            } else {
                $upcoming++;
                if ($status === 'confirmed') {
                    $totalSpent += (float) $row->total_amount;
                }
            }
        }

        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');

        return [
            'total'       => $total,
            'upcoming'    => $upcoming,
            'completed'   => $completed,
            'cancelled'   => $cancelled,
            'total_spent' => Money::format($totalSpent, $currencySymbol),
        ];
    }

    /**
     * Cancel or request cancellation for a user's booking with ownership validation.
     *
     * @param int    $bookingId
     * @param string $reason
     * @return array{success: bool, mode?: string, message: string, code?: int}
     */
    public static function processUserCancellation(int $bookingId, string $reason = ''): array
    {
        if (!is_user_logged_in()) {
            return [
                'success' => false,
                'code'    => 401,
                'message' => __('Please log in to manage your bookings.', 'tourivo'),
            ];
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE id = %d LIMIT 1",
            $bookingId
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$booking) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => __('Booking not found.', 'tourivo'),
            ];
        }

        $currentUser   = wp_get_current_user();
        $userId        = (int) $currentUser->ID;
        $userEmail     = (string) $currentUser->user_email;
        $isOwner       = self::userOwnsBooking($booking, $userId, $userEmail);
        $isAdmin       = current_user_can('manage_tourivo_bookings');

        if (!$isOwner && !$isAdmin) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => __('You do not have permission to manage this booking.', 'tourivo'),
            ];
        }

        return BookingLookupShortcode::processCancellationRequest(
            (string) $booking->booking_code,
            (string) $booking->customer_email,
            $reason
        );
    }

    /**
     * Retrieve detailed breakdown for a booking owned by current traveler.
     *
     * @param int $bookingId
     * @return array{success: bool, code?: int, message?: string, booking?: array<string, mixed>, items?: array<mixed>}
     */
    public static function getUserBookingDetails(int $bookingId): array
    {
        if (!is_user_logged_in()) {
            return [
                'success' => false,
                'code'    => 401,
                'message' => __('Please log in to view booking details.', 'tourivo'),
            ];
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE id = %d LIMIT 1",
            $bookingId
        ));

        if (!$booking) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => __('Booking not found.', 'tourivo'),
            ];
        }

        $currentUser = wp_get_current_user();
        $userId      = (int) $currentUser->ID;
        $userEmail   = (string) $currentUser->user_email;
        $isOwner     = self::userOwnsBooking($booking, $userId, $userEmail);
        $isAdmin     = current_user_can('manage_tourivo_bookings');

        if (!$isOwner && !$isAdmin) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => __('You do not have permission to view this booking.', 'tourivo'),
            ];
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$itemsTable} WHERE booking_id = %d ORDER BY id ASC",
            $bookingId
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');

        $formattedItems = [];
        foreach ($items as $item) {
            $formattedItems[] = [
                'item_title'        => (string) $item->item_title,
                'item_type'         => (string) $item->item_type,
                'check_in'          => !empty($item->check_in) ? gmdate('M d, Y', strtotime((string) $item->check_in)) : '—',
                'check_out'         => !empty($item->check_out) ? gmdate('M d, Y', strtotime((string) $item->check_out)) : '',
                'time_slot'         => !empty($item->time_slot) ? (string) $item->time_slot : '',
                'adults_count'      => (int) $item->adults_count,
                'children_count'    => (int) $item->children_count,
                'infants_count'     => (int) $item->infants_count,
                'quantity'          => (int) $item->quantity,
                'unit_price'        => Money::format((float) $item->unit_price, $currencySymbol),
                'total_price'       => Money::format((float) $item->total_price, $currencySymbol),
                'pricing_breakdown' => !empty($item->pricing_breakdown) ? json_decode((string) $item->pricing_breakdown, true) : null,
            ];
        }

        return [
            'success' => true,
            'booking' => [
                'id'              => (int) $booking->id,
                'booking_code'    => (string) $booking->booking_code,
                'customer_name'   => (string) $booking->customer_name,
                'customer_email'  => (string) $booking->customer_email,
                'customer_phone'  => (string) $booking->customer_phone,
                'billing_address' => (string) $booking->billing_address,
                'booking_status'  => ucfirst((string) $booking->booking_status),
                'payment_status'  => ucfirst((string) $booking->payment_status),
                'payment_method'  => ucwords(str_replace('_', ' ', (string) $booking->payment_method)),
                'total_amount'    => Money::format((float) $booking->total_amount, $currencySymbol),
                'tax_amount'      => Money::format((float) $booking->tax_amount, $currencySymbol),
                'discount_amount' => Money::format((float) $booking->discount_amount, $currencySymbol),
                'paid_amount'     => Money::format((float) $booking->paid_amount, $currencySymbol),
                'due_amount'      => Money::format((float) $booking->due_amount, $currencySymbol),
                'customer_notes'  => (string) $booking->customer_notes,
                'created_at'      => gmdate('M d, Y H:i', strtotime((string) $booking->created_at)),
            ],
            'items'   => $formattedItems,
        ];
    }

    /**
     * A logged-in traveler owns a booking tied to their account, or a guest booking that still matches their email.
     */
    private static function userOwnsBooking(object $booking, int $userId, string $userEmail): bool
    {
        if ($userId > 0 && (int) $booking->customer_id === $userId) {
            return true;
        }

        return (int) $booking->customer_id === 0
            && $userEmail !== ''
            && strcasecmp((string) $booking->customer_email, $userEmail) === 0;
    }

    /**
     * AJAX handler for user cancellation action.
     *
     * @return void
     */
    public static function handleCancelBookingAjax(): void
    {
        check_ajax_referer('tourivo_my_bookings_nonce', 'nonce');

        $bookingId = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $reason    = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash((string) $_POST['reason'])) : '';

        if ($bookingId <= 0) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'tourivo')], 400);
        }

        $res = self::processUserCancellation($bookingId, $reason);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res, $res['code'] ?? 400);
        }
    }

    /**
     * AJAX handler for fetching single booking details.
     *
     * @return void
     */
    public static function handleBookingDetailsAjax(): void
    {
        check_ajax_referer('tourivo_my_bookings_nonce', 'nonce');

        $bookingId = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        if ($bookingId <= 0) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'tourivo')], 400);
        }

        $res = self::getUserBookingDetails($bookingId);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res, $res['code'] ?? 400);
        }
    }

    /**
     * Send rejection for unauthorized AJAX access.
     *
     * @return void
     */
    public static function handleUnauthorizedAjax(): void
    {
        wp_send_json_error(['message' => __('Please log in to continue.', 'tourivo')], 401);
    }

    /**
     * Nonce refresh handler for cached page support.
     *
     * @return void
     */
    public static function handleGetNonceAjax(): void
    {
        nocache_headers();
        wp_send_json_success([
            'nonce' => wp_create_nonce('tourivo_my_bookings_nonce'),
        ]);
    }
}
