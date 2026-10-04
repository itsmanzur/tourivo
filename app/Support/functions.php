<?php
/**
 * Tourivo Public Global Helper Functions & Developer API
 *
 * Provides a standardized template engine, price formatting, and model accessor helpers
 * for theme developers, child themes, and third-party integrations.
 *
 * @package Tourivo\Support
 */

declare(strict_types=1);

use Tourivo\Config\Config;
use Tourivo\Models\Hotel;
use Tourivo\Models\Room;
use Tourivo\Models\Tour;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tourivo_locate_template')) {
    /**
     * Locate a template and return the path for inclusion.
     *
     * Search order:
     * 1. yourtheme/tourivo/$template_name
     * 2. yourtheme/$template_name
     * 3. plugins/tourivo/templates/$template_name
     *
     * @param string $templateName Template to locate.
     * @param string $templatePath Subdirectory in theme (default 'tourivo/').
     * @param string $defaultPath  Fallback plugin template path.
     * @return string
     */
    function tourivo_locate_template(string $templateName, string $templatePath = '', string $defaultPath = ''): string
    {
        if (empty($templatePath)) {
            $templatePath = (string) apply_filters('tourivo/template_path', 'tourivo/');
        }

        if (empty($defaultPath)) {
            $defaultPath = untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/';
        }

        // Look within theme/child-theme first
        $template = locate_template([
            trailingslashit($templatePath) . $templateName,
            $templateName,
        ]);

        // Fallback to plugin default template
        if (!$template || file_exists($template) === false) {
            $template = trailingslashit($defaultPath) . $templateName;
        }

        return (string) apply_filters('tourivo/locate_template', $template, $templateName, $templatePath);
    }
}

if (!function_exists('tourivo_get_template')) {
    /**
     * Load a template with arguments.
     *
     * @param string               $templateName Template name (e.g. 'single-tour.php' or 'cards/tour-card.php').
     * @param array<string, mixed> $args         Arguments to extract and pass into the template scope.
     * @param string               $templatePath Subdirectory in theme (default 'tourivo/').
     * @param string               $defaultPath  Fallback plugin template path.
     * @return void
     */
    function tourivo_get_template(string $templateName, array $args = [], string $templatePath = '', string $defaultPath = ''): void
    {
        $args = (array) apply_filters('tourivo/template_args', $args, $templateName);

        $located = tourivo_locate_template($templateName, $templatePath, $defaultPath);

        if (!file_exists($located)) {
            return;
        }

        do_action('tourivo/before_template', $templateName, $located, $args);

        if (!empty($args)) {
            extract($args); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }

        include $located;

        do_action('tourivo/after_template', $templateName, $located, $args);
    }
}

if (!function_exists('tourivo_get_template_part')) {
    /**
     * Load a template part into a template.
     *
     * @param string               $slug Template slug (e.g. 'cards/tour' or 'booking-panel').
     * @param string               $name Template name (e.g. 'compact' or 'grid').
     * @param array<string, mixed> $args Arguments to pass into template part.
     * @return void
     */
    function tourivo_get_template_part(string $slug, string $name = '', array $args = []): void
    {
        $template = '';

        if (!empty($name)) {
            $template = tourivo_locate_template("{$slug}-{$name}.php");
        }

        if (empty($template) || !file_exists($template)) {
            $template = tourivo_locate_template("{$slug}.php");
        }

        if (!empty($template) && file_exists($template)) {
            tourivo_get_template(basename($template), $args, dirname($template));
        }
    }
}

if (!function_exists('tourivo_format_price')) {
    /**
     * Format a price with currency symbol and formatting rules.
     *
     * @param float       $amount   Monetary amount.
     * @param string|null $currency Currency code or symbol (optional).
     * @return string
     */
    function tourivo_format_price(float $amount, ?string $currency = null): string
    {
        return Money::format($amount, $currency);
    }
}

if (!function_exists('tourivo_get_tour')) {
    /**
     * Get a Tour model instance by ID or current post.
     *
     * @param int|null $postId
     * @return Tour|null
     */
    function tourivo_get_tour(?int $postId = null): ?Tour
    {
        $id = $postId ?: (int) get_the_ID();
        if ($id <= 0 || get_post_type($id) !== 'tourivo_tour') {
            return null;
        }

        return new Tour($id);
    }
}

if (!function_exists('tourivo_get_hotel')) {
    /**
     * Get a Hotel model instance by ID or current post.
     *
     * @param int|null $postId
     * @return Hotel|null
     */
    function tourivo_get_hotel(?int $postId = null): ?Hotel
    {
        $id = $postId ?: (int) get_the_ID();
        if ($id <= 0 || get_post_type($id) !== 'tourivo_hotel') {
            return null;
        }

        return new Hotel($id);
    }
}

if (!function_exists('tourivo_get_room')) {
    /**
     * Get a Room model instance by ID or current post.
     *
     * @param int|null $postId
     * @return Room|null
     */
    function tourivo_get_room(?int $postId = null): ?Room
    {
        $id = $postId ?: (int) get_the_ID();
        if ($id <= 0 || get_post_type($id) !== 'tourivo_room') {
            return null;
        }

        return new Room($id);
    }
}

