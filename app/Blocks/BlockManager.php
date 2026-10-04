<?php

declare(strict_types=1);

namespace Tourivo\Blocks;

use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Shortcodes\BookingPanelShortcode;
use Tourivo\Shortcodes\CurrencySwitcherShortcode;
use Tourivo\Shortcodes\FilterSearchShortcode;
use Tourivo\Shortcodes\HotelGridShortcode;
use Tourivo\Shortcodes\SearchBarShortcode;
use Tourivo\Shortcodes\TourGridShortcode;
use Tourivo\Shortcodes\WishlistShortcode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BlockManager
 *
 * Registers server-rendered Gutenberg Core Blocks and Tourivo custom block category.
 *
 * @package Tourivo\Blocks
 */
class BlockManager
{
    /**
     * Register Tourivo custom block category.
     *
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    public static function registerBlockCategory(array $categories): array
    {
        return array_merge([
            [
                'slug'  => 'tourivo',
                'title' => __('Tourivo Travel Engine', 'tourivo'),
                'icon'  => 'palmtree',
            ],
        ], $categories);
    }

    /**
     * Register all Tourivo Gutenberg dynamic blocks.
     *
     * @return void
     */
    public static function registerBlocks(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        // Register custom category filter for WordPress 5.8+
        add_filter('block_categories_all', [self::class, 'registerBlockCategory']);

        // 1. Tour Grid Block
        register_block_type('tourivo/tour-grid', [
            'api_version'     => 2,
            'title'           => __('Tourivo Tour Grid', 'tourivo'),
            'category'        => 'tourivo',
            'icon'            => 'palmtree',
            'description'     => __('Display tours in a modern responsive grid layout.', 'tourivo'),
            'render_callback' => [TourGridShortcode::class, 'render'],
            'attributes'      => [
                'count'       => ['type' => 'number', 'default' => 6],
                'columns'     => ['type' => 'number', 'default' => 3],
                'destination' => ['type' => 'string', 'default' => ''],
                'activity'    => ['type' => 'string', 'default' => ''],
            ],
        ]);

        // 2. Hotel Grid Block
        register_block_type('tourivo/hotel-grid', [
            'api_version'     => 2,
            'title'           => __('Tourivo Hotel Grid', 'tourivo'),
            'category'        => 'tourivo',
            'icon'            => 'building',
            'description'     => __('Display hotels and resorts in a responsive grid.', 'tourivo'),
            'render_callback' => [HotelGridShortcode::class, 'render'],
            'attributes'      => [
                'count'       => ['type' => 'number', 'default' => 6],
                'columns'     => ['type' => 'number', 'default' => 3],
                'destination' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        // 3. Search Bar Block
        register_block_type('tourivo/search-bar', [
            'api_version'     => 2,
            'title'           => __('Tourivo Search Bar', 'tourivo'),
            'category'        => 'tourivo',
            'icon'            => 'search',
            'description'     => __('Destination, date and traveler search filter bar.', 'tourivo'),
            'render_callback' => [SearchBarShortcode::class, 'render'],
        ]);

        // 4. Live Filter Search Grid Block
        register_block_type('tourivo/filter-search', [
            'api_version'     => 2,
            'title'           => __('Tourivo Filter & Search Grid', 'tourivo'),
            'category'        => 'tourivo',
            'icon'            => 'filter',
            'description'     => __('Live AJAX filtering by destination, price slider, and duration.', 'tourivo'),
            'render_callback' => [FilterSearchShortcode::class, 'render'],
            'attributes'      => [
                'type'    => ['type' => 'string', 'default' => 'tour'],
                'columns' => ['type' => 'number', 'default' => 3],
            ],
        ]);

        // 5. Booking Panel Block
        register_block_type('tourivo/booking-panel', [
            'api_version'     => 2,
            'title'           => __('Tourivo Booking Panel', 'tourivo'),
            'category'        => 'tourivo',
            'icon'            => 'calendar-alt',
            'description'     => __('Interactive booking panel with instant pricing and checkout.', 'tourivo'),
            'render_callback' => [BookingPanelShortcode::class, 'render'],
            'attributes'      => [
                'item_id'   => ['type' => 'number', 'default' => 0],
                'item_type' => ['type' => 'string', 'default' => 'tour'],
            ],
        ]);

        // 6. Track Booking Portal Block
        register_block_type('tourivo/booking-lookup', [
            'api_version'     => 2,
            'title'           => __('Tourivo Track My Booking', 'tourivo'),
            'category'        => 'tourivo',
            'icon'            => 'tickets-alt',
            'description'     => __('Self-service portal for travelers to check reservation status and download vouchers.', 'tourivo'),
            'render_callback' => [BookingLookupShortcode::class, 'render'],
        ]);

        // 7. Traveler Wishlist Block
        register_block_type('tourivo/wishlist', [
            'api_version'     => 2,
            'title'           => __('Tourivo Traveler Wishlist', 'tourivo'),
            'category'        => 'tourivo',
            'icon'            => 'heart',
            'description'     => __('Display saved favorite tours and hotels for the traveler.', 'tourivo'),
            'render_callback' => [WishlistShortcode::class, 'render'],
            'attributes'      => [
                'columns' => ['type' => 'number', 'default' => 3],
            ],
        ]);
    }
}
