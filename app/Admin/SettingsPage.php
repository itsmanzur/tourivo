<?php

declare(strict_types=1);

namespace Tourivo\Admin;

use Tourivo\Config\Config;
use Tourivo\Shortcodes\CurrencySwitcherShortcode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsPage
 *
 * Renders and saves Tourivo global settings with tabbed UI and test email dispatcher.
 *
 * @package Tourivo\Admin
 */
class SettingsPage
{
    /**
     * Render the settings page view.
     *
     * @return void
     */
    public static function render(): void
    {
        // Handle form save
        if (isset($_POST['tourivo_save_settings'])) {
            check_admin_referer('tourivo_settings_action', 'tourivo_settings_nonce');

            if (!current_user_can('manage_tourivo_settings')) {
                wp_die(esc_html__('Unauthorized access.', 'tourivo'));
            }

            $currentSettings = get_option('tourivo_settings', Config::getDefaults());

            $newSettings = [
                'currency'                => sanitize_text_field(wp_unslash($_POST['currency'] ?? 'USD')),
                'currency_symbol'         => sanitize_text_field(wp_unslash($_POST['currency_symbol'] ?? '$')),
                'currency_position'       => sanitize_text_field(wp_unslash($_POST['currency_position'] ?? 'left')),
                'plugin_language'         => in_array(sanitize_key(wp_unslash($_POST['plugin_language'] ?? 'default')), ['default', 'en', 'bn'], true) ? sanitize_key(wp_unslash($_POST['plugin_language'])) : 'default',
                'use_bangla_digits'       => isset($_POST['use_bangla_digits']) ? 1 : 0,
                'tax_enabled'             => isset($_POST['tax_enabled']) ? 1 : 0,
                'tax_label'               => sanitize_text_field(wp_unslash($_POST['tax_label'] ?? 'Tax')),
                'tax_rate'                => max(0.0, (float) sanitize_text_field(wp_unslash($_POST['tax_rate'] ?? '0'))),
                'tax_mode'                => in_array(sanitize_key(wp_unslash($_POST['tax_mode'] ?? 'exclusive')), ['exclusive', 'inclusive'], true) ? sanitize_key(wp_unslash($_POST['tax_mode'])) : 'exclusive',
                'tax_applies_to'          => in_array(sanitize_key(wp_unslash($_POST['tax_applies_to'] ?? 'all')), ['all', 'tours', 'rooms'], true) ? sanitize_key(wp_unslash($_POST['tax_applies_to'])) : 'all',
                'default_booking_status'     => sanitize_text_field(wp_unslash($_POST['default_booking_status'] ?? 'pending')),
                'redirect_after_booking'     => in_array(sanitize_key(wp_unslash($_POST['redirect_after_booking'] ?? 'inline')), ['inline', 'thankyou'], true) ? sanitize_key(wp_unslash($_POST['redirect_after_booking'])) : 'inline',
                'thankyou_page_id'           => absint($_POST['thankyou_page_id'] ?? 0),
                'enable_datalayer'           => isset($_POST['enable_datalayer']) ? 1 : 0,
                'allow_cancel_requests'      => isset($_POST['allow_cancel_requests']) ? 1 : 0,
                'customer_self_cancel_hours' => absint($_POST['customer_self_cancel_hours'] ?? 0),
                'email_from_name'            => sanitize_text_field(wp_unslash($_POST['email_from_name'] ?? get_bloginfo('name'))),
                'email_from_address'         => sanitize_email(wp_unslash($_POST['email_from_address'] ?? get_option('admin_email'))),
                'email_notification_address' => sanitize_email(wp_unslash($_POST['email_notification_address'] ?? get_option('admin_email'))),
                'reminder_days_before'       => max(0, min(30, (int) sanitize_text_field(wp_unslash($_POST['reminder_days_before'] ?? '2')))),
                'lookup_page_id'             => absint($_POST['lookup_page_id'] ?? 0),
                'offline_payment_instructions' => sanitize_textarea_field(wp_unslash($_POST['offline_payment_instructions'] ?? '')),
                // 1. Customer: Booking Received
                'email_customer_booking_received_enabled'            => isset($_POST['email_customer_booking_received_enabled']) ? 1 : 0,
                'email_customer_booking_received_subject'            => sanitize_text_field(wp_unslash($_POST['email_customer_booking_received_subject'] ?? '')),
                'email_customer_booking_received_heading'            => sanitize_text_field(wp_unslash($_POST['email_customer_booking_received_heading'] ?? '')),
                'email_customer_booking_received_additional_content' => wp_kses_post(wp_unslash($_POST['email_customer_booking_received_additional_content'] ?? '')),
                // 2. Customer: Booking Confirmed
                'email_customer_booking_confirmed_enabled'           => isset($_POST['email_customer_booking_confirmed_enabled']) ? 1 : 0,
                'email_customer_booking_confirmed_subject'           => sanitize_text_field(wp_unslash($_POST['email_customer_booking_confirmed_subject'] ?? '')),
                'email_customer_booking_confirmed_heading'           => sanitize_text_field(wp_unslash($_POST['email_customer_booking_confirmed_heading'] ?? '')),
                'email_customer_booking_confirmed_additional_content'=> wp_kses_post(wp_unslash($_POST['email_customer_booking_confirmed_additional_content'] ?? '')),
                // 3. Customer: Booking Cancelled
                'email_customer_booking_cancelled_enabled'           => isset($_POST['email_customer_booking_cancelled_enabled']) ? 1 : 0,
                'email_customer_booking_cancelled_subject'           => sanitize_text_field(wp_unslash($_POST['email_customer_booking_cancelled_subject'] ?? '')),
                'email_customer_booking_cancelled_heading'           => sanitize_text_field(wp_unslash($_POST['email_customer_booking_cancelled_heading'] ?? '')),
                'email_customer_booking_cancelled_additional_content'=> wp_kses_post(wp_unslash($_POST['email_customer_booking_cancelled_additional_content'] ?? '')),
                // 4. Customer: Trip Reminder
                'email_customer_trip_reminder_enabled'               => isset($_POST['email_customer_trip_reminder_enabled']) ? 1 : 0,
                'email_customer_trip_reminder_subject'               => sanitize_text_field(wp_unslash($_POST['email_customer_trip_reminder_subject'] ?? '')),
                'email_customer_trip_reminder_heading'               => sanitize_text_field(wp_unslash($_POST['email_customer_trip_reminder_heading'] ?? '')),
                'email_customer_trip_reminder_additional_content'    => wp_kses_post(wp_unslash($_POST['email_customer_trip_reminder_additional_content'] ?? '')),
                // 5. Admin: New Booking Alert
                'email_admin_new_booking_enabled'                    => isset($_POST['email_admin_new_booking_enabled']) ? 1 : 0,
                'email_admin_new_booking_subject'                    => sanitize_text_field(wp_unslash($_POST['email_admin_new_booking_subject'] ?? '')),
                'email_admin_new_booking_heading'                    => sanitize_text_field(wp_unslash($_POST['email_admin_new_booking_heading'] ?? '')),
                'email_admin_new_booking_additional_content'         => wp_kses_post(wp_unslash($_POST['email_admin_new_booking_additional_content'] ?? '')),
                // 6. Admin: Booking Cancelled Alert
                'email_admin_booking_cancelled_enabled'              => isset($_POST['email_admin_booking_cancelled_enabled']) ? 1 : 0,
                'email_admin_booking_cancelled_subject'              => sanitize_text_field(wp_unslash($_POST['email_admin_booking_cancelled_subject'] ?? '')),
                'email_admin_booking_cancelled_heading'              => sanitize_text_field(wp_unslash($_POST['email_admin_booking_cancelled_heading'] ?? '')),
                'email_admin_booking_cancelled_additional_content'   => wp_kses_post(wp_unslash($_POST['email_admin_booking_cancelled_additional_content'] ?? '')),
                // 7. Admin: New Inquiry Alert
                'email_admin_new_inquiry_enabled'                    => isset($_POST['email_admin_new_inquiry_enabled']) ? 1 : 0,
                'email_admin_new_inquiry_subject'                    => sanitize_text_field(wp_unslash($_POST['email_admin_new_inquiry_subject'] ?? '')),
                'email_admin_new_inquiry_heading'                    => sanitize_text_field(wp_unslash($_POST['email_admin_new_inquiry_heading'] ?? '')),
                'email_admin_new_inquiry_additional_content'         => wp_kses_post(wp_unslash($_POST['email_admin_new_inquiry_additional_content'] ?? '')),
                'primary_color'              => sanitize_hex_color(wp_unslash((string)($_POST['primary_color'] ?? '#0d9488'))) ?: '#0d9488',
                'primary_hover'              => sanitize_hex_color(wp_unslash((string)($_POST['primary_hover'] ?? '#0f766e'))) ?: '#0f766e',
                'accent_color'               => sanitize_hex_color(wp_unslash((string)($_POST['accent_color'] ?? '#f59e0b'))) ?: '#f59e0b',
                'border_radius'              => sanitize_text_field(wp_unslash((string)($_POST['border_radius'] ?? '8px'))),
                'button_text_color'          => sanitize_hex_color(wp_unslash((string)($_POST['button_text_color'] ?? '#ffffff'))) ?: '#ffffff',
                'enable_schema'              => isset($_POST['enable_schema']) ? 1 : 0,
                'enable_opengraph'           => isset($_POST['enable_opengraph']) ? 1 : 0,
                'webhook_url'                => esc_url_raw(wp_unslash($_POST['webhook_url'] ?? '')),
                'webhook_secret'             => sanitize_text_field(wp_unslash($_POST['webhook_secret'] ?? '')),
                'webhook_events'             => isset($_POST['webhook_events']) && is_array($_POST['webhook_events']) ? array_map('sanitize_text_field', wp_unslash($_POST['webhook_events'])) : [],
                'webhook_include_phone'      => isset($_POST['webhook_include_phone']) ? 1 : 0,
                'webhook_include_message'    => isset($_POST['webhook_include_message']) ? 1 : 0,
                'require_consent'            => isset($_POST['require_consent']) ? 1 : 0,
                'privacy_policy_page_id'     => absint($_POST['privacy_policy_page_id'] ?? 0),
                'terms_page_id'              => absint($_POST['terms_page_id'] ?? 0),
                'consent_label'              => sanitize_text_field(wp_unslash($_POST['consent_label'] ?? 'I agree to the {privacy} and {terms}')),
                'store_ip'                   => isset($_POST['store_ip']) ? 1 : 0,
                'anonymize_after_months'     => max(0, (int) sanitize_text_field(wp_unslash($_POST['anonymize_after_months'] ?? '0'))),
                'delete_inquiries_after_months' => max(0, (int) sanitize_text_field(wp_unslash($_POST['delete_inquiries_after_months'] ?? '0'))),
                'proxy_mode'                 => in_array(sanitize_text_field(wp_unslash($_POST['proxy_mode'] ?? '')), ['cloudflare', 'reverse_proxy'], true) ? sanitize_text_field(wp_unslash($_POST['proxy_mode'])) : 'disabled',
                'trusted_proxies'            => sanitize_textarea_field(wp_unslash($_POST['trusted_proxies'] ?? '')),
                'trust_proxy_headers'        => (isset($_POST['proxy_mode']) && in_array($_POST['proxy_mode'], ['cloudflare', 'reverse_proxy'], true)) ? 1 : 0,
                'erase_data_on_uninstall'    => isset($_POST['erase_data_on_uninstall']) ? 1 : 0,
            ];

            $merged = array_merge($currentSettings, $newSettings);
            update_option('tourivo_settings', $merged);

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'tourivo') . '</p></div>';
        }

