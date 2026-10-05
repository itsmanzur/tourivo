<?php

declare(strict_types=1);

namespace Tourivo\Support;

use Tourivo\Config\Config;
use Tourivo\Services\LogService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Privacy
 *
 * Handles GDPR / Privacy compliance, user consent versioning,
 * WordPress Personal Data Exporters, Erasers, and Privacy Policy guide.
 *
 * @package Tourivo\Support
 */
class Privacy
{
    /**
     * Get the configured or default Privacy Policy URL.
     *
     * @return string
     */
    public static function getPrivacyPolicyUrl(): string
    {
        $pageId = (int) Config::get('privacy_policy_page_id', 0);
        if ($pageId > 0 && function_exists('get_permalink')) {
            $url = (string) get_permalink($pageId);
            if (!empty($url)) {
                return $url;
            }
        }

        if (function_exists('get_privacy_policy_url')) {
            return (string) get_privacy_policy_url();
        }

        return '';
    }

    /**
     * Get the configured Terms & Conditions page URL.
     *
     * @return string
     */
    public static function getTermsUrl(): string
    {
        $pageId = (int) Config::get('terms_page_id', 0);
        if ($pageId > 0 && function_exists('get_permalink')) {
            return (string) get_permalink($pageId);
        }

        return '';
    }

    /**
     * Compute a deterministic 64-character SHA-256 hash representing the consent version.
     *
     * @return string
     */
    public static function getConsentVersion(): string
    {
        $label      = (string) Config::get('consent_label', 'I agree to the {privacy} and {terms}');
        $privacyUrl = self::getPrivacyPolicyUrl();
        $termsUrl   = self::getTermsUrl();

        return hash('sha256', $label . '|' . $privacyUrl . '|' . $termsUrl);
    }

    /**
     * Render the consent label with accessible HTML links for placeholders.
     *
     * @return string
     */
    public static function getRenderedConsentLabel(): string
    {
        $label      = (string) Config::get('consent_label', __('I agree to the {privacy} and {terms}', 'tourivo'));
        $privacyUrl = self::getPrivacyPolicyUrl();
        $termsUrl   = self::getTermsUrl();

        $privacyLink = !empty($privacyUrl)
            ? '<a href="' . esc_url($privacyUrl) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Privacy Policy', 'tourivo') . '</a>'
            : esc_html__('Privacy Policy', 'tourivo');

        $termsLink = !empty($termsUrl)
            ? '<a href="' . esc_url($termsUrl) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Terms and Conditions', 'tourivo') . '</a>'
            : esc_html__('Terms and Conditions', 'tourivo');

        $rendered = str_replace(
            ['{privacy}', '{terms}'],
            [$privacyLink, $termsLink],
            $label
        );

        return wp_kses_post($rendered);
    }

    /**
     * Register core WordPress privacy hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        if (function_exists('add_filter')) {
            add_filter('wp_privacy_personal_data_exporters', [self::class, 'registerExporter']);
            add_filter('wp_privacy_personal_data_erasers', [self::class, 'registerEraser']);
        }

        if (function_exists('add_action')) {
            add_action('admin_init', [self::class, 'addPrivacyPolicyGuide']);
        }
    }

    /**
     * Hook into WordPress Personal Data Exporters.
     *
     * @param array<string, mixed> $exporters
     * @return array<string, mixed>
     */
    public static function registerExporter(array $exporters): array
    {
        $exporters['tourivo'] = [
            'exporter_friendly_name' => __('Tourivo Bookings & Inquiries', 'tourivo'),
            'callback'               => [self::class, 'exportPersonalData'],
        ];

        return $exporters;
    }

    /**
     * Hook into WordPress Personal Data Erasers.
     *
     * @param array<string, mixed> $erasers
     * @return array<string, mixed>
     */
    public static function registerEraser(array $erasers): array
    {
        $erasers['tourivo'] = [
            'eraser_friendly_name' => __('Tourivo Personal Data Eraser', 'tourivo'),
            'callback'             => [self::class, 'erasePersonalData'],
        ];

        return $erasers;
    }

    /**
     * Add Tourivo privacy policy suggestions to the WordPress privacy guide.
     *
     * @return void
     */
    public static function addPrivacyPolicyGuide(): void
    {
        if (function_exists('wp_add_privacy_policy_content')) {
            wp_add_privacy_policy_content(
                __('Tourivo', 'tourivo'),
                wp_kses_post(self::getPrivacyPolicyContent())
            );
        }
    }

