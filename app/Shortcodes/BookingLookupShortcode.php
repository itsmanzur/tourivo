<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\LogService;
use Tourivo\Support\ClientIp;
use Tourivo\Support\RateLimiter;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookingLookupShortcode
 *
 * Provides a frontend "Track My Booking" lookup portal with:
 * - Real-time lookup with honeypot & IP-based rate limiting (cached-page safe)
 * - Printable voucher token generation & verification
 * - Customer Cancellation Request / Direct Self-Cancel (within configured window)
 * - LogService & Admin alert integration
 *
 * Shortcode: [tourivo_booking_lookup]
 *
 * @package Tourivo\Shortcodes
 */
class BookingLookupShortcode
{
    /**
     * Register shortcode and AJAX endpoints.
     *
     * @return void
     */
    public static function register(): void
    {
        add_shortcode('tourivo_booking_lookup', [self::class, 'render']);

        // Lookup AJAX
        add_action('wp_ajax_tourivo_lookup_booking', [self::class, 'handleLookupAjax']);
        add_action('wp_ajax_nopriv_tourivo_lookup_booking', [self::class, 'handleLookupAjax']);

        // Cancellation Request AJAX
        add_action('wp_ajax_tourivo_request_booking_cancellation', [self::class, 'handleCancellationAjax']);
        add_action('wp_ajax_nopriv_tourivo_request_booking_cancellation', [self::class, 'handleCancellationAjax']);

        // Nonce refresh for cached pages
        add_action('wp_ajax_tourivo_get_lookup_nonce', [self::class, 'handleGetLookupNonce']);
        add_action('wp_ajax_nopriv_tourivo_get_lookup_nonce', [self::class, 'handleGetLookupNonce']);
    }

    /**
     * Render the booking lookup interface.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public static function render(array|string $atts = []): string
    {
        $attributes = shortcode_atts([
            'title'       => __('Track Your Booking', 'tourivo'),
            'description' => __('Enter your booking reference code and email to check your reservation status and download your voucher.', 'tourivo'),
        ], (array) $atts, 'tourivo_booking_lookup');

        ob_start();
        tourivo_get_template('booking-lookup.php', [
            'tourivoLookupTitle' => sanitize_text_field((string) $attributes['title']),
            'tourivoLookupDesc'  => sanitize_text_field((string) $attributes['description']),
            'tourivoNonce'       => wp_create_nonce('tourivo_lookup_nonce'),
        ]);

        return ob_get_clean() ?: '';
    }

    /**
     * Provide fresh lookup nonce for heavily cached frontend pages.
     *
     * @return void
     */
    public static function handleGetLookupNonce(): void
    {
        nocache_headers();
        wp_send_json_success([
            'nonce' => wp_create_nonce('tourivo_lookup_nonce'),
        ]);
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- every request-handling method up to the matching enable is gated by hasValidNonce() (wp_verify_nonce).

    /**
     * Require a valid lookup nonce. Cached pages obtain a fresh one through `tourivo_get_lookup_nonce`,
     * so a missing or stale token is always rejected with the machine-readable `nonce_expired` code.
     *
     * @return bool True when the request carries a valid nonce; otherwise an error response was already sent.
     */
    protected static function hasValidNonce(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';

        if ($nonce !== '' && wp_verify_nonce($nonce, 'tourivo_lookup_nonce')) {
            return true;
        }

        wp_send_json_error([
            'message' => __('Security token expired. Please refresh the page and try again.', 'tourivo'),
            'code'    => 'nonce_expired',
        ], 403);

        return false;
    }

    /**
     * Handle AJAX lookup request with rate limiting and secure verification.
     *
     * @return void
     */
    public static function handleLookupAjax(): void
    {
        // Check Honeypot spam field
        if (!empty($_POST['trv_hp_check'])) {
            wp_send_json_error(['message' => __('Submission blocked.', 'tourivo')], 400);
        }

        $ip = ClientIp::get();
        // Rate limiting: max 10 lookup attempts per 5 minutes per IP
        if (!RateLimiter::hit('trv_lookup_' . md5($ip), 10, 300)) {
            wp_send_json_error([
                'message' => __('Too many lookup attempts. Please wait a few minutes before trying again.', 'tourivo'),
            ], 429);
            return;
        }

        if (!self::hasValidNonce()) {
            return;
        }

        $bookingCode = isset($_POST['booking_code']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_POST['booking_code']))) : '';
        $email       = isset($_POST['customer_email']) ? sanitize_email(wp_unslash((string) $_POST['customer_email'])) : '';

