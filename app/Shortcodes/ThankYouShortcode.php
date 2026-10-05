<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

use Tourivo\Config\Config;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ThankYouShortcode
 *
 * Renders the post-booking confirmation & thank-you page:
 * - Security: HMAC signed token validation (purpose: thankyou, 72-hour validity, no PII in URL)
 * - Cache busting: DONOTCACHEPAGE and nocache_headers()
 * - Summary: Reservation details, status badge, pricing breakdown
 * - Offline Payment Instructions box (if configured)
 * - Actions: Print voucher, Add to calendar (.ics), Track reservation
 * - Conversion tracking: Optional GTM dataLayer event push (PII-free) with sessionStorage guard
 *
 * Shortcode: [tourivo_thank_you]
 *
 * @package Tourivo\Shortcodes
 */
class ThankYouShortcode
{
    /**
     * Register shortcode handler.
     *
     * @return void
     */
    public static function register(): void
    {
        add_shortcode('tourivo_thank_you', [self::class, 'render']);
    }

    /**
     * Generate secure, time-bound HMAC token for thank-you page access.
     *
     * @param int      $bookingId
     * @param int|null $issuedAt
     * @return string
     */
    public static function generateThankYouToken(int $bookingId, ?int $issuedAt = null): string
    {
        $issuedAt = $issuedAt ?? time();
        $salt     = wp_salt('nonce');
        $hash     = hash_hmac('sha256', "tourivo_thankyou_{$bookingId}_{$issuedAt}", $salt);

        return "{$issuedAt}.{$hash}";
    }

