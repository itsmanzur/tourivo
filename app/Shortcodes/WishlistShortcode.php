<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

use Tourivo\Models\Hotel;
use Tourivo\Models\Tour;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\TourPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WishlistShortcode
 *
 * Shortcode: [tourivo_wishlist columns="3"]
 *
 * @package Tourivo\Shortcodes
 */
class WishlistShortcode
{
    public static function register(): void
    {
        add_shortcode('tourivo_wishlist', [self::class, 'render']);
    }

    /**
     * Render Wishlist frontend container.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public static function render(array|string $atts = []): string
    {
        $attributes = shortcode_atts([
            'columns' => 3,
        ], (array) $atts, 'tourivo_wishlist');

        $cols = max(1, min(4, (int) $attributes['columns']));

        ob_start();
        ?>
        <div class="tourivo-wishlist-wrap" data-columns="<?php echo esc_attr((string) $cols); ?>">
            <div class="tourivo-wishlist-header">
                <h2>❤️ <?php esc_html_e('My Saved Trips & Stays', 'tourivo'); ?></h2>
                <p class="tourivo-wishlist-count-label">
                    <span id="tourivo-wishlist-total-count">0</span> <?php esc_html_e('items saved', 'tourivo'); ?>
                </p>
            </div>

            <!-- Loading Spinner -->
            <div id="tourivo-wishlist-loading" class="tourivo-loading-state" style="text-align: center; padding: 40px 0;">
                <span class="dashicons dashicons-update spin" style="font-size: 28px; width: 28px; height: 28px; color: #0284c7;"></span>
                <p style="margin-top: 10px; color: #64748b;"><?php esc_html_e('Loading your favorites...', 'tourivo'); ?></p>
            </div>

            <!-- Wishlist Grid -->
            <div id="tourivo-wishlist-grid" class="tourivo-grid" style="--trv-grid-cols: <?php echo esc_attr((string) $cols); ?>; display: none;"></div>

            <!-- Empty State -->
            <div id="tourivo-wishlist-empty" class="tourivo-empty-state" style="display: none; text-align: center; padding: 60px 20px;">
                <div style="font-size: 48px; margin-bottom: 16px;">🏖️</div>
                <h3><?php esc_html_e('Your Wishlist is Empty', 'tourivo'); ?></h3>
                <p style="color: #64748b; max-width: 480px; margin: 0 auto 20px auto;">
                    <?php esc_html_e('Explore our exciting tour packages and luxury hotels, and click the heart icon to save your favorite itineraries here!', 'tourivo'); ?>
                </p>
                <a href="<?php echo esc_url(get_post_type_archive_link(TourPostType::POST_TYPE) ?: home_url('/')); ?>" class="tourivo-btn tourivo-btn-primary">
                    🔍 <?php esc_html_e('Explore Tours & Stays', 'tourivo'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * AJAX handler to render cards for saved item IDs.
     *
     * @param array<int> $itemIds
     * @return string HTML cards
     */
    public static function renderCardsHtml(array $itemIds): string
    {
        if (empty($itemIds)) {
            return '';
        }

        $query = new \WP_Query([
            'post_type'      => [TourPostType::POST_TYPE, HotelPostType::POST_TYPE],
            'post__in'       => array_map('intval', $itemIds),
            'posts_per_page' => -1,
            'orderby'        => 'post__in',
        ]);

        if (!$query->have_posts()) {
            return '';
        }

        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            $post = get_post();
            if ($post->post_type === TourPostType::POST_TYPE) {
                $tour = new Tour($post);
                include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/cards/tour-card.php';
            } elseif ($post->post_type === HotelPostType::POST_TYPE) {
                $hotel = new Hotel($post);
                include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/cards/hotel-card.php';
            }
        }
        wp_reset_postdata();

        return ob_get_clean() ?: '';
    }
}
