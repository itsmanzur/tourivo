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
                'default_booking_status'  => sanitize_text_field(wp_unslash($_POST['default_booking_status'] ?? 'pending')),
                'email_from_name'            => sanitize_text_field(wp_unslash($_POST['email_from_name'] ?? get_bloginfo('name'))),
                'email_from_address'         => sanitize_email(wp_unslash($_POST['email_from_address'] ?? get_option('admin_email'))),
                'email_notification_address' => sanitize_email(wp_unslash($_POST['email_notification_address'] ?? get_option('admin_email'))),
                'email_customer_subject'     => sanitize_text_field(wp_unslash($_POST['email_customer_subject'] ?? '')),
                'email_admin_subject'        => sanitize_text_field(wp_unslash($_POST['email_admin_subject'] ?? '')),
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
        $defaultStatus     = Config::get('default_booking_status', 'pending');
        $fromName          = Config::get('email_from_name', get_bloginfo('name'));
        $fromEmail         = Config::get('email_from_address', get_option('admin_email'));
        $notifyEmail       = Config::get('email_notification_address', get_option('admin_email'));
        $custSubject       = Config::get('email_customer_subject', '');
        $admSubject        = Config::get('email_admin_subject', '');
        $proxyMode         = Config::get('proxy_mode', 'disabled');
        $trustedProxies    = Config::get('trusted_proxies', '');
        $eraseData         = Config::get('erase_data_on_uninstall', 0);
        $currencies        = CurrencySwitcherShortcode::getCurrencies();
        ?>
        <div class="wrap tourivo-admin-wrap">
            <div class="tourivo-dashboard-header">
                <div>
                    <h1 class="wp-heading-inline">⚙️ <?php esc_html_e('Tourivo Settings', 'tourivo'); ?></h1>
                    <p class="tourivo-subtitle"><?php esc_html_e('Configure your currency rates, booking defaults, and email notifications.', 'tourivo'); ?></p>
                </div>
            </div>

            <!-- Settings Tabs Navigation -->
            <h2 class="nav-tab-wrapper tourivo-settings-tabs">
                <a href="#tab-general" class="nav-tab nav-tab-active" data-tab="general">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('General & Currency', 'tourivo'); ?>
                </a>
                <a href="#tab-emails" class="nav-tab" data-tab="emails">
                    <span class="dashicons dashicons-email"></span> <?php esc_html_e('Email Notifications', 'tourivo'); ?>
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

                        <h2><?php esc_html_e('Booking Defaults', 'tourivo'); ?></h2>
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
                        </table>
                    </div>
                </div>

                <!-- TAB 2: Email Notifications -->
                <div id="settings-tab-emails" class="tourivo-settings-tab-pane" style="display: none;">
                    <div class="tourivo-settings-box">
                        <h2><?php esc_html_e('Sender Details', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="email_from_name"><?php esc_html_e('Sender Name', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="email_from_name" type="text" id="email_from_name" value="<?php echo esc_attr($fromName); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="email_from_address"><?php esc_html_e('Sender Email Address', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="email_from_address" type="email" id="email_from_address" value="<?php echo esc_attr($fromEmail); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="email_notification_address"><?php esc_html_e('Admin Notification Recipient Email', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="email_notification_address" type="email" id="email_notification_address" value="<?php echo esc_attr($notifyEmail); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('New booking alerts and customer trip inquiries will be delivered to this email address.', 'tourivo'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2><?php esc_html_e('Email Template Customizer', 'tourivo'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="email_customer_subject"><?php esc_html_e('Customer Confirmation Subject', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="email_customer_subject" type="text" id="email_customer_subject" value="<?php echo esc_attr($custSubject); ?>" class="large-text" placeholder="Your Booking Confirmation #{booking_code} - {site_name}">
                                    <p class="description"><?php esc_html_e('Available tags: {customer_name}, {booking_code}, {item_title}, {check_in}, {total_amount}, {site_name}', 'tourivo'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="email_admin_subject"><?php esc_html_e('Admin Alert Subject', 'tourivo'); ?></label></th>
                                <td>
                                    <input name="email_admin_subject" type="text" id="email_admin_subject" value="<?php echo esc_attr($admSubject); ?>" class="large-text" placeholder="[New Booking] #{booking_code} by {customer_name}">
                                </td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <h2>✉️ <?php esc_html_e('Test Email Dispatcher', 'tourivo'); ?></h2>
                        <p class="description"><?php esc_html_e('Send a sample booking confirmation email to verify your WordPress mail server setup.', 'tourivo'); ?></p>
                        
                        <div style="display: flex; gap: 10px; align-items: center; margin-top: 12px;">
                            <input type="email" id="tourivo_test_email_recipient" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text" placeholder="your-email@example.com">
                            <button type="button" class="button button-secondary" id="tourivo-send-test-email-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_test_email_nonce')); ?>">
                                ✉️ <?php esc_html_e('Send Test Email', 'tourivo'); ?>
                            </button>
                        </div>
                        <div id="tourivo-test-email-alert" style="display: none; margin-top: 12px; font-weight: 600;"></div>
                    </div>
                </div>

                <!-- TAB 3: Advanced & System -->
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
