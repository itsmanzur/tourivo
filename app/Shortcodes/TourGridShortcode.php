<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

use Tourivo\Models\Tour;
use Tourivo\PostTypes\TourPostType;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TourGridShortcode
 *
 * Shortcode: [tourivo_tours columns="3" count="6" destination="..." activity="..."]
 *
 * @package Tourivo\Shortcodes
 */
class TourGridShortcode
{
    public static function register(): void
    {
        add_shortcode('tourivo_tours', [self::class, 'render']);
    }

    /**
     * Render tour grid.
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
            'activity'    => '',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ], (array) $atts, 'tourivo_tours');

        $args = [
            'post_type'      => TourPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => (int) $attributes['count'],
            'orderby'        => sanitize_text_field((string) $attributes['orderby']),
            'order'          => sanitize_text_field((string) $attributes['order']),
        ];

        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        $taxQuery = [];
        if (!empty($attributes['destination'])) {
            $taxQuery[] = [
                'taxonomy' => TourPostType::TAX_DESTINATION,
                'field'    => 'slug',
                'terms'    => sanitize_text_field((string) $attributes['destination']),
            ];
        }
        if (!empty($attributes['activity'])) {
            $taxQuery[] = [
                'taxonomy' => TourPostType::TAX_ACTIVITY,
                'field'    => 'slug',
                'terms'    => sanitize_text_field((string) $attributes['activity']),
            ];
        }
        if (!empty($taxQuery)) {
            $args['tax_query'] = $taxQuery;
        }

        $query = new WP_Query($args);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_tax_query

        if (!$query->have_posts()) {
            return '<p class="tourivo-no-items">' . esc_html__('No tours found matching criteria.', 'tourivo') . '</p>';
        }

        $cols = max(1, min(4, (int) $attributes['columns']));

        ob_start();
        echo '<div class="tourivo-grid" style="--trv-grid-cols: ' . esc_attr((string)$cols) . ';">';
        while ($query->have_posts()) {
            $query->the_post();
            $tour = new Tour(get_post());
            tourivo_get_template('cards/tour-card.php', ['tour' => $tour]);
        }
        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean() ?: '';
    }
}