        if (empty($bookingCode) || empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid Booking Reference Code and Email Address.', 'tourivo'),
            ], 400);
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE booking_code = %s AND customer_email = %s LIMIT 1",
            $bookingCode,
            $email
        ));

        if (!$booking) {
            wp_send_json_error([
                'message' => __('No reservation found matching the provided Booking Code and Email Address. Please check your confirmation details.', 'tourivo'),
            ], 404);
        }

        $items = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$itemsTable} WHERE booking_id = %d",
            $booking->id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $payload = self::formatLookupResponseData($booking, $items);
        wp_send_json_success($payload);
    }

    /**
     * AJAX handler for traveler cancellation requests or direct self-cancellations.
     *
     * @return void
     */
    public static function handleCancellationAjax(): void
    {
        if (!empty($_POST['trv_hp_check'])) {
            wp_send_json_error(['message' => __('Submission blocked.', 'tourivo')], 400);
            return;
        }

        if (!self::hasValidNonce()) {
            return;
        }

        $bookingCode = isset($_POST['booking_code']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_POST['booking_code']))) : '';
        $email       = isset($_POST['customer_email']) ? sanitize_email(wp_unslash((string) $_POST['customer_email'])) : '';
        $reason      = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash((string) $_POST['reason'])) : '';

        $res = self::processCancellationRequest($bookingCode, $email, $reason);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res, $res['code'] ?? 400);
        }
    }

    // phpcs:enable WordPress.Security.NonceVerification.Missing

    /**
     * Core business logic for processing cancellation request or direct self-cancellation.
     *
     * @param string $bookingCode
     * @param string $email
     * @param string $reason
     * @return array{success: bool, mode?: string, message: string, code?: int}
     */
    public static function processCancellationRequest(string $bookingCode, string $email, string $reason = ''): array
    {
        $ip = ClientIp::get();

        // Rate limiting: max 10 attempts per 5 minutes per IP
        if (!RateLimiter::hit('trv_cancel_req_' . md5($ip), 10, 300)) {
            return [
                'success' => false,
                'code'    => 429,
                'message' => __('Too many cancellation requests submitted. Please try again later.', 'tourivo'),
            ];
        }

        if (empty($bookingCode) || empty($email) || !is_email($email)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => __('Please provide both a valid Booking Code and Email Address.', 'tourivo'),
            ];
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE booking_code = %s AND customer_email = %s LIMIT 1",
            $bookingCode,
            $email
        ));

        if (!$booking) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => __('No reservation found matching the provided Booking Code and Email Address.', 'tourivo'),
            ];
        }

        $bookingId = (int) $booking->id;

        if ($booking->booking_status === 'cancelled') {
            return [
                'success' => false,
                'code'    => 400,
                'message' => __('This reservation has already been cancelled.', 'tourivo'),
            ];
        }

        $allowCancel = (bool) Config::get('allow_cancel_requests', true);
        if (!$allowCancel) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => __('Online cancellation requests are disabled. Please contact customer support.', 'tourivo'),
            ];
        }

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$itemsTable} WHERE booking_id = %d LIMIT 1", $bookingId));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $selfCancelHours = (int) Config::get('customer_self_cancel_hours', 0);
        $checkInRaw      = $item ? (string) $item->check_in : (string) $booking->created_at;
        $checkInTime     = strtotime($checkInRaw);
        $now             = time();

        $canSelfCancel = false;
        if ($selfCancelHours > 0
            && in_array($booking->booking_status, ['pending', 'confirmed'], true)
            && ($booking->payment_status !== 'paid' || (float) $booking->total_amount <= 0.0)
        ) {
            $cutoff = $checkInTime - ($selfCancelHours * 3600);
            if ($now <= $cutoff) {
                $canSelfCancel = true;
            }
        }

        // 1. Direct Self-Cancellation Mode
        if ($canSelfCancel) {
            $bookingService = Container::getInstance()->get(BookingService::class);
            $changeRes = $bookingService->changeStatus($bookingId, 'cancelled', [
                'source' => 'customer',
                'reason' => $reason,
            ]);

            if ($changeRes['success']) {
                return [
                    'success' => true,
                    'mode'    => 'cancelled',
                    'message' => __('Your reservation has been successfully cancelled and inventory released.', 'tourivo'),
                ];
            }

            return [
                'success' => false,
                'code'    => 500,
                'message' => $changeRes['message'] ?: __('Could not cancel reservation.', 'tourivo'),
            ];
        }

        // 2. Cancellation Request Mode (Outside window, or Paid booking, or request-only mode)
        // A request already on file within the last 24h is acknowledged without re-alerting the administrator.
        if (!empty($booking->cancel_requested_at) && strtotime((string) $booking->cancel_requested_at . ' UTC') > (time() - 86400)) {
            return [
                'success' => true,
                'mode'    => 'requested',
                'message' => __('Your cancellation request has already been received. Our team will review and process it shortly.', 'tourivo'),
            ];
        }

        $nowGmt = current_time('mysql', 1);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $bookingsTable,
            ['cancel_requested_at' => $nowGmt],
            ['id' => $bookingId],
            ['%s'],
            ['%d']
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        // Audit log in LogService
        LogService::log(
            $bookingId,
            'cancel_requested',
            sprintf(
                /* translators: %s: Reason for cancellation */
                __('Traveler submitted cancellation request via lookup portal. Reason: %s', 'tourivo'),
                $reason ?: __('No reason provided', 'tourivo')
            ),
            0
        );

        // Notify Administrator of Cancellation Request
        $emailService = Container::getInstance()->get(EmailService::class);
        $adminEmail   = $emailService->getAdminEmail();
        if (!empty($adminEmail) && is_email($adminEmail)) {
            $bookingData = [
                'id'                  => $bookingId,
                'booking_code'        => (string) $booking->booking_code,
                'customer_name'       => (string) $booking->customer_name,
                'customer_email'      => (string) $booking->customer_email,
                'customer_phone'      => (string) $booking->customer_phone,
                'item_title'          => $item ? (string) $item->item_title : __('Tour / Room', 'tourivo'),
                'total_amount'        => Money::format((float) $booking->total_amount),
                'cancellation_reason' => $reason ?: __('No reason specified', 'tourivo'),
            ];
            // Enqueue admin alert
            $emailService->enqueue('admin_booking_cancelled', $adminEmail, $bookingData);
        }

        do_action('tourivo/booking_cancel_requested', $bookingId, $reason);

        return [
            'success' => true,
            'mode'    => 'requested',
            'message' => __('Your cancellation request has been received. Our team will review and process your request shortly.', 'tourivo'),
        ];
    }

    /**
     * Format booking record and items for lookup AJAX JSON response.
     *
     * @param object $booking
     * @param array<object> $items
     * @return array<string, mixed>
     */
    public static function formatLookupResponseData(object $booking, array $items): array
    {
        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');
        $voucherToken   = self::generateVoucherToken((int) $booking->id, (string) $booking->customer_email, $booking->access_key ?? null);
        $voucherUrl     = add_query_arg([
            'action' => 'tourivo_print_voucher',
            'code'   => $booking->booking_code,
            'token'  => $voucherToken,
        ], admin_url('admin-post.php'));

        $statusColors = [
            'confirmed' => '#10b981',
            'completed' => '#059669',
            'pending'   => '#f59e0b',
            'on_hold'   => '#6366f1',
            'cancelled' => '#ef4444',
        ];

        $paymentColors = [
            'paid'     => '#10b981',
            'unpaid'   => '#f59e0b',
            'refunded' => '#64748b',
            'failed'   => '#ef4444',
        ];

        $bookingStatus = (string) $booking->booking_status;
        $paymentStatus = (string) $booking->payment_status;

        $itemsHtml = '';
        foreach ($items as $item) {
            $checkInFormatted  = !empty($item->check_in) ? gmdate('M d, Y', strtotime((string) $item->check_in)) : '—';
            $checkOutFormatted = !empty($item->check_out) ? gmdate('M d, Y', strtotime((string) $item->check_out)) : '';
            $dateRange = $checkOutFormatted ? "{$checkInFormatted} → {$checkOutFormatted}" : $checkInFormatted;
            $typeLabel = ($item->item_type === 'room' || $item->item_type === 'hotel_room') ? esc_html__('Hotel Stay', 'tourivo') : esc_html__('Tour Package', 'tourivo');

            $itemsHtml .= '<div class="tourivo-lookup-item-row" style="padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 8px; border: 1px solid #e2e8f0;">';
            $itemsHtml .= '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 4px;">';
            $itemsHtml .= '<div><strong style="color:#0f172a; font-size:15px;">' . esc_html($item->item_title) . '</strong> <span style="display:inline-block; font-size:11px; padding:2px 6px; background:#e0e7ff; color:#3730a3; border-radius:4px; font-weight:600; margin-left:6px;">' . esc_html($typeLabel) . '</span></div>';
            $itemsHtml .= '<div style="font-weight:700; color:#0f172a;">' . esc_html(Money::format((float) $item->total_price, $currencySymbol)) . '</div>';
            $itemsHtml .= '</div>';
            $itemsHtml .= '<div style="font-size:13px; color:#64748b;">';
            $itemsHtml .= '📅 <strong>' . esc_html__('Dates:', 'tourivo') . '</strong> ' . esc_html($dateRange) . ' &bull; ';
            $itemsHtml .= '👥 <strong>' . esc_html__('Qty / Pax:', 'tourivo') . '</strong> ' . esc_html((string) $item->quantity);
            $itemsHtml .= '</div>';
            $itemsHtml .= '</div>';
        }

        $createdFormatted = gmdate('M d, Y H:i', strtotime((string) $booking->created_at));
        $cancelRequested  = !empty($booking->cancel_requested_at) && $bookingStatus !== 'cancelled';
        $allowCancel      = (bool) Config::get('allow_cancel_requests', true) && $bookingStatus !== 'cancelled';
        $selfCancelHours  = (int) Config::get('customer_self_cancel_hours', 0);

        return [
            'booking_code'     => (string) $booking->booking_code,
            'customer_name'    => (string) $booking->customer_name,
            'customer_email'   => (string) $booking->customer_email,
            'created_at'       => $createdFormatted,
            'booking_status'   => ucfirst($bookingStatus),
            'status_color'     => $statusColors[$bookingStatus] ?? '#64748b',
            'payment_status'   => ucfirst($paymentStatus),
            'payment_color'    => $paymentColors[$paymentStatus] ?? '#64748b',
            'payment_method'   => ucwords(str_replace('_', ' ', (string) $booking->payment_method)),
            'total_amount'     => Money::format((float) $booking->total_amount, $currencySymbol),
            'items_html'       => $itemsHtml,
            'voucher_url'      => esc_url_raw($voucherUrl),
            'cancel_requested' => $cancelRequested,
            'allow_cancel'     => $allowCancel,
            'self_cancel_hours'=> $selfCancelHours,
        ];
    }

    /**
     * Generate secure token for voucher download and verification.
     *
     * @param int $bookingId
     * @param string $email
     * @param string|null $accessKey Per-booking random secret. When present the token depends on it (not on the
     *                               guessable email) and dies as soon as the key is rotated; bookings created
     *                               before access keys existed keep the legacy email-derived token.
     * @return string
     */
    public static function generateVoucherToken(int $bookingId, string $email, ?string $accessKey = null): string
    {
        $salt = wp_salt('nonce');

        if ($accessKey !== null && $accessKey !== '') {
            return hash_hmac('sha256', "tourivo_voucher_{$bookingId}_key_{$accessKey}", $salt);
        }

        return hash_hmac('sha256', "tourivo_voucher_{$bookingId}_{$email}", $salt);
    }
}