    /**
     * Verify whether the provided thank-you token is authentic and within the 72-hour validity window.
     *
     * @param int    $bookingId
     * @param string $token
     * @return bool
     */
    public static function verifyThankYouToken(int $bookingId, string $token): bool
    {
        if (empty($token) || !str_contains($token, '.')) {
            return false;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $issuedAt = (int) $parts[0];
        $hash     = (string) $parts[1];
        $now      = time();

        // 72 hours validity (72 * 3600 = 259200 seconds), allow 5 minutes future clock skew
        if ($issuedAt <= 0 || ($now - $issuedAt) > 259200 || $issuedAt > ($now + 300)) {
            return false;
        }

        $salt         = wp_salt('nonce');
        $expectedHash = hash_hmac('sha256', "tourivo_thankyou_{$bookingId}_{$issuedAt}", $salt);

        return hash_equals($expectedHash, $hash);
    }

    /**
     * Render the Thank You page.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public static function render(array|string $atts = []): string
    {
        // 1. Prevent search indexing and intermediate proxy/CDN caching
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $bookingCode = isset($_GET['code']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_GET['code']))) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token       = isset($_GET['token']) ? sanitize_text_field(wp_unslash((string) $_GET['token'])) : '';

        $lookupPageId = (int) Config::get('lookup_page_id', 0);
        $lookupUrl    = $lookupPageId > 0 ? get_permalink($lookupPageId) : home_url('/');

        if (empty($bookingCode) || empty($token)) {
            return self::renderInvalidTokenState(__('Missing booking reference or security token.', 'tourivo'), $lookupUrl);
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE booking_code = %s LIMIT 1",
            $bookingCode
        ));

        if (!$booking) {
            return self::renderInvalidTokenState(__('No reservation was found matching this reference code.', 'tourivo'), $lookupUrl);
        }

        // Verify signed token
        if (!self::verifyThankYouToken((int) $booking->id, $token)) {
            return self::renderInvalidTokenState(__('We could not securely verify your booking confirmation link or the link has expired (links remain active for 72 hours).', 'tourivo'), $lookupUrl);
        }

        $items = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$itemsTable} WHERE booking_id = %d",
            $booking->id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        // Fire thank you viewed hook for conversion integrations & audit
        do_action('tourivo/thankyou_viewed', (int) $booking->id);

        $voucherToken = BookingLookupShortcode::generateVoucherToken((int) $booking->id, (string) $booking->customer_email);
        $voucherUrl   = add_query_arg([
            'action' => 'tourivo_print_voucher',
            'code'   => $booking->booking_code,
            'token'  => $voucherToken,
        ], admin_url('admin-post.php'));

        $icsUrl = add_query_arg([
            'action' => 'tourivo_download_ics',
            'code'   => $booking->booking_code,
            'token'  => $token,
        ], admin_url('admin-post.php'));

        $offlinePaymentInstructions = (string) Config::get('offline_payment_instructions', '');
        $enableDataLayer           = (bool) Config::get('enable_datalayer', false);
        $primaryColor              = (string) Config::get('primary_color', '#0d9488');

        ob_start();
        tourivo_get_template('thank-you.php', [
            'booking'                    => $booking,
            'items'                      => $items,
            'voucherUrl'                 => $voucherUrl,
            'icsUrl'                     => $icsUrl,
            'lookupUrl'                  => $lookupUrl,
            'offlinePaymentInstructions' => $offlinePaymentInstructions,
            'enableDataLayer'            => $enableDataLayer,
            'primaryColor'               => $primaryColor,
        ]);

        return ob_get_clean() ?: '';
    }

    /**
     * Render a polite error card when security token is invalid or expired.
     *
     * @param string $message
     * @param string $lookupUrl
     * @return string
     */
    protected static function renderInvalidTokenState(string $message, string $lookupUrl): string
    {
        ob_start();
        ?>
        <div class="tourivo-thankyou-wrapper" style="max-width: 680px; margin: 40px auto; padding: 32px 24px; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); font-family: inherit;">
            <div style="font-size: 48px; margin-bottom: 12px;">🔒</div>
            <h3 style="margin: 0 0 8px; color: #1e293b; font-size: 22px; font-weight: 700;"><?php esc_html_e('Unable to Display Booking Details', 'tourivo'); ?></h3>
            <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin: 0 0 24px;">
                <?php echo esc_html($message); ?>
            </p>
            <p style="color: #475569; font-size: 13px; margin: 0 0 20px;">
                <?php esc_html_e('You can view and manage your reservation anytime by entering your booking reference code and email on our self-service portal:', 'tourivo'); ?>
            </p>
            <a href="<?php echo esc_url($lookupUrl); ?>" class="button button-primary" style="display: inline-block; background: #0d9488; color: #ffffff; padding: 10px 22px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;">
                🔍 <?php esc_html_e('Track My Booking on Lookup Portal', 'tourivo'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Generate RFC 5545 iCalendar (.ics) string for a booking.
     *
     * @param int $bookingId
     * @return string
     */
    public static function generateIcsContent(int $bookingId): string
    {
        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bookingsTable} WHERE id = %d", $bookingId));
        if (!$booking) {
            return '';
        }

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$itemsTable} WHERE booking_id = %d LIMIT 1", $bookingId));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $siteName = get_bloginfo('name') ?: 'Tourivo';
        $host     = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'tourivo.local';
        $title    = $item ? (string) $item->item_title : __('Tour / Hotel Reservation', 'tourivo');
        $code     = (string) $booking->booking_code;
        $uid      = "tourivo-booking-{$bookingId}-{$code}@{$host}";
        $nowStamp = gmdate('Ymd\THis\Z');

        $checkInRaw  = $item ? (string) $item->check_in : (string) $booking->created_at;
        $checkOutRaw = ($item && !empty($item->check_out)) ? (string) $item->check_out : $checkInRaw;

        $dtStart = gmdate('Ymd', strtotime($checkInRaw));
        // For iCal full-day event, DTEND is non-inclusive day after end date
        $dtEnd = gmdate('Ymd', strtotime($checkOutRaw . ' +1 day'));

        $summary = sprintf(
            /* translators: 1: Item title, 2: Booking code */
            __('%1$s (Booking #%2$s)', 'tourivo'),
            $title,
            $code
        );

        $description = sprintf(
            /* translators: 1: Booking reference code, 2: Customer name, 3: Total amount, 4: Site name */
            __('Reservation Reference: #%1$s\nTraveler: %2$s\nTotal: %3$s\nOrganized by: %4$s', 'tourivo'),
            $code,
            $booking->customer_name,
            Money::format((float) $booking->total_amount),
            $siteName
        );

        // Escape iCalendar text
        $summaryEsc     = self::escapeIcsText($summary);
        $descriptionEsc = self::escapeIcsText($description);
        $locationEsc    = self::escapeIcsText((string) home_url('/'));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Tourivo//Tourivo Booking System//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$nowStamp}",
            "DTSTART;VALUE=DATE:{$dtStart}",
            "DTEND;VALUE=DATE:{$dtEnd}",
            "SUMMARY:{$summaryEsc}",
            "DESCRIPTION:{$descriptionEsc}",
            "LOCATION:{$locationEsc}",
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Escape special characters in iCalendar text fields according to RFC 5545.
     *
     * @param string $text
     * @return string
     */
    protected static function escapeIcsText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(["\r\n", "\n", "\r"], '\n', $text);

        return $text;
    }
}
