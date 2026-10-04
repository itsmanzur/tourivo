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
        tourivo_get_template('booking-panel.php', [
            'tourivoItemId'   => $itemId,
            'tourivoItemType' => $itemType,
        ]);

        return ob_get_clean() ?: '';
    }
}