        $currency          = Config::get('currency', 'USD');
        $currencySymbol    = Config::get('currency_symbol', '$');
        $currencyPos       = Config::get('currency_position', 'left');
        $pluginLanguage    = Config::get('plugin_language', 'default');
        $useBanglaDigits   = Config::get('use_bangla_digits', 0);
        $taxEnabled        = Config::get('tax_enabled', 0);
        $taxLabel          = Config::get('tax_label', 'Tax');
        $taxRate           = Config::get('tax_rate', 0.0);
        $taxMode           = Config::get('tax_mode', 'exclusive');
        $taxAppliesTo      = Config::get('tax_applies_to', 'all');
        $defaultStatus           = Config::get('default_booking_status', 'pending');
        $redirectAfterBooking    = Config::get('redirect_after_booking', 'inline');
        $thankyouPageId          = (int) Config::get('thankyou_page_id', 0);
        $enableDatalayer         = Config::get('enable_datalayer', 0);
        $allowCancelRequests     = Config::get('allow_cancel_requests', 1);
        $customerSelfCancelHours = (int) Config::get('customer_self_cancel_hours', 0);
        $fromName                = Config::get('email_from_name', get_bloginfo('name'));
        $fromEmail         = Config::get('email_from_address', get_option('admin_email'));
        $notifyEmail       = Config::get('email_notification_address', get_option('admin_email'));
        $reminderDays      = (int) Config::get('reminder_days_before', 2);
        $lookupPageId      = (int) Config::get('lookup_page_id', 0);
        $offlinePayment    = (string) Config::get('offline_payment_instructions', '');
        $requireConsent        = Config::get('require_consent', 0);
        $privacyPolicyPageId   = (int) Config::get('privacy_policy_page_id', 0);
        $termsPageId           = (int) Config::get('terms_page_id', 0);
        $consentLabel          = (string) Config::get('consent_label', 'I agree to the {privacy} and {terms}');
        $storeIp               = Config::get('store_ip', 1);
        $anonymizeAfterMonths  = (int) Config::get('anonymize_after_months', 0);
        $deleteInqAfterMonths  = (int) Config::get('delete_inquiries_after_months', 0);
        $webhookIncludePhone   = Config::get('webhook_include_phone', 1);
        $webhookIncludeMessage = Config::get('webhook_include_message', 1);
        $primaryColor      = Config::get('primary_color', '#0d9488');
        $primaryHover      = Config::get('primary_hover', '#0f766e');
        $accentColor       = Config::get('accent_color', '#f59e0b');
        $borderRadius      = Config::get('border_radius', '8px');
        $btnTextColor      = Config::get('button_text_color', '#ffffff');
        $enableSchema      = Config::get('enable_schema', 1);
        $enableOpenGraph   = Config::get('enable_opengraph', 1);
        $webhookUrl        = Config::get('webhook_url', '');
        $webhookSecret     = Config::get('webhook_secret', '');
        $webhookEvents     = (array) Config::get('webhook_events', ['booking.created', 'booking.status_changed', 'inquiry.created']);
        $proxyMode         = Config::get('proxy_mode', 'disabled');
        $trustedProxies    = Config::get('trusted_proxies', '');
        $eraseData         = Config::get('erase_data_on_uninstall', 0);
        $currencies        = CurrencySwitcherShortcode::getCurrencies();

