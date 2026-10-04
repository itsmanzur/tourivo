<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

use Tourivo\Support\ClientIp;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookingLookupShortcode
 *
 * Provides a frontend "Track My Booking" lookup portal.
 * Travelers can enter their Booking Reference Code and Email to check live status and print vouchers.
 *
 * Shortcode: [tourivo_booking_lookup]
 *
 * @package Tourivo\Shortcodes
 */
class BookingLookupShortcode
{
    public static function register(): void
    {
        add_shortcode('tourivo_booking_lookup', [self::class, 'render']);
        add_action('wp_ajax_tourivo_lookup_booking', [self::class, 'handleLookupAjax']);
        add_action('wp_ajax_nopriv_tourivo_lookup_booking', [self::class, 'handleLookupAjax']);
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
        $tourivoLookupTitle = sanitize_text_field((string) $attributes['title']);
        $tourivoLookupDesc  = sanitize_text_field((string) $attributes['description']);
        $tourivoNonce       = wp_create_nonce('tourivo_lookup_nonce');

        include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/booking-lookup.php';

        return ob_get_clean() ?: '';
    }

    /**
     * Handle AJAX lookup request with rate limiting and secure verification.
     *
     * @return void
     */
    public static function handleLookupAjax(): void
    {
        check_ajax_referer('tourivo_lookup_nonce', 'nonce');

        $ip = ClientIp::get();
        $rateLimitKey = 'trv_lookup_' . md5($ip);

        // Rate limiting: max 10 lookup attempts per 5 minutes per IP
        $attempts = (int) get_transient($rateLimitKey);
        if ($attempts >= 10) {
            wp_send_json_error([
                'message' => __('Too many lookup attempts. Please wait a few minutes before trying again.', 'tourivo'),
            ], 429);
        }
        set_transient($rateLimitKey, $attempts + 1, 300);

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

        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');
        $voucherToken   = self::generateVoucherToken((int) $booking->id, (string) $booking->customer_email);
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

        wp_send_json_success([
            'booking_code'    => esc_html($booking->booking_code),
            'customer_name'   => esc_html($booking->customer_name),
            'customer_email'  => esc_html($booking->customer_email),
            'created_at'      => esc_html($createdFormatted),
            'booking_status'  => esc_html(ucfirst($bookingStatus)),
            'status_color'    => $statusColors[$bookingStatus] ?? '#64748b',
            'payment_status'  => esc_html(ucfirst($paymentStatus)),
            'payment_color'   => $paymentColors[$paymentStatus] ?? '#64748b',
            'payment_method'  => esc_html(ucwords(str_replace('_', ' ', (string) $booking->payment_method))),
            'total_amount'    => esc_html(Money::format((float) $booking->total_amount, $currencySymbol)),
            'items_html'      => $itemsHtml,
            'voucher_url'     => esc_url($voucherUrl),
        ]);
    }

    /**
     * Generate secure token for voucher download and verification.
     *
     * @param int $bookingId
     * @param string $email
     * @return string
     */
    public static function generateVoucherToken(int $bookingId, string $email): string
    {
        $salt = wp_salt('nonce');
        return hash_hmac('sha256', "tourivo_voucher_{$bookingId}_{$email}", $salt);
    }
}
