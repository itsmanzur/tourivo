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
            'email_from_name'       => get_bloginfo('name'),
            'email_from_address'    => get_option('admin_email'),
            'email_notification_address' => get_option('admin_email'),
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