if (!function_exists('tourivo_get_booking')) {
    /**
     * Retrieve booking object from database by numeric ID.
     *
     * @param int $bookingId
     * @return object|null
     */
    function tourivo_get_booking(int $bookingId): ?object
    {
        global $wpdb;
        if ($bookingId <= 0 || !$wpdb) {
            return null;
        }

        $table = $wpdb->prefix . 'tourivo_bookings';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $bookingId));

        return $row ?: null;
    }
}

if (!function_exists('tourivo_get_booking_by_code')) {
    /**
     * Retrieve booking object by reference code.
     *
     * @param string $bookingCode
     * @return object|null
     */
    function tourivo_get_booking_by_code(string $bookingCode): ?object
    {
        global $wpdb;
        $bookingCode = sanitize_text_field($bookingCode);
        if (empty($bookingCode) || !$wpdb) {
            return null;
        }

        $table = $wpdb->prefix . 'tourivo_bookings';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE booking_code = %s LIMIT 1", $bookingCode));

        return $row ?: null;
    }
}

if (!function_exists('tourivo_is_tour')) {
    /**
     * Check if currently viewing a single tour or tour archive.
     *
     * @param int|null $postId
     * @return bool
     */
    function tourivo_is_tour(?int $postId = null): bool
    {
        if ($postId !== null) {
            return get_post_type($postId) === 'tourivo_tour';
        }

        return is_singular('tourivo_tour') || is_post_type_archive('tourivo_tour') || is_tax(['tourivo_destination', 'tourivo_activity']);
    }
}

if (!function_exists('tourivo_is_hotel')) {
    /**
     * Check if currently viewing a single hotel or hotel archive.
     *
     * @param int|null $postId
     * @return bool
     */
    function tourivo_is_hotel(?int $postId = null): bool
    {
        if ($postId !== null) {
            return get_post_type($postId) === 'tourivo_hotel';
        }

        return is_singular('tourivo_hotel') || is_post_type_archive('tourivo_hotel') || is_tax(['tourivo_hotel_type', 'tourivo_amenity']);
    }
}

if (!function_exists('tourivo_get_destinations')) {
    /**
     * Get list of all tour destinations.
     *
     * @param array<string, mixed> $args
     * @return array<\WP_Term>
     */
    function tourivo_get_destinations(array $args = []): array
    {
        $defaults = [
            'taxonomy'   => 'tourivo_destination',
            'hide_empty' => false,
        ];
        $parsed = wp_parse_args($args, $defaults);
        $terms = get_terms($parsed);

        return is_array($terms) ? $terms : [];
    }
}

if (!function_exists('tourivo_get_activities')) {
    /**
     * Get list of all tour activities.
     *
     * @param array<string, mixed> $args
     * @return array<\WP_Term>
     */
    function tourivo_get_activities(array $args = []): array
    {
        $defaults = [
            'taxonomy'   => 'tourivo_activity',
            'hide_empty' => false,
        ];
        $parsed = wp_parse_args($args, $defaults);
        $terms = get_terms($parsed);

        return is_array($terms) ? $terms : [];
    }
}

if (!function_exists('tourivo_render_wishlist_button')) {
    /**
     * Render a reactive wishlist heart button for any item card or template.
     *
     * @param int    $itemId   Tour or Hotel Post ID.
     * @param string $itemType 'tour' or 'hotel'.
     * @param string $cssClass Additional custom CSS classes.
     * @return string
     */
    function tourivo_render_wishlist_button(int $itemId, string $itemType = 'tour', string $cssClass = ''): string
    {
        $itemId   = absint($itemId);
        $itemType = esc_attr($itemType);
        $classes  = 'tourivo-card-wishlist-btn ' . esc_attr($cssClass);

        return sprintf(
            '<button type="button" class="%s" data-id="%d" data-type="%s" aria-label="%s" title="%s"><span class="dashicons dashicons-heart"></span></button>',
            $classes,
            $itemId,
            $itemType,
            esc_attr__('Save to wishlist', 'tourivo'),
            esc_attr__('Save to wishlist', 'tourivo')
        );
    }
}

if (!function_exists('tourivo_bn_number')) {
    /**
     * Convert English numbers to Bengali digits.
     *
     * @param int|float|string $number
     * @return string
     */
    function tourivo_bn_number($number): string
    {
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        return str_replace($en, $bn, (string) $number);
    }
}

if (!function_exists('tourivo_current_locale')) {
    /**
     * Get the active plugin locale code (e.g. 'en_US', 'bn_BD').
     *
     * @return string
     */
    function tourivo_current_locale(): string
    {
        $lang = (string) Config::get('plugin_language', 'default');
        if ('bn' === $lang) {
            return 'bn_BD';
        }
        if ('en' === $lang) {
            return 'en_US';
        }

        return function_exists('determine_locale') ? determine_locale() : get_locale();
    }
}

if (!function_exists('tourivo_is_bengali')) {
    /**
     * Check if the current plugin language is Bengali.
     *
     * @return bool
     */
    function tourivo_is_bengali(): bool
    {
        $locale = tourivo_current_locale();
        return str_starts_with($locale, 'bn');
    }
}