    /**
     * Generate standard privacy policy documentation content.
     *
     * @return string
     */
    public static function getPrivacyPolicyContent(): string
    {
        $webhookUrl = trim((string) Config::get('webhook_url', ''));

        $output = '<div class="wp-suggested-text">';
        $output .= '<h3>' . esc_html__('Personal Data Collected by Tourivo', 'tourivo') . '</h3>';
        $output .= '<p>' . esc_html__('When travelers book a tour or submit a trip inquiry through Tourivo, we collect the following personal information: customer name, email address, phone number, billing address, and special customer requests. If IP address collection is enabled, IP addresses are logged alongside bookings for fraud detection and security auditing.', 'tourivo') . '</p>';

        $output .= '<h3>' . esc_html__('Purpose of Data Processing', 'tourivo') . '</h3>';
        $output .= '<p>' . esc_html__('Personal data is collected strictly to process travel reservations, coordinate room and tour availability, verify customer identity for booking lookups, generate booking vouchers and invoices, and deliver booking confirmation and reminder emails.', 'tourivo') . '</p>';

        $output .= '<h3>' . esc_html__('Data Retention & Anonymization', 'tourivo') . '</h3>';
        $output .= '<p>' . esc_html__('Booking financial records (order totals, taxes, transaction IDs, and line items) are retained for statutory accounting and taxation compliance. When an erasure request is executed or retention schedules expire, personal identifiers (name, email, phone, billing address, IP address, and notes) are permanently erased and replaced with anonymized placeholders.', 'tourivo') . '</p>';

        $output .= '<h3>' . esc_html__('Cookies & Local Browser Storage', 'tourivo') . '</h3>';
        $output .= '<p>' . esc_html__('Tourivo utilizes browser local storage (localStorage) exclusively for client-side user experience enhancements, including favorite wishlist tours and selected currency preferences. No sensitive personal information is stored in client cookies.', 'tourivo') . '</p>';

        $output .= '<h3>' . esc_html__('Security & Rate Limiting', 'tourivo') . '</h3>';
        $output .= '<p>' . esc_html__('To protect our booking endpoints against spam, automated abuse, and brute-force attempts, Tourivo stores transient cryptographic hash representations of client IP addresses. These security transients expire automatically within short windows (10 minutes).', 'tourivo') . '</p>';

        if (!empty($webhookUrl)) {
            $parsedHost = parse_url($webhookUrl, PHP_URL_HOST);
            $targetHost = $parsedHost ? esc_html($parsedHost) : esc_html($webhookUrl);

            $output .= '<h3>' . esc_html__('External Webhook Automations', 'tourivo') . '</h3>';
            $output .= '<p>' . sprintf(
                /* translators: 1: webhook endpoint domain */
                esc_html__('This site is configured to transmit booking and inquiry event payloads to an external automated endpoint (%1$s) via secure HTTPS webhooks for third-party CRM and calendar synchronization.', 'tourivo'),
                esc_url($webhookUrl)
            ) . '</p>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Export personal data for WordPress Core Privacy Data Exporter tool.
     *
     * @param string $emailAddress
     * @param int    $page
     * @return array{data: array<int, array<string, mixed>>, done: bool}
     */
    public static function exportPersonalData(string $emailAddress, int $page = 1): array
    {
        global $wpdb;

        $emailAddress = sanitize_email($emailAddress);
        $number       = 50;
        $offset       = ($page - 1) * $number;

        $bookingsTable  = $wpdb->prefix . 'tourivo_bookings';
        $inquiriesTable = $wpdb->prefix . 'tourivo_inquiries';

        $totalBookings = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookingsTable} WHERE customer_email = %s",
            $emailAddress
        ));

