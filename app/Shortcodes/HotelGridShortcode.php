<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

use Tourivo\Models\Hotel;
use Tourivo\PostTypes\HotelPostType;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HotelGridShortcode
 *
 * Shortcode: [tourivo_hotels columns="3" count="6" destination="..."]
 *
 * @package Tourivo\Shortcodes
 */
class HotelGridShortcode
{
    public static function register(): void
    {
        add_shortcode('tourivo_hotels', [self::class, 'render']);
    }

    /**
     * Render hotel grid.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public static function render(array|string $atts = []): string
    {
        $attributes = shortcode_atts([
            'count'       => 6,
            'columns'     => 3,
            'destination' => '',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ], (array) $atts, 'tourivo_hotels');

        $args = [
            'post_type'      => HotelPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => (int) $attributes['count'],
            'orderby'        => sanitize_text_field((string) $attributes['orderby']),
            'order'          => sanitize_text_field((string) $attributes['order']),
        ];

        if (!empty($attributes['destination'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'tourivo_destination',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field((string) $attributes['destination']),
                ],
            ];
        }

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return '<p class="tourivo-no-items">' . esc_html__('No hotels found matching criteria.', 'tourivo') . '</p>';
        }

        $cols = max(1, min(4, (int) $attributes['columns']));

        ob_start();
        echo '<div class="tourivo-grid" style="--trv-grid-cols: ' . esc_attr((string)$cols) . ';">';
        while ($query->have_posts()) {
            $query->the_post();
            $hotel = new Hotel(get_post());
            include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/cards/hotel-card.php';
        }
        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean() ?: '';
    }
}
