<?php

declare(strict_types=1);

namespace Tourivo\Blocks;

use Tourivo\Shortcodes\BookingPanelShortcode;
use Tourivo\Shortcodes\HotelGridShortcode;
use Tourivo\Shortcodes\SearchBarShortcode;
use Tourivo\Shortcodes\TourGridShortcode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BlockManager
 *
 * Registers server-rendered Gutenberg Core Blocks.
 *
 * @package Tourivo\Blocks
 */
class BlockManager
{
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

        // 1. Tour Grid Block
        register_block_type('tourivo/tour-grid', [
            'api_version'     => 2,
            'title'           => __('Tourivo Tour Grid', 'tourivo'),
            'category'        => 'widgets',
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
            'category'        => 'widgets',
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
            'category'        => 'widgets',
            'icon'            => 'search',
            'description'     => __('Destination, date and traveler search filter bar.', 'tourivo'),
            'render_callback' => [SearchBarShortcode::class, 'render'],
        ]);

        // 4. Booking Panel Block
        register_block_type('tourivo/booking-panel', [
            'api_version'     => 2,
            'title'           => __('Tourivo Booking Panel', 'tourivo'),
            'category'        => 'widgets',
            'icon'            => 'calendar-alt',
            'description'     => __('Interactive booking panel with instant pricing and checkout.', 'tourivo'),
            'render_callback' => [BookingPanelShortcode::class, 'render'],
            'attributes'      => [
                'item_id'   => ['type' => 'number', 'default' => 0],
                'item_type' => ['type' => 'string', 'default' => 'tour'],
            ],
        ]);
    }
}
