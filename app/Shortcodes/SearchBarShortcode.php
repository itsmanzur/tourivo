<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SearchBarShortcode
 *
 * Shortcode: [tourivo_search_bar]
 *
 * @package Tourivo\Shortcodes
 */
class SearchBarShortcode
{
    public static function register(): void
    {
        add_shortcode('tourivo_search_bar', [self::class, 'render']);
    }

    /**
     * Render search bar.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public static function render(array|string $atts = []): string
    {
        ob_start();
        tourivo_get_template('search-bar.php');

        return ob_get_clean() ?: '';
    }
}