        $totalInquiries = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$inquiriesTable} WHERE customer_email = %s",
            $emailAddress
        ));

        $totalRecords = $totalBookings + $totalInquiries;
        $data         = [];

        // 1. Fetch Bookings for this page range
        $bookingsToFetch = 0;
        $bookingOffset   = 0;

        if ($offset < $totalBookings) {
            $bookingOffset   = $offset;
            $bookingsToFetch = min($number, $totalBookings - $offset);

            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$bookingsTable} WHERE customer_email = %s ORDER BY id ASC LIMIT %d OFFSET %d",
                $emailAddress,
                $bookingsToFetch,
                $bookingOffset
            ));

            foreach ($bookings as $booking) {
                $data[] = [
                    'group_id'    => 'tourivo-bookings',
                    'group_label' => __('Tourivo Bookings', 'tourivo'),
                    'item_id'     => 'tourivo-booking-' . $booking->id,
                    'data'        => [
                        ['name' => __('Booking Code', 'tourivo'), 'value' => (string) ($booking->booking_code ?? '')],
                        ['name' => __('Customer Name', 'tourivo'), 'value' => (string) ($booking->customer_name ?? '')],
                        ['name' => __('Email Address', 'tourivo'), 'value' => (string) ($booking->customer_email ?? '')],
                        ['name' => __('Phone Number', 'tourivo'), 'value' => (string) ($booking->customer_phone ?? '')],
                        ['name' => __('Billing Address', 'tourivo'), 'value' => (string) ($booking->billing_address ?? '')],
                        ['name' => __('Total Amount', 'tourivo'), 'value' => (string) ($booking->total_amount ?? '0.00') . ' ' . (string) ($booking->currency ?? 'USD')],
                        ['name' => __('Booking Status', 'tourivo'), 'value' => (string) ($booking->booking_status ?? '')],
                        ['name' => __('Payment Status', 'tourivo'), 'value' => (string) ($booking->payment_status ?? '')],
                        ['name' => __('Customer Notes', 'tourivo'), 'value' => (string) ($booking->customer_notes ?? '')],
                        ['name' => __('IP Address', 'tourivo'), 'value' => (string) ($booking->ip_address ?? '')],
                        ['name' => __('Consent Given At', 'tourivo'), 'value' => (string) ($booking->consent_at ?? '')],
                        ['name' => __('Created Date', 'tourivo'), 'value' => (string) ($booking->created_at ?? '')],
                    ],
                ];
            }
        }

        // 2. Fetch Inquiries if space remains in this page batch
        $remainingSlots = $number - count($data);
        if ($remainingSlots > 0 && ($offset + count($data)) < $totalRecords) {
            $inquiryOffset = max(0, $offset - $totalBookings);

            $inquiries = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$inquiriesTable} WHERE customer_email = %s ORDER BY id ASC LIMIT %d OFFSET %d",
                $emailAddress,
                $remainingSlots,
                $inquiryOffset
            ));

            foreach ($inquiries as $inquiry) {
                $data[] = [
                    'group_id'    => 'tourivo-inquiries',
                    'group_label' => __('Tourivo Inquiries', 'tourivo'),
                    'item_id'     => 'tourivo-inquiry-' . $inquiry->id,
                    'data'        => [
                        ['name' => __('Inquirer Name', 'tourivo'), 'value' => (string) ($inquiry->customer_name ?? '')],
                        ['name' => __('Email Address', 'tourivo'), 'value' => (string) ($inquiry->customer_email ?? '')],
                        ['name' => __('Phone Number', 'tourivo'), 'value' => (string) ($inquiry->customer_phone ?? '')],
                        ['name' => __('Message', 'tourivo'), 'value' => (string) ($inquiry->message ?? '')],
                        ['name' => __('IP Address', 'tourivo'), 'value' => (string) ($inquiry->ip_address ?? '')],
                        ['name' => __('Consent Given At', 'tourivo'), 'value' => (string) ($inquiry->consent_at ?? '')],
                        ['name' => __('Submitted At', 'tourivo'), 'value' => (string) ($inquiry->created_at ?? '')],
                    ],
                ];
            }
        }

        $done = ($offset + count($data)) >= $totalRecords;

        return [
            'data' => $data,
            'done' => $done,
        ];
    }

    /**
     * Erase personal data for WordPress Core Personal Data Eraser tool.
     *
     * In accordance with tax, accounting, and financial reporting regulations,
     * financial booking records are anonymized rather than deleted, while
     * inquiries and user wishlist preferences are purged.
     *
     * @param string $emailAddress
     * @param int    $page
     * @return array{items_removed: bool|int, items_retained: bool, messages: array<int, string>, done: bool}
     */
    public static function erasePersonalData(string $emailAddress, int $page = 1): array
    {
        global $wpdb;

        $emailAddress   = sanitize_email($emailAddress);
        $bookingsTable  = $wpdb->prefix . 'tourivo_bookings';
        $inquiriesTable = $wpdb->prefix . 'tourivo_inquiries';

        // 1. Fetch matching bookings to anonymize
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT id, booking_code FROM {$bookingsTable} WHERE customer_email = %s",
            $emailAddress
        ));

        $anonymizedCount = 0;
        foreach ($bookings as $b) {
            $anonEmail = 'anon-' . substr(md5((string)$b->id . $emailAddress), 0, 12) . '@anonymized.invalid';

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
                ['id' => $b->id]
            );

            $anonymizedCount++;
        }

        // 2. Delete all inquiries associated with this email
        $inquiriesCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$inquiriesTable} WHERE customer_email = %s",
            $emailAddress
        ));

        if ($inquiriesCount > 0) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$inquiriesTable} WHERE customer_email = %s",
                $emailAddress
            ));
        }

        // 3. Purge wishlist meta for any WP user associated with this email
        if (function_exists('get_user_by')) {
            $user = get_user_by('email', $emailAddress);
            if ($user && isset($user->ID)) {
                if (function_exists('delete_user_meta')) {
                    delete_user_meta((int) $user->ID, '_tourivo_wishlist');
                }
            }
        }

        // 4. Record audit log without storing any PII
        LogService::log(
            0,
            'gdpr_erasure',
            sprintf('Personal data erasure executed for customer; %d booking(s) anonymized and %d inquiry/inquiries removed.', $anonymizedCount, $inquiriesCount),
            0
        );

        $messages = [];
        if ($anonymizedCount > 0) {
            $messages[] = __('Financial booking records have been anonymized and retained for legal and tax accounting obligations.', 'tourivo');
        }

        return [
            'items_removed'  => $inquiriesCount,
            'items_retained' => true,
            'messages'       => $messages,
            'done'           => true,
        ];
    }
}
