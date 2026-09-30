<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

use Tourivo\Models\Hotel;
use Tourivo\Models\Tour;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\TourPostType;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FilterSearchShortcode
 *
 * Shortcode: [tourivo_filter_search type="tour" columns="3" count="9" layout="top"]
 *
 * @package Tourivo\Shortcodes
 */
class FilterSearchShortcode
{
    public static function register(): void
    {
        add_shortcode('tourivo_filter_search', [self::class, 'render']);
    }

    /**
     * Render the Live Search & AJAX Filter interface.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public static function render(array|string $atts = []): string
    {
        $attributes = shortcode_atts([
            'type'    => 'tour', // 'tour' or 'hotel'
            'columns' => 3,
            'count'   => 9,
            'layout'  => 'top', // 'top' or 'sidebar'
        ], (array) $atts, 'tourivo_filter_search');

        $type = ($attributes['type'] === 'hotel') ? 'hotel' : 'tour';
        $cols = max(1, min(4, (int) $attributes['columns']));
        $count = max(1, min(50, (int) $attributes['count']));
        $layout = $attributes['layout'] === 'sidebar' ? 'sidebar' : 'top';

        $destinations = get_terms([
            'taxonomy'   => TourPostType::TAX_DESTINATION,
            'hide_empty' => false,
        ]);

        $activities = ($type === 'tour') ? get_terms([
            'taxonomy'   => TourPostType::TAX_ACTIVITY,
            'hide_empty' => false,
        ]) : [];

        $currency = (string) apply_filters('tourivo/currency_symbol', '$');

        ob_start();
        ?>
        <div class="tourivo-live-filter-wrapper tourivo-layout-<?php echo esc_attr($layout); ?>" 
             data-type="<?php echo esc_attr($type); ?>" 
             data-columns="<?php echo esc_attr((string) $cols); ?>" 
             data-count="<?php echo esc_attr((string) $count); ?>">

            <!-- Filter Controls Bar -->
            <form class="tourivo-filter-form" onsubmit="return false;">
                <div class="tourivo-filter-grid">
                    <!-- Keyword Search -->
                    <div class="tourivo-filter-col filter-keyword">
                        <label for="filter-keyword-<?php echo esc_attr($type); ?>">🔍 <?php esc_html_e('Search', 'tourivo'); ?></label>
                        <input type="text" id="filter-keyword-<?php echo esc_attr($type); ?>" name="s" class="tourivo-filter-input" placeholder="<?php echo $type === 'hotel' ? esc_attr__('Search hotels, cities...', 'tourivo') : esc_attr__('Search trips, keywords...', 'tourivo'); ?>">
                    </div>

                    <!-- Destination -->
                    <div class="tourivo-filter-col filter-destination">
                        <label for="filter-dest-<?php echo esc_attr($type); ?>">📍 <?php esc_html_e('Destination', 'tourivo'); ?></label>
                        <select id="filter-dest-<?php echo esc_attr($type); ?>" name="destination" class="tourivo-filter-select">
                            <option value=""><?php esc_html_e('All Destinations', 'tourivo'); ?></option>
                            <?php if (!empty($destinations) && !is_wp_error($destinations)) : foreach ($destinations as $dest) : ?>
                                <option value="<?php echo esc_attr($dest->slug); ?>"><?php echo esc_html($dest->name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <?php if ($type === 'tour') : ?>
                        <!-- Activity Type -->
                        <div class="tourivo-filter-col filter-activity">
                            <label for="filter-act-<?php echo esc_attr($type); ?>">🏷️ <?php esc_html_e('Activity', 'tourivo'); ?></label>
                            <select id="filter-act-<?php echo esc_attr($type); ?>" name="activity" class="tourivo-filter-select">
                                <option value=""><?php esc_html_e('All Activities', 'tourivo'); ?></option>
                                <?php if (!empty($activities) && !is_wp_error($activities)) : foreach ($activities as $act) : ?>
                                    <option value="<?php echo esc_attr($act->slug); ?>"><?php echo esc_html($act->name); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>

                        <!-- Duration Filter -->
                        <div class="tourivo-filter-col filter-duration">
                            <label for="filter-dur-<?php echo esc_attr($type); ?>">⏱️ <?php esc_html_e('Duration', 'tourivo'); ?></label>
                            <select id="filter-dur-<?php echo esc_attr($type); ?>" name="duration" class="tourivo-filter-select">
                                <option value=""><?php esc_html_e('Any Duration', 'tourivo'); ?></option>
                                <option value="short"><?php esc_html_e('1 - 3 Days', 'tourivo'); ?></option>
                                <option value="medium"><?php esc_html_e('4 - 7 Days', 'tourivo'); ?></option>
                                <option value="long"><?php esc_html_e('8+ Days', 'tourivo'); ?></option>
                            </select>
                        </div>
                    <?php else : ?>
                        <!-- Star Rating for Hotels -->
                        <div class="tourivo-filter-col filter-rating">
                            <label for="filter-stars-<?php echo esc_attr($type); ?>">⭐ <?php esc_html_e('Star Rating', 'tourivo'); ?></label>
                            <select id="filter-stars-<?php echo esc_attr($type); ?>" name="star_rating" class="tourivo-filter-select">
                                <option value=""><?php esc_html_e('All Ratings', 'tourivo'); ?></option>
                                <option value="5">★★★★★ (5 Star)</option>
                                <option value="4">★★★★☆ (4+ Star)</option>
                                <option value="3">★★★☆☆ (3+ Star)</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Price Range Slider -->
                    <div class="tourivo-filter-col filter-price">
                        <div class="price-header-labels">
                            <label>💰 <?php esc_html_e('Max Budget:', 'tourivo'); ?></label>
                            <span class="tourivo-price-display"><strong><?php echo esc_html($currency); ?><span class="price-val-indicator">2000</span></strong></span>
                        </div>
                        <input type="range" name="max_price" class="tourivo-filter-range" min="0" max="5000" step="50" value="5000">
                    </div>

                    <!-- Sort Order -->
                    <div class="tourivo-filter-col filter-sort">
                        <label for="filter-sort-<?php echo esc_attr($type); ?>">🔀 <?php esc_html_e('Sort By', 'tourivo'); ?></label>
                        <select id="filter-sort-<?php echo esc_attr($type); ?>" name="orderby" class="tourivo-filter-select">
                            <option value="newest"><?php esc_html_e('Newest First', 'tourivo'); ?></option>
                            <option value="price_low"><?php esc_html_e('Price: Low to High', 'tourivo'); ?></option>
                            <option value="price_high"><?php esc_html_e('Price: High to Low', 'tourivo'); ?></option>
                            <option value="rating"><?php esc_html_e('Customer Rating', 'tourivo'); ?></option>
                            <option value="title"><?php esc_html_e('Title: A to Z', 'tourivo'); ?></option>
                        </select>
                    </div>

                    <!-- Reset Button -->
                    <div class="tourivo-filter-col filter-reset">
                        <button type="button" class="tourivo-btn-reset-filters">
                            <span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e('Reset', 'tourivo'); ?>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Results Counter & Live Grid -->
            <div class="tourivo-filter-results-bar">
                <p class="tourivo-results-counter">
                    <span class="dashicons dashicons-list-view"></span> <span class="counter-num">...</span> <?php esc_html_e('items found', 'tourivo'); ?>
                </p>
            </div>

            <div class="tourivo-filter-grid-container" style="position: relative; min-height: 200px;">
                <div class="tourivo-filter-loader" style="display: none;">
                    <span class="dashicons dashicons-update spin"></span>
                </div>
                <div class="tourivo-grid tourivo-live-results" style="--trv-grid-cols: <?php echo esc_attr((string)$cols); ?>;">
                    <!-- Pre-rendered Initial Posts -->
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo self::queryAndRender([
                        'type'     => sanitize_key($type),
                        'columns'  => absint($cols),
                        'count'    => absint($count),
                    ]);
                    ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Query and render cards HTML for filter requests.
     *
     * @param array<string, mixed> $params
     * @return string
     */
    public static function queryAndRender(array $params): string
    {
        $type = ($params['type'] ?? 'tour') === 'hotel' ? 'hotel' : 'tour';
        $postType = ($type === 'hotel') ? HotelPostType::POST_TYPE : TourPostType::POST_TYPE;
        $count = max(1, min(50, (int) ($params['count'] ?? 9)));
        $keyword = sanitize_text_field((string) ($params['s'] ?? ''));
        $destination = sanitize_text_field((string) ($params['destination'] ?? ''));
        $activity = sanitize_text_field((string) ($params['activity'] ?? ''));
        $duration = sanitize_text_field((string) ($params['duration'] ?? ''));
        $starRating = (int) ($params['star_rating'] ?? 0);
        $maxPrice = isset($params['max_price']) ? (float) $params['max_price'] : 0.0;
        $orderby = sanitize_text_field((string) ($params['orderby'] ?? 'newest'));

        $args = [
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => $count,
        ];

        if (!empty($keyword)) {
            $args['s'] = $keyword;
        }

        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        $taxQuery = [];
        if (!empty($destination)) {
            $taxQuery[] = [
                'taxonomy' => TourPostType::TAX_DESTINATION,
                'field'    => 'slug',
                'terms'    => $destination,
            ];
        }
        if ($type === 'tour' && !empty($activity)) {
            $taxQuery[] = [
                'taxonomy' => TourPostType::TAX_ACTIVITY,
                'field'    => 'slug',
                'terms'    => $activity,
            ];
        }
        if (!empty($taxQuery)) {
            $args['tax_query'] = $taxQuery;
        }

        $metaQuery = [];
        if ($type === 'tour' && $maxPrice > 0) {
            $metaQuery[] = [
                'key'     => '_tourivo_base_price',
                'value'   => $maxPrice,
                'type'    => 'NUMERIC',
                'compare' => '<=',
            ];
        } elseif ($type === 'hotel' && $maxPrice > 0) {
            $metaQuery[] = [
                'key'     => '_tourivo_min_price',
                'value'   => $maxPrice,
                'type'    => 'NUMERIC',
                'compare' => '<=',
            ];
        }

        if ($type === 'tour' && !empty($duration)) {
            if ($duration === 'short') {
                $metaQuery[] = [
                    'key'     => '_tourivo_duration_days',
                    'value'   => [1, 3],
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN',
                ];
            } elseif ($duration === 'medium') {
                $metaQuery[] = [
                    'key'     => '_tourivo_duration_days',
                    'value'   => [4, 7],
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN',
                ];
            } elseif ($duration === 'long') {
                $metaQuery[] = [
                    'key'     => '_tourivo_duration_days',
                    'value'   => 8,
                    'type'    => 'NUMERIC',
                    'compare' => '>=',
                ];
            }
        }

        if ($type === 'hotel' && $starRating > 0) {
            $metaQuery[] = [
                'key'     => '_tourivo_star_rating',
                'value'   => $starRating,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        if (!empty($metaQuery)) {
            $args['meta_query'] = $metaQuery;
        }

        // Sorting
        switch ($orderby) {
            case 'price_low':
                $args['meta_key'] = ($type === 'tour') ? '_tourivo_base_price' : '_tourivo_min_price';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
                break;
            case 'price_high':
                $args['meta_key'] = ($type === 'tour') ? '_tourivo_base_price' : '_tourivo_min_price';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            case 'rating':
                $args['meta_key'] = '_tourivo_average_rating';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            case 'title':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
            case 'newest':
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
        }

        $query = new WP_Query($args);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_tax_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key

        if (!$query->have_posts()) {
            return '<div class="tourivo-no-results-msg" style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; color: #64748b;">
                <div style="font-size: 36px; margin-bottom: 8px;">🔍</div>
                <p><strong>' . esc_html__('No results found matching your filters.', 'tourivo') . '</strong></p>
                <small>' . esc_html__('Try adjusting your keywords, budget, or destination criteria.', 'tourivo') . '</small>
            </div>';
        }

        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            $post = get_post();
            if ($type === 'tour') {
                $tour = new Tour($post);
                include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/cards/tour-card.php';
            } else {
                $hotel = new Hotel($post);
                include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/cards/hotel-card.php';
            }
        }
        wp_reset_postdata();

        return ob_get_clean() ?: '';
    }
}
