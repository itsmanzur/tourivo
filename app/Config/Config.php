<?php

declare(strict_types=1);

namespace Tourivo\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Config
 *
 * Provides central access to default settings, currency definitions, and options.
 *
 * @package Tourivo\Config
 */
class Config
{
    /**
     * Get default plugin options.
     *
     * @return array<string, mixed>
     */
    public static function getDefaults(): array
    {
        return self::$defaultsCache ??= self::buildDefaults();
    }

    /**
     * Forget the per-request defaults cache (after the site email/name changes, and in tests).
     */
    public static function flushDefaults(): void
    {
        self::$defaultsCache = null;
    }

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $defaultsCache = null;

    /**
     * @return array<string, mixed>
     */
    private static function buildDefaults(): array
    {
        $siteEmail = (string) get_option('admin_email', 'admin@example.com');
        if (empty($siteEmail) || !is_email($siteEmail)) {
            $siteEmail = 'admin@example.com';
        }

        return [
            'currency'              => 'USD',
            'currency_symbol'       => '$',
            'currency_position'     => 'left', // left, right, left_space, right_space
            'decimal_separator'     => '.',
            'thousand_separator'    => ',',
            'number_of_decimals'    => 2,
            'default_booking_status'=> 'pending',
            'enable_reviews'        => true,
            'auto_approve_reviews'  => false,
            'email_from_name'       => get_bloginfo('name') ?: 'Tourivo',
            'email_from_address'    => $siteEmail,
            'email_notification_address' => $siteEmail,
            'primary_color'         => '#0d9488',
            'primary_hover'         => '#0f766e',
            'accent_color'          => '#f59e0b',
            'border_radius'         => '8px',
            'button_text_color'     => '#ffffff',
            'webhook_url'           => '',
            'webhook_secret'        => '',
            'webhook_events'        => ['booking.created', 'booking.status_changed', 'inquiry.created'],
            'plugin_language'       => 'default', // 'default' (WP site default), 'en' (English), 'bn' (বাংলা)
            'use_bangla_digits'     => false,
            'tax_enabled'           => false,
            'tax_label'             => 'Tax',
            'tax_rate'              => 0.0,
            'tax_mode'              => 'exclusive', // 'exclusive' or 'inclusive'
            'tax_applies_to'        => 'all',       // 'all', 'tours', 'rooms'
            'reminder_days_before'  => 2,
            'lookup_page_id'        => 0,
            'thankyou_page_id'      => 0,
            'redirect_after_booking'=> 'inline', // 'inline' or 'thankyou'
            'enable_datalayer'      => false,
            'pending_expiry_hours'  => 0, // 0 = pending bookings never auto-expire
            'require_email_verification' => false,
            'unverified_expiry_minutes'  => 60,
            'allow_cancel_requests' => true,
            'customer_self_cancel_hours' => 0, // 0 = disabled (request-only)
            'offline_payment_instructions' => '',
            // 1. Customer: Booking Received (Pending)
            'email_customer_booking_received_enabled'            => true,
            'email_customer_booking_received_subject'            => 'Your Booking Request Received #{booking_code} - {site_name}',
            'email_customer_booking_received_heading'            => 'Booking Request Received',
            'email_customer_booking_received_additional_content' => '',
            // 2. Customer: Booking Confirmed
            'email_customer_booking_confirmed_enabled'           => true,
            'email_customer_booking_confirmed_subject'           => 'Your Booking Confirmation #{booking_code} - {site_name}',
            'email_customer_booking_confirmed_heading'           => 'Booking Confirmed!',
            'email_customer_booking_confirmed_additional_content'=> '',
            // 2b. Customer: Confirm Your Email (only when email verification is required)
            'email_customer_verify_email_enabled'                => true,
            'email_customer_verify_email_subject'                => 'Please confirm your booking #{booking_code} - {site_name}',
            'email_customer_verify_email_heading'                => 'Confirm Your Email Address',
            'email_customer_verify_email_additional_content'     => '',
            // 3. Customer: Booking Cancelled
            'email_customer_booking_cancelled_enabled'           => true,
            'email_customer_booking_cancelled_subject'           => 'Your Booking #{booking_code} has been Cancelled - {site_name}',
            'email_customer_booking_cancelled_heading'           => 'Booking Cancelled',
            'email_customer_booking_cancelled_additional_content'=> '',
            // 4. Customer: Trip Reminder
            'email_customer_trip_reminder_enabled'               => true,
            'email_customer_trip_reminder_subject'               => 'Upcoming Trip Reminder: #{booking_code} - {site_name}',
            'email_customer_trip_reminder_heading'               => 'Your Trip is Coming Up Soon!',
            'email_customer_trip_reminder_additional_content'    => '',
            // 5. Admin: New Booking Alert
            'email_admin_new_booking_enabled'                    => true,
            'email_admin_new_booking_subject'                    => '[New Booking] #{booking_code} by {customer_name}',
            'email_admin_new_booking_heading'                    => 'New Booking Received',
            'email_admin_new_booking_additional_content'         => '',
            // 6. Admin: Booking Cancelled Alert
            'email_admin_booking_cancelled_enabled'              => true,
            'email_admin_booking_cancelled_subject'              => '[Booking Cancelled] #{booking_code} - {customer_name}',
            'email_admin_booking_cancelled_heading'              => 'Booking Cancelled Notification',
            'email_admin_booking_cancelled_additional_content'   => '',
            // 7. Admin: New Inquiry Alert
            'email_admin_new_inquiry_enabled'                    => true,
            'email_admin_new_inquiry_subject'                    => '[New Inquiry] {item_title} from {customer_name}',
            'email_admin_new_inquiry_heading'                    => 'New Traveler Trip Inquiry',
            'email_admin_new_inquiry_additional_content'         => '',
            // Privacy & GDPR Compliance
            'require_consent'                                    => false,
            'privacy_policy_page_id'                             => 0,
            'terms_page_id'                                      => 0,
            'consent_label'                                      => 'I agree to the {privacy} and {terms}',
            'store_ip'                                           => true,
            'anonymize_after_months'                             => 0,
            'delete_inquiries_after_months'                      => 0,
            'webhook_include_phone'                              => true,
            'webhook_include_message'                            => true,
        ];
    }

    /**
     * Retrieve a specific setting with fallback to default.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = get_option('tourivo_settings', []);
        if (isset($settings[$key])) {
            return $settings[$key];
        }

        $defaults = self::getDefaults();
        return $defaults[$key] ?? $default;
    }
}
