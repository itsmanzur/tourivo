<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookingPanelShortcode
 *
 * Shortcode: [tourivo_booking_panel item_id="..." item_type="tour|hotel_room"]
 *
 * @package Tourivo\Shortcodes
 */
class BookingPanelShortcode
{
    public static function register(): void
    {
        add_shortcode('tourivo_booking_panel', [self::class, 'render']);
    }

    /**
     * Render shortcode output.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public static function render(array|string $atts = []): string
    {
        $attributes = shortcode_atts([
            'item_id'   => get_the_ID(),
            'item_type' => 'tour',
        ], (array) $atts, 'tourivo_booking_panel');

        $itemId   = (int) $attributes['item_id'];
        $itemType = sanitize_text_field((string) $attributes['item_type']);

        if ($itemId <= 0) {
            return '';
        }

        ob_start();
        $template = untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/booking-panel.php';
        $themeTemplate = locate_template(['tourivo/booking-panel.php']);
        if (!empty($themeTemplate) && file_exists($themeTemplate)) {
            $template = $themeTemplate;
        }

        if (file_exists($template)) {
            include $template;
        }

        return ob_get_clean() ?: '';
    }
}