        $emailTemplates = [
            'customer_booking_received' => [
                'title'       => __('Customer: Booking Request Received (Pending)', 'tourivo'),
                'recipient'   => __('Customer (Booker)', 'tourivo'),
                'desc'        => __('Sent to traveler immediately when a new booking is placed in Pending status.', 'tourivo'),
                'enabled'     => (bool) Config::get('email_customer_booking_received_enabled', true),
                'subject'     => (string) Config::get('email_customer_booking_received_subject', 'Your Booking Request Received #{booking_code} - {site_name}'),
                'heading'     => (string) Config::get('email_customer_booking_received_heading', 'Booking Request Received'),
                'content'     => (string) Config::get('email_customer_booking_received_additional_content', ''),
            ],
            'customer_booking_confirmed' => [
                'title'       => __('Customer: Booking Confirmed', 'tourivo'),
                'recipient'   => __('Customer (Booker)', 'tourivo'),
                'desc'        => __('Sent to traveler when a booking is confirmed. Includes printable voucher access.', 'tourivo'),
                'enabled'     => (bool) Config::get('email_customer_booking_confirmed_enabled', true),
                'subject'     => (string) Config::get('email_customer_booking_confirmed_subject', 'Your Booking Confirmation #{booking_code} - {site_name}'),
                'heading'     => (string) Config::get('email_customer_booking_confirmed_heading', 'Booking Confirmed!'),
                'content'     => (string) Config::get('email_customer_booking_confirmed_additional_content', ''),
            ],
            'customer_booking_cancelled' => [
                'title'       => __('Customer: Booking Cancelled', 'tourivo'),
                'recipient'   => __('Customer (Booker)', 'tourivo'),
                'desc'        => __('Sent to traveler when a reservation is cancelled.', 'tourivo'),
                'enabled'     => (bool) Config::get('email_customer_booking_cancelled_enabled', true),
                'subject'     => (string) Config::get('email_customer_booking_cancelled_subject', 'Your Booking #{booking_code} has been Cancelled - {site_name}'),
                'heading'     => (string) Config::get('email_customer_booking_cancelled_heading', 'Booking Cancelled'),
                'content'     => (string) Config::get('email_customer_booking_cancelled_additional_content', ''),
            ],
            'customer_trip_reminder' => [
                'title'       => __('Customer: Upcoming Trip Reminder', 'tourivo'),
                'recipient'   => __('Customer (Booker)', 'tourivo'),
                'desc'        => __('Automated reminder dispatched X days prior to check-in/departure.', 'tourivo'),
                'enabled'     => (bool) Config::get('email_customer_trip_reminder_enabled', true),
                'subject'     => (string) Config::get('email_customer_trip_reminder_subject', 'Upcoming Trip Reminder: #{booking_code} - {site_name}'),
                'heading'     => (string) Config::get('email_customer_trip_reminder_heading', 'Your Trip is Coming Up Soon!'),
                'content'     => (string) Config::get('email_customer_trip_reminder_additional_content', ''),
            ],
            'admin_new_booking' => [
                'title'       => __('Admin: New Booking Notification', 'tourivo'),
                'recipient'   => __('Admin Notification Recipient', 'tourivo'),
                'desc'        => __('Sent to site administrator when a new booking is submitted.', 'tourivo'),
                'enabled'     => (bool) Config::get('email_admin_new_booking_enabled', true),
                'subject'     => (string) Config::get('email_admin_new_booking_subject', '[New Booking] #{booking_code} by {customer_name}'),
                'heading'     => (string) Config::get('email_admin_new_booking_heading', 'New Booking Received'),
                'content'     => (string) Config::get('email_admin_new_booking_additional_content', ''),
            ],
            'admin_booking_cancelled' => [
                'title'       => __('Admin: Booking Cancelled Alert', 'tourivo'),
                'recipient'   => __('Admin Notification Recipient', 'tourivo'),
                'desc'        => __('Sent to site administrator when a booking is cancelled.', 'tourivo'),
                'enabled'     => (bool) Config::get('email_admin_booking_cancelled_enabled', true),
                'subject'     => (string) Config::get('email_admin_booking_cancelled_subject', '[Booking Cancelled] #{booking_code} - {customer_name}'),
                'heading'     => (string) Config::get('email_admin_booking_cancelled_heading', 'Booking Cancelled Notification'),
                'content'     => (string) Config::get('email_admin_booking_cancelled_additional_content', ''),
            ],
            'admin_new_inquiry' => [
                'title'       => __('Admin: New Customer Inquiry', 'tourivo'),
                'recipient'   => __('Admin Notification Recipient', 'tourivo'),
                'desc'        => __('Sent to site administrator when a traveler submits the trip inquiry popup form.', 'tourivo'),
                'enabled'     => (bool) Config::get('email_admin_new_inquiry_enabled', true),
                'subject'     => (string) Config::get('email_admin_new_inquiry_subject', '[New Inquiry] {item_title} from {customer_name}'),
                'heading'     => (string) Config::get('email_admin_new_inquiry_heading', 'New Traveler Trip Inquiry'),
                'content'     => (string) Config::get('email_admin_new_inquiry_additional_content', ''),
            ],
        ];
        ?>
        <div class="wrap tourivo-admin-wrap">
            <div class="tourivo-dashboard-header">
                <div>
                    <h1 class="wp-heading-inline">⚙️ <?php esc_html_e('Tourivo Settings', 'tourivo'); ?></h1>
                    <p class="tourivo-subtitle"><?php esc_html_e('Configure your currency rates, booking defaults, design styling, SEO, and Webhooks.', 'tourivo'); ?></p>
                </div>
            </div>

            <!-- Settings Tabs Navigation -->
            <h2 class="nav-tab-wrapper tourivo-settings-tabs">
                <a href="#tab-general" class="nav-tab nav-tab-active" data-tab="general">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('General & Currency', 'tourivo'); ?>
                </a>
                <a href="#tab-appearance" class="nav-tab" data-tab="appearance">
                    <span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e('Design & SEO', 'tourivo'); ?>
                </a>
                <a href="#tab-emails" class="nav-tab" data-tab="emails">
                    <span class="dashicons dashicons-email"></span> <?php esc_html_e('Email Notifications', 'tourivo'); ?>
                </a>
                <a href="#tab-webhooks" class="nav-tab" data-tab="webhooks">
                    <span class="dashicons dashicons-rest-api"></span> <?php esc_html_e('Webhooks & API', 'tourivo'); ?>
                </a>
                <a href="#tab-privacy" class="nav-tab" data-tab="privacy">
                    <span class="dashicons dashicons-privacy"></span> <?php esc_html_e('Privacy & GDPR', 'tourivo'); ?>
                </a>
                <a href="#tab-advanced" class="nav-tab" data-tab="advanced">
                    <span class="dashicons dashicons-shield"></span> <?php esc_html_e('Advanced & System', 'tourivo'); ?>
                </a>
            </h2>

            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('tourivo_settings_action', 'tourivo_settings_nonce'); ?>

                <!-- TAB 1: General & Currency -->
                <div id="settings-tab-general" class="tourivo-settings-tab-pane">
                    <div class="tourivo-settings-box">
                        <h2><?php esc_html_e('Base Currency & Pricing Options', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="currency"><?php esc_html_e('Base Currency Code', 'tourivo'); ?></label></th>
                                <td>
                                    <select name="currency" id="currency">
                                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD - US Dollar ($)</option>
                                        <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro (€)</option>
                                        <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound (£)</option>
                                        <option value="BDT" <?php selected($currency, 'BDT'); ?>>BDT - Bangladeshi Taka (৳)</option>
                                        <option value="INR" <?php selected($currency, 'INR'); ?>>INR - Indian Rupee (₹)</option>
                                        <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD - Australian Dollar ($)</option>
                                        <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD - Canadian Dollar ($)</option>
                                        <option value="AED" <?php selected($currency, 'AED'); ?>>AED - UAE Dirham (AED)</option>
                                    </select>
                                    <p class="description"><?php esc_html_e('The main currency used to store prices in the database.', 'tourivo'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="currency_symbol"><?php esc_html_e('Currency Symbol', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="currency_symbol" type="text" id="currency_symbol" value="<?php echo esc_attr($currencySymbol); ?>" class="regular-text" style="max-width: 100px;">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="currency_position"><?php esc_html_e('Symbol Position', 'tourivo'); ?></label></th>
                                <td>
                                    <select name="currency_position" id="currency_position">
                                        <option value="left" <?php selected($currencyPos, 'left'); ?>><?php esc_html_e('Left ($99)', 'tourivo'); ?></option>
                                        <option value="right" <?php selected($currencyPos, 'right'); ?>><?php esc_html_e('Right (99$)', 'tourivo'); ?></option>
                                        <option value="left_space" <?php selected($currencyPos, 'left_space'); ?>><?php esc_html_e('Left with space ($ 99)', 'tourivo'); ?></option>
                                        <option value="right_space" <?php selected($currencyPos, 'right_space'); ?>><?php esc_html_e('Right with space (99 $)', 'tourivo'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2><?php esc_html_e('Tax & VAT Configuration', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable Tax', 'tourivo'); ?></th>
                                <td>
                                    <label>
                                        <input name="tax_enabled" type="checkbox" id="tax_enabled" value="1" <?php checked($taxEnabled, 1); ?>>
                                        <?php esc_html_e('Calculate tax on bookings', 'tourivo'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tax_label"><?php esc_html_e('Tax Name / Label', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="tax_label" type="text" id="tax_label" value="<?php echo esc_attr($taxLabel); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g. VAT / GST / Sales Tax', 'tourivo'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tax_rate"><?php esc_html_e('Tax Rate (%)', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="tax_rate" type="number" step="0.01" min="0" max="100" id="tax_rate" value="<?php echo esc_attr((string) $taxRate); ?>" class="regular-text" style="max-width: 120px;">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tax_mode"><?php esc_html_e('Tax Mode', 'tourivo'); ?></label></th>
                                <td>
                                    <select name="tax_mode" id="tax_mode">
                                        <option value="exclusive" <?php selected($taxMode, 'exclusive'); ?>><?php esc_html_e('Exclusive (Tax added on top of subtotal)', 'tourivo'); ?></option>
                                        <option value="inclusive" <?php selected($taxMode, 'inclusive'); ?>><?php esc_html_e('Inclusive (Tax extracted from price)', 'tourivo'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tax_applies_to"><?php esc_html_e('Tax Applies To', 'tourivo'); ?></label></th>
                                <td>
                                    <select name="tax_applies_to" id="tax_applies_to">
                                        <option value="all" <?php selected($taxAppliesTo, 'all'); ?>><?php esc_html_e('All Bookings (Tours & Rooms)', 'tourivo'); ?></option>
                                        <option value="tours" <?php selected($taxAppliesTo, 'tours'); ?>><?php esc_html_e('Tours Only', 'tourivo'); ?></option>
                                        <option value="rooms" <?php selected($taxAppliesTo, 'rooms'); ?>><?php esc_html_e('Rooms / Accommodations Only', 'tourivo'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2><?php esc_html_e('Supported Multi-Currencies for Frontend Switcher', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('These exchange rates are used when travelers switch currencies with [tourivo_currency_switcher]:', 'tourivo'); ?></p>
                        
                        <table class="widefat striped" style="max-width: 600px; margin-top: 10px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Currency', 'tourivo'); ?></th>
                                    <th><?php esc_html_e('Symbol', 'tourivo'); ?></th>
                                    <th><?php esc_html_e('Exchange Rate (vs 1 Base)', 'tourivo'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currencies as $code => $c) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($code); ?></strong> (<?php echo esc_html($c['name']); ?>)</td>
                                        <td><code><?php echo esc_html($c['symbol']); ?></code></td>
                                        <td><code><?php echo esc_html((string)$c['rate']); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2><?php esc_html_e('Booking Defaults & Post-Booking Experience', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="default_booking_status"><?php esc_html_e('Default Booking Status', 'tourivo'); ?></label></th>
                                <td>
                                    <select name="default_booking_status" id="default_booking_status">
                                        <option value="pending" <?php selected($defaultStatus, 'pending'); ?>><?php esc_html_e('Pending (Awaiting Admin Confirmation)', 'tourivo'); ?></option>
                                        <option value="confirmed" <?php selected($defaultStatus, 'confirmed'); ?>><?php esc_html_e('Instant Confirmed', 'tourivo'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="redirect_after_booking"><?php esc_html_e('Post-Booking Action', 'tourivo'); ?></label></th>
                                <td>
                                    <select name="redirect_after_booking" id="redirect_after_booking">
                                        <option value="inline" <?php selected($redirectAfterBooking, 'inline'); ?>><?php esc_html_e('Inline Message (Stay on Booking Page)', 'tourivo'); ?></option>
                                        <option value="thankyou" <?php selected($redirectAfterBooking, 'thankyou'); ?>><?php esc_html_e('Redirect to Dedicated Thank You / Confirmation Page', 'tourivo'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Choose what happens immediately after a customer submits the frontend booking form.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="thankyou_page_id"><?php esc_html_e('Thank You / Confirmation Page', 'tourivo'); ?></label></th>
                                <td>
                                    <?php
                                    wp_dropdown_pages([
                                        'name'              => 'thankyou_page_id',
                                        'id'                => 'thankyou_page_id',
                                        'selected'          => $thankyouPageId,
                                        'show_option_none'  => esc_html__('— Select Page with [tourivo_thank_you] —', 'tourivo'),
                                        'option_none_value' => '0',
                                    ]);
                                    ?>
                                    <p class="description"><?php esc_html_e('Page containing the [tourivo_thank_you] shortcode where travelers are redirected.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>🛡️ <?php esc_html_e('Traveler Self-Service & Cancellation Policy', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Allow Cancellation Requests', 'tourivo'); ?></th>
                                <td>
                                    <label for="allow_cancel_requests">
                                        <input name="allow_cancel_requests" type="checkbox" id="allow_cancel_requests" value="1" <?php checked($allowCancelRequests, 1); ?>>
                                        <strong><?php esc_html_e('Allow travelers to request cancellation via the booking lookup portal.', 'tourivo'); ?></strong>
                                    </label>
                                    <p class="description"><?php esc_html_e('When enabled, travelers can initiate a cancellation request on the track booking page.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="customer_self_cancel_hours"><?php esc_html_e('Self-Cancellation Window (Hours Before Check-in)', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="customer_self_cancel_hours" type="number" id="customer_self_cancel_hours" min="0" max="720" value="<?php echo esc_attr((string) $customerSelfCancelHours); ?>" class="small-text">
                                    <?php esc_html_e('hours before trip departure / hotel check-in.', 'tourivo'); ?>
                                    <p class="description"><?php esc_html_e('Set to 0 (default) to require admin review for all cancellations. If set to > 0, unpaid bookings can be instantly cancelled by the traveler if check-in is at least N hours away, immediately releasing inventory.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>🌐 <?php esc_html_e('Language & Localization / ভাষা ও লোকালাইজেশন', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="plugin_language"><?php esc_html_e('Plugin Language / প্লাগিনের ভাষা', 'tourivo'); ?></label></th>
                                <td>
                                    <select name="plugin_language" id="plugin_language">
                                        <option value="default" <?php selected($pluginLanguage, 'default'); ?>><?php
                                        echo esc_html(
                                            sprintf(
                                                /* translators: %s: Active WordPress locale code */
                                                __('Site Default (WordPress Locale: %s)', 'tourivo'),
                                                get_locale()
                                            )
                                        );
                                        ?></option>
                                        <option value="en" <?php selected($pluginLanguage, 'en'); ?>>English (US)</option>
                                        <option value="bn" <?php selected($pluginLanguage, 'bn'); ?>>বাংলা (Bengali - Bangladesh & Global)</option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Choose whether Tourivo displays in English or Bengali across the admin panel, booking forms, vouchers, and notifications.', 'tourivo'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Bengali Numbers / বাংলা সংখ্যা', 'tourivo'); ?></th>
                                <td>
                                    <label for="use_bangla_digits">
                                        <input name="use_bangla_digits" type="checkbox" id="use_bangla_digits" value="1" <?php checked($useBanglaDigits, 1); ?>>
                                        <?php esc_html_e('Render prices and counters in Bengali digits (যেমন: ৳১,৫০০, ৩ জন)', 'tourivo'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: Design & SEO Styling -->
                <div id="settings-tab-appearance" class="tourivo-settings-tab-pane" style="display:none;">
                    <div class="tourivo-settings-box">
                        <h2>🎨 <?php esc_html_e('Brand Color Customizer & Styling', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Match Tourivo booking widgets, cards, and buttons with your active WordPress theme styling.', 'tourivo'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="primary_color"><?php esc_html_e('Primary Brand Color', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="primary_color" type="color" id="primary_color" value="<?php echo esc_attr($primaryColor); ?>" style="width: 50px; height: 35px; vertical-align: middle; cursor: pointer;">
                                    <code><?php echo esc_html($primaryColor); ?></code>
                                    <p class="description"><?php esc_html_e('Used for primary booking buttons, price tags, active calendar dates, and highlights.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="primary_hover"><?php esc_html_e('Primary Hover Color', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="primary_hover" type="color" id="primary_hover" value="<?php echo esc_attr($primaryHover); ?>" style="width: 50px; height: 35px; vertical-align: middle; cursor: pointer;">
                                    <code><?php echo esc_html($primaryHover); ?></code>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="accent_color"><?php esc_html_e('Accent / Badge Color', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="accent_color" type="color" id="accent_color" value="<?php echo esc_attr($accentColor); ?>" style="width: 50px; height: 35px; vertical-align: middle; cursor: pointer;">
                                    <code><?php echo esc_html($accentColor); ?></code>
                                    <p class="description"><?php esc_html_e('Used for promotional ribbons, star ratings, and discount tags.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="button_text_color"><?php esc_html_e('Button Text Color', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="button_text_color" type="color" id="button_text_color" value="<?php echo esc_attr($btnTextColor); ?>" style="width: 50px; height: 35px; vertical-align: middle; cursor: pointer;">
                                    <code><?php echo esc_html($btnTextColor); ?></code>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="border_radius"><?php esc_html_e('Widget Border Radius', 'tourivo'); ?></label></th>
                                <td>
                                    <select name="border_radius" id="border_radius">
                                        <option value="4px" <?php selected($borderRadius, '4px'); ?>><?php esc_html_e('Sharp (4px)', 'tourivo'); ?></option>
                                        <option value="8px" <?php selected($borderRadius, '8px'); ?>><?php esc_html_e('Modern Rounded (8px - Default)', 'tourivo'); ?></option>
                                        <option value="12px" <?php selected($borderRadius, '12px'); ?>><?php esc_html_e('Smooth Curved (12px)', 'tourivo'); ?></option>
                                        <option value="16px" <?php selected($borderRadius, '16px'); ?>><?php esc_html_e('Pill Curved (16px)', 'tourivo'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>🚀 <?php esc_html_e('Search Engine Optimization (SEO) & Social Sharing', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Schema.org JSON-LD', 'tourivo'); ?></th>
                                <td>
                                    <label for="enable_schema">
                                        <input name="enable_schema" type="checkbox" id="enable_schema" value="1" <?php checked($enableSchema, 1); ?>>
                                        <strong><?php esc_html_e('Enable automatic Schema.org structured data on Tour and Hotel pages.', 'tourivo'); ?></strong>
                                    </label>
                                    <p class="description"><?php esc_html_e('Generates TouristTrip and Hotel JSON-LD metadata so Google can display rich search snippets, prices, and star ratings.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('OpenGraph & Twitter Cards', 'tourivo'); ?></th>
                                <td>
                                    <label for="enable_opengraph">
                                        <input name="enable_opengraph" type="checkbox" id="enable_opengraph" value="1" <?php checked($enableOpenGraph, 1); ?>>
                                        <strong><?php esc_html_e('Enable rich social sharing preview cards on Facebook, WhatsApp, and Twitter / X.', 'tourivo'); ?></strong>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Google Tag Manager / DataLayer', 'tourivo'); ?></th>
                                <td>
                                    <label for="enable_datalayer">
                                        <input name="enable_datalayer" type="checkbox" id="enable_datalayer" value="1" <?php checked($enableDatalayer, 1); ?>>
                                        <strong><?php esc_html_e('Enable eCommerce conversion tracking dataLayer event on Thank You page.', 'tourivo'); ?></strong>
                                    </label>
                                    <p class="description"><?php esc_html_e('Pushes a privacy-friendly tourivo_booking event (booking_id, value, currency) to window.dataLayer without customer PII.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: Email Notifications -->
                <div id="settings-tab-emails" class="tourivo-settings-tab-pane" style="display: none;">
                    <div class="tourivo-settings-box">
                        <h2>✉️ <?php esc_html_e('Sender Details & Routing', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="email_from_name"><?php esc_html_e('Sender "From" Name', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="email_from_name" type="text" id="email_from_name" value="<?php echo esc_attr($fromName); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('The name appearing in traveler inboxes (e.g. Your Agency Name).', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="email_from_address"><?php esc_html_e('Sender "From" Email', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="email_from_address" type="email" id="email_from_address" value="<?php echo esc_attr($fromEmail); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('The outbound email address used in the From and Reply-To headers.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="email_notification_address"><?php esc_html_e('Admin Notification Recipient', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="email_notification_address" type="email" id="email_notification_address" value="<?php echo esc_attr($notifyEmail); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Internal staff / operator email that receives new booking alerts, cancellation notices, and customer inquiries.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>⚙️ <?php esc_html_e('General Email & Reminder Settings', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="reminder_days_before"><?php esc_html_e('Upcoming Trip Reminder (Days Before)', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="reminder_days_before" type="number" id="reminder_days_before" min="0" max="30" value="<?php echo esc_attr((string) $reminderDays); ?>" class="small-text">
                                    <?php esc_html_e('days before tour departure or hotel check-in date.', 'tourivo'); ?>
                                    <p class="description"><?php esc_html_e('Set to 0 to disable automated daily trip reminder emails. Tourivo runs a daily background cron check to dispatch reminders.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lookup_page_id"><?php esc_html_e('Customer Booking Lookup Page', 'tourivo'); ?></label></th>
                                <td>
                                    <?php
                                    wp_dropdown_pages([
                                        'name'              => 'lookup_page_id',
                                        'id'                => 'lookup_page_id',
                                        'selected'          => $lookupPageId,
                                        'show_option_none'  => esc_html__('— Select Page with [tourivo_booking_lookup] —', 'tourivo'),
                                        'option_none_value' => '0',
                                    ]);
                                    ?>
                                    <p class="description"><?php esc_html_e('Selected page is used to construct the {lookup_url} placeholder so travelers can look up and self-manage reservations.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="offline_payment_instructions"><?php esc_html_e('Offline / Manual Payment Instructions', 'tourivo'); ?></label></th>
                                <td>
                                    <textarea name="offline_payment_instructions" id="offline_payment_instructions" rows="4" class="large-text code" placeholder="<?php esc_attr_e("Bank Transfer Details:\nBank: City Bank\nAccount: 123456789\nOr bKash Merchant: 01700000000 (Use booking code as reference)", 'tourivo'); ?>"><?php echo esc_textarea($offlinePayment); ?></textarea>
                                    <p class="description"><?php esc_html_e('Included in Pending Booking emails via the {offline_payment_instructions} placeholder for manual bank wire, bKash, or pay-on-arrival instructions.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>🏷️ <?php esc_html_e('Available Template Placeholders', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Use these tags inside email subjects, headings, and additional content. They will be dynamically replaced with real booking data:', 'tourivo'); ?></p>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin: 12px 0 24px; font-size: 13px; line-height: 1.8;">
                            <code>{customer_name}</code>, <code>{customer_email}</code>, <code>{customer_phone}</code>, <code>{booking_code}</code>, <code>{item_title}</code>, <code>{item_type}</code>, <code>{check_in}</code>, <code>{check_out}</code>, <code>{guests}</code>, <code>{total_amount}</code>, <code>{subtotal}</code>, <code>{tax_amount}</code>, <code>{discount_amount}</code>, <code>{status}</code>, <code>{site_name}</code>, <code>{site_url}</code>, <code>{voucher_url}</code>, <code>{lookup_url}</code>, <code>{offline_payment_instructions}</code>
                            <br><small style="color: #64748b;"><?php esc_html_e('For inquiry notifications: {inquiry_name}, {inquiry_email}, {inquiry_phone}, {inquiry_message}, {item_title}, {site_name}.', 'tourivo'); ?></small>
                        </div>

                        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>📬 <?php esc_html_e('Email Templates Configuration', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Customize the subject lines, headings, and additional notes for all automated emails. You can also override email layouts in your active WordPress theme under your-theme/tourivo/emails/.', 'tourivo'); ?></p>

                        <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 18px;">
                            <?php foreach ($emailTemplates as $tmplKey => $tmpl) : ?>
                                <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 14px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <label style="font-size: 15px; font-weight: 600; color: #1e293b; cursor: pointer;">
                                                <input type="checkbox" name="email_<?php echo esc_attr($tmplKey); ?>_enabled" value="1" <?php checked($tmpl['enabled']); ?>>
                                                <?php echo esc_html($tmpl['title']); ?>
                                            </label>
                                            <span style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 9999px; font-weight: 600;">
                                                <?php echo esc_html($tmpl['recipient']); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <button type="button" class="button button-small tourivo-send-single-test-email-btn" data-email-type="<?php echo esc_attr($tmplKey); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_test_email_nonce')); ?>">
                                                ✉️ <?php esc_html_e('Test This Email', 'tourivo'); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <p style="color: #64748b; font-size: 13px; margin: 0 0 14px;"><?php echo esc_html($tmpl['desc']); ?></p>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px;">
                                        <div>
                                            <label style="display: block; font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px;" for="email_<?php echo esc_attr($tmplKey); ?>_subject">
                                                <?php esc_html_e('Email Subject Line', 'tourivo'); ?>
                                            </label>
                                            <input type="text" name="email_<?php echo esc_attr($tmplKey); ?>_subject" id="email_<?php echo esc_attr($tmplKey); ?>_subject" value="<?php echo esc_attr($tmpl['subject']); ?>" class="large-text">
                                        </div>
                                        <div>
                                            <label style="display: block; font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px;" for="email_<?php echo esc_attr($tmplKey); ?>_heading">
                                                <?php esc_html_e('Email Main Heading', 'tourivo'); ?>
                                            </label>
                                            <input type="text" name="email_<?php echo esc_attr($tmplKey); ?>_heading" id="email_<?php echo esc_attr($tmplKey); ?>_heading" value="<?php echo esc_attr($tmpl['heading']); ?>" class="large-text">
                                        </div>
                                    </div>

                                    <div>
                                        <label style="display: block; font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px;" for="email_<?php echo esc_attr($tmplKey); ?>_additional_content">
                                            <?php esc_html_e('Additional Content / Custom Notes', 'tourivo'); ?>
                                        </label>
                                        <textarea name="email_<?php echo esc_attr($tmplKey); ?>_additional_content" id="email_<?php echo esc_attr($tmplKey); ?>_additional_content" rows="3" class="large-text" placeholder="<?php esc_attr_e('Optional custom message, arrival guidelines, or policy instructions...', 'tourivo'); ?>"><?php echo esc_textarea($tmpl['content']); ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>✉️ <?php esc_html_e('Test Email Dispatcher', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Send a sample email to verify your WordPress mail server setup and layout rendering.', 'tourivo'); ?></p>
                        
                        <div style="display: flex; gap: 10px; align-items: center; margin-top: 12px; flex-wrap: wrap;">
                            <select id="tourivo_test_email_type" class="regular-text" style="max-width: 280px;">
                                <?php foreach ($emailTemplates as $tmplKey => $tmpl) : ?>
                                    <option value="<?php echo esc_attr($tmplKey); ?>"><?php echo esc_html($tmpl['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="email" id="tourivo_test_email_recipient" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text" placeholder="your-email@example.com">
                            <button type="button" class="button button-secondary" id="tourivo-send-test-email-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_test_email_nonce')); ?>">
                                ✉️ <?php esc_html_e('Send Test Email', 'tourivo'); ?>
                            </button>
                        </div>
                        <div id="tourivo-test-email-alert" style="display: none; margin-top: 12px; font-weight: 600;"></div>
                    </div>
                </div>

                <!-- TAB 4: Webhooks & API -->
                <div id="settings-tab-webhooks" class="tourivo-settings-tab-pane" style="display: none;">
                    <div class="tourivo-settings-box">
                        <h2>🔗 <?php esc_html_e('Outbound Webhooks (Zapier, Make, n8n, CRMs)', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Send real-time JSON payloads to external webhooks whenever reservations are created or updated.', 'tourivo'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="webhook_url"><?php esc_html_e('Webhook Target URL', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="webhook_url" type="url" id="webhook_url" value="<?php echo esc_url($webhookUrl); ?>" class="large-text" placeholder="https://hooks.zapier.com/hooks/catch/..." style="max-width: 600px;">
                                    <p class="description"><?php esc_html_e('Your external automation endpoint URL that receives POST requests.', 'tourivo'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="webhook_secret"><?php esc_html_e('Secret Key (HMAC SHA-256)', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="webhook_secret" type="text" id="webhook_secret" value="<?php echo esc_attr($webhookSecret); ?>" class="regular-text" placeholder="e.g. secret_trv_xxxx" style="max-width: 400px;">
                                    <button type="button" class="button" id="tourivo-gen-secret-btn"><?php esc_html_e('Generate Secret', 'tourivo'); ?></button>
                                    <p class="description"><?php esc_html_e('Optional secret key used to compute the X-Tourivo-Signature HTTP header for payload verification.', 'tourivo'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Subscribed Events', 'tourivo'); ?></th>
                                <td>
                                    <fieldset>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input name="webhook_events[]" type="checkbox" value="booking.created" <?php checked(in_array('booking.created', $webhookEvents, true)); ?>>
                                            <strong><code>booking.created</code></strong> — <?php esc_html_e('Fired when a new tour or hotel booking is submitted.', 'tourivo'); ?>
                                        </label>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input name="webhook_events[]" type="checkbox" value="booking.status_changed" <?php checked(in_array('booking.status_changed', $webhookEvents, true)); ?>>
                                            <strong><code>booking.status_changed</code></strong> — <?php esc_html_e('Fired when a booking status changes (confirmed, cancelled, completed).', 'tourivo'); ?>
                                        </label>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input name="webhook_events[]" type="checkbox" value="inquiry.created" <?php checked(in_array('inquiry.created', $webhookEvents, true)); ?>>
                                            <strong><code>inquiry.created</code></strong> — <?php esc_html_e('Fired when a customer inquiry is received.', 'tourivo'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Payload Data Minimization', 'tourivo'); ?></th>
                                <td>
                                    <fieldset>
                                        <label style="display: block; margin-bottom: 6px;">
                                            <input name="webhook_include_phone" type="checkbox" value="1" <?php checked($webhookIncludePhone, 1); ?>>
                                            <?php esc_html_e('Include customer phone numbers in webhook payloads.', 'tourivo'); ?>
                                        </label>
                                        <label style="display: block;">
                                            <input name="webhook_include_message" type="checkbox" value="1" <?php checked($webhookIncludeMessage, 1); ?>>
                                            <?php esc_html_e('Include custom traveler inquiry messages in inquiry webhook payloads.', 'tourivo'); ?>
                                        </label>
                                    </fieldset>
                                    <p class="description"><?php esc_html_e('Uncheck to strip personal communication details and phone numbers from external automated webhooks for privacy minimization.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>🧪 <?php esc_html_e('Test Webhook Dispatcher', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Send a test ping payload to your webhook target URL to verify connectivity.', 'tourivo'); ?></p>
                        
                        <div style="display: flex; gap: 10px; align-items: center; margin-top: 12px;">
                            <button type="button" class="button button-secondary" id="tourivo-send-test-webhook-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_test_webhook_nonce')); ?>">
                                🚀 <?php esc_html_e('Send Test Ping Payload', 'tourivo'); ?>
                            </button>
                        </div>
                        <div id="tourivo-test-webhook-alert" style="display: none; margin-top: 12px; font-weight: 600;"></div>
                    </div>
                </div>

                <!-- TAB 5: Privacy & GDPR Compliance -->
                <div id="settings-tab-privacy" class="tourivo-settings-tab-pane" style="display: none;">
                    <div class="tourivo-settings-box">
                        <h2>🛡️ <?php esc_html_e('User Consent & Terms Agreement', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Require travelers to explicitly agree to your Terms and Privacy Policy before submitting bookings or trip inquiries.', 'tourivo'); ?></p>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Require Consent', 'tourivo'); ?></th>
                                <td>
                                    <label for="require_consent">
                                        <input name="require_consent" type="checkbox" id="require_consent" value="1" <?php checked($requireConsent, 1); ?>>
                                        <strong><?php esc_html_e('Require checkbox agreement before checkout and inquiry submission.', 'tourivo'); ?></strong>
                                    </label>
                                    <p class="description"><?php esc_html_e('When enabled, bookings and inquiries cannot proceed without agreeing to terms. Tourivo records the exact timestamp and policy version SHA-256 hash.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="privacy_policy_page_id"><?php esc_html_e('Privacy Policy Page', 'tourivo'); ?></label></th>
                                <td>
                                    <?php
                                    wp_dropdown_pages([
                                        'name'              => 'privacy_policy_page_id',
                                        'id'                => 'privacy_policy_page_id',
                                        'selected'          => $privacyPolicyPageId,
                                        'show_option_none'  => esc_html__('— Use WordPress Core Privacy Policy Page —', 'tourivo'),
                                        'option_none_value' => '0',
                                    ]);
                                    ?>
                                    <p class="description"><?php esc_html_e('By default, Tourivo links to the WordPress site privacy policy defined under Settings → Privacy.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="terms_page_id"><?php esc_html_e('Terms & Conditions Page', 'tourivo'); ?></label></th>
                                <td>
                                    <?php
                                    wp_dropdown_pages([
                                        'name'              => 'terms_page_id',
                                        'id'                => 'terms_page_id',
                                        'selected'          => $termsPageId,
                                        'show_option_none'  => esc_html__('— Select Terms and Conditions Page —', 'tourivo'),
                                        'option_none_value' => '0',
                                    ]);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="consent_label"><?php esc_html_e('Consent Checkbox Label', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="consent_label" type="text" id="consent_label" value="<?php echo esc_attr($consentLabel); ?>" class="large-text">
                                    <p class="description"><?php esc_html_e('Placeholders {privacy} and {terms} will be automatically transformed into clickable links.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>🔒 <?php esc_html_e('IP Storage & Data Minimization', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Store IP Addresses', 'tourivo'); ?></th>
                                <td>
                                    <label for="store_ip">
                                        <input name="store_ip" type="checkbox" id="store_ip" value="1" <?php checked($storeIp, 1); ?>>
                                        <?php esc_html_e('Log client IP addresses with booking and inquiry records.', 'tourivo'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('If disabled, the IP address column will remain blank in the database. Rate limiting defenses continue securely using one-way transient hashes.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>🧹 <?php esc_html_e('Scheduled Data Retention & Anonymization Routine', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Automate GDPR storage limitation compliance. Bookings are safely anonymized (retaining financial amounts and lines for tax laws), while inquiries are permanently deleted.', 'tourivo'); ?></p>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="anonymize_after_months"><?php esc_html_e('Anonymize Completed Bookings After', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="anonymize_after_months" type="number" id="anonymize_after_months" min="0" max="120" value="<?php echo esc_attr((string) $anonymizeAfterMonths); ?>" class="small-text">
                                    <?php esc_html_e('months (Set to 0 to disable automated anonymization).', 'tourivo'); ?>
                                    <p class="description"><?php esc_html_e('Replaces customer name, email, phone, billing address, and IP with anonymized identifiers while keeping revenue stats intact.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="delete_inquiries_after_months"><?php esc_html_e('Delete Old Inquiries After', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="delete_inquiries_after_months" type="number" id="delete_inquiries_after_months" min="0" max="120" value="<?php echo esc_attr((string) $deleteInqAfterMonths); ?>" class="small-text">
                                    <?php esc_html_e('months (Set to 0 to retain inquiries indefinitely).', 'tourivo'); ?>
                                </td>
                            </tr>
                        </table>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-top: 15px; max-width: 650px;">
                            <h4 style="margin: 0 0 8px; color: #1e293b;"><?php esc_html_e('Retention Routine Dry-Run Preview', 'tourivo'); ?></h4>
                            <p style="margin: 0 0 12px; font-size: 13px; color: #64748b;"><?php esc_html_e('Check how many expired records are currently eligible for scheduled retention actions without modifying data.', 'tourivo'); ?></p>
                            <button type="button" class="button button-secondary" id="tourivo-retention-dry-run-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_admin_nonce')); ?>">
                                🔍 <?php esc_html_e('Check Eligible Records Count', 'tourivo'); ?>
                            </button>
                            <div id="tourivo-retention-dry-run-result" style="display: none; margin-top: 10px; font-size: 13px; font-weight: 600;"></div>
                        </div>

                        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>📋 <?php esc_html_e('WordPress Core Privacy Tools Integration', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Tourivo is fully integrated with standard WordPress privacy compliance features:', 'tourivo'); ?></p>
                        <ul style="list-style: disc; padding-left: 20px; line-height: 1.8; color: #475569;">
                            <li><strong><?php esc_html_e('Export Personal Data:', 'tourivo'); ?></strong> <?php esc_html_e('Exports traveler bookings and trip inquiries when requested via Tools → Export Personal Data.', 'tourivo'); ?></li>
                            <li><strong><?php esc_html_e('Erase Personal Data:', 'tourivo'); ?></strong> <?php esc_html_e('Erases inquiry submissions and wishlist user meta, and anonymizes booking financial records for tax/statutory compliance via Tools → Erase Personal Data.', 'tourivo'); ?></li>
                            <li><strong><?php esc_html_e('Privacy Policy Guide:', 'tourivo'); ?></strong> <?php esc_html_e('Suggested disclosures are automatically registered in the WordPress Privacy Policy Guide under Settings → Privacy.', 'tourivo'); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- TAB 6: Advanced & System -->
                <div id="settings-tab-advanced" class="tourivo-settings-tab-pane" style="display: none;">
                    <div class="tourivo-settings-box">
                        <h2><?php esc_html_e('Network & Proxy Configuration', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="proxy_mode"><?php esc_html_e('Behind Cloudflare / Reverse Proxy', 'tourivo'); ?></label></th>
                                <td>
                                    <select name="proxy_mode" id="proxy_mode">
                                        <option value="disabled" <?php selected($proxyMode, 'disabled'); ?>><?php esc_html_e('Disabled (Standard direct connection / REMOTE_ADDR)', 'tourivo'); ?></option>
                                        <option value="cloudflare" <?php selected($proxyMode, 'cloudflare'); ?>><?php esc_html_e('Cloudflare (Trust CF-Connecting-IP only from Cloudflare IPs)', 'tourivo'); ?></option>
                                        <option value="reverse_proxy" <?php selected($proxyMode, 'reverse_proxy'); ?>><?php esc_html_e('Reverse Proxy / Load Balancer (Trust X-Forwarded-For)', 'tourivo'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Enable this if your website is behind Cloudflare, AWS CloudFront, Nginx reverse proxy, or load balancers for accurate client IP detection.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="trusted_proxies"><?php esc_html_e('Custom Trusted Proxies', 'tourivo'); ?></label></th>
                                <td>
                                    <textarea name="trusted_proxies" id="trusted_proxies" rows="3" class="large-text code" placeholder="10.0.0.0/8, 192.168.1.100"><?php echo esc_textarea($trustedProxies); ?></textarea>
                                    <p class="description"><?php esc_html_e('Optional comma- or newline-separated list of trusted upstream proxy/load balancer IPs or CIDR subnets.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2><?php esc_html_e('Data Deletion on Uninstall', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Erase Data on Delete', 'tourivo'); ?></th>
                                <td>
                                    <label for="erase_data_on_uninstall">
                                        <input name="erase_data_on_uninstall" type="checkbox" id="erase_data_on_uninstall" value="1" <?php checked($eraseData, 1); ?>>
                                        <?php esc_html_e('Check this box if you want to completely erase custom database tables and settings when the plugin is deleted.', 'tourivo'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <h2><?php esc_html_e('Setup Wizard & Demo Tools', 'tourivo'); ?></h2>
                        <div style="background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 16px; margin-bottom: 20px; max-width: 600px;">
                            <h4 style="margin: 0 0 6px; color: #0f766e;"><?php esc_html_e('Quick Onboarding & Sample Data', 'tourivo'); ?></h4>
                            <p style="margin: 0 0 12px; font-size: 13px; color: #115e59;"><?php esc_html_e('Need to regenerate core pages, re-configure currency, or re-import sample demo tours and boutique hotels?', 'tourivo'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-setup-wizard')); ?>" class="button button-secondary">
                                🪄 <?php esc_html_e('Launch Setup Wizard', 'tourivo'); ?>
                            </a>
                        </div>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2><?php esc_html_e('System Information', 'tourivo'); ?></h2>
                        <table class="widefat striped" style="max-width: 600px;">
                            <tbody>
                                <tr>
                                    <td><strong><?php esc_html_e('Tourivo Core Version', 'tourivo'); ?></strong></td>
                                    <td><code><?php echo esc_html(TOURIVO_VERSION); ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('Database Schema Version', 'tourivo'); ?></strong></td>
                                    <td><code><?php echo esc_html((string)get_option('tourivo_db_version', '1.1.0')); ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('PHP Version', 'tourivo'); ?></strong></td>
                                    <td><code><?php echo esc_html(PHP_VERSION); ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('WordPress Version', 'tourivo'); ?></strong></td>
                                    <td><code><?php echo esc_html(get_bloginfo('version')); ?></code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <input type="submit" name="tourivo_save_settings" class="button button-primary button-hero" value="<?php esc_attr_e('Save Changes', 'tourivo'); ?>">
                </div>
            </form>
        </div>
        <?php
    }
}
