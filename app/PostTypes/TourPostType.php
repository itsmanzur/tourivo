<?php

declare(strict_types=1);

namespace Tourivo\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TourPostType
 *
 * Registers the 'tourivo_tour' custom post type and its taxonomies.
 *
 * @package Tourivo\PostTypes
 */
class TourPostType
{
    public const POST_TYPE = 'tourivo_tour';
    public const TAX_DESTINATION = 'tourivo_destination';
    public const TAX_ACTIVITY = 'tourivo_activity_type';
    public const TAX_FEATURE = 'tourivo_tour_feature';

    /**
     * Register post type and taxonomies.
     *
     * @return void
     */
    public static function register(): void
    {
        self::registerTaxonomies();
        self::registerPostType();
    }

    /**
     * Register Custom Post Type.
     *
     * @return void
     */
    protected static function registerPostType(): void
    {
        $labels = [
            'name'                  => _x('Tours & Packages', 'Post type general name', 'tourivo'),
            'singular_name'         => _x('Tour', 'Post type singular name', 'tourivo'),
            'menu_name'             => _x('Tours', 'Admin Menu text', 'tourivo'),
            'name_admin_bar'        => _x('Tour', 'Add New on Toolbar', 'tourivo'),
            'add_new'               => __('Add New Tour', 'tourivo'),
            'add_new_item'          => __('Add New Tour', 'tourivo'),
            'new_item'              => __('New Tour', 'tourivo'),
            'edit_item'             => __('Edit Tour', 'tourivo'),
            'view_item'             => __('View Tour', 'tourivo'),
            'all_items'             => __('All Tours', 'tourivo'),
            'search_items'          => __('Search Tours', 'tourivo'),
            'not_found'             => __('No tours found.', 'tourivo'),
            'not_found_in_trash'    => __('No tours found in Trash.', 'tourivo'),
            'featured_image'        => _x('Tour Cover Image', 'Overrides the "Featured Image" phrase', 'tourivo'),
            'set_featured_image'    => _x('Set tour cover image', 'Overrides the "Set featured image" phrase', 'tourivo'),
            'remove_featured_image' => _x('Remove tour cover image', 'Overrides the "Remove featured image" phrase', 'tourivo'),
            'use_featured_image'    => _x('Use as tour cover image', 'Overrides the "Use as featured image" phrase', 'tourivo'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'tourivo',
            'query_var'          => true,
            'rewrite'            => ['slug' => 'tours', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => 'tours',
            'hierarchical'       => false,
            'menu_position'      => 26,
            'supports'           => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields'],
            'show_in_rest'       => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register Taxonomies for Tours.
     *
     * @return void
     */
    protected static function registerTaxonomies(): void
    {
        // 1. Destination (Hierarchical)
        register_taxonomy(self::TAX_DESTINATION, [self::POST_TYPE, HotelPostType::POST_TYPE], [
            'hierarchical'      => true,
            'labels'            => [
                'name'              => _x('Destinations', 'taxonomy general name', 'tourivo'),
                'singular_name'     => _x('Destination', 'taxonomy singular name', 'tourivo'),
                'search_items'      => __('Search Destinations', 'tourivo'),
                'all_items'         => __('All Destinations', 'tourivo'),
                'parent_item'       => __('Parent Destination', 'tourivo'),
                'parent_item_colon' => __('Parent Destination:', 'tourivo'),
                'edit_item'         => __('Edit Destination', 'tourivo'),
                'update_item'       => __('Update Destination', 'tourivo'),
                'add_new_item'      => __('Add New Destination', 'tourivo'),
                'new_item_name'     => __('New Destination Name', 'tourivo'),
                'menu_name'         => __('Destinations', 'tourivo'),
            ],
            'show_ui'           => true,
            'show_in_menu'      => 'tourivo',
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'destination', 'with_front' => false],
            'show_in_rest'      => true,
        ]);

        // 2. Activity Type (Non-hierarchical)
        register_taxonomy(self::TAX_ACTIVITY, [self::POST_TYPE], [
            'hierarchical'      => false,
            'labels'            => [
                'name'          => _x('Activities & Categories', 'taxonomy general name', 'tourivo'),
                'singular_name' => _x('Activity', 'taxonomy singular name', 'tourivo'),
                'search_items'  => __('Search Activities', 'tourivo'),
                'all_items'     => __('All Activities', 'tourivo'),
                'edit_item'     => __('Edit Activity', 'tourivo'),
                'update_item'   => __('Update Activity', 'tourivo'),
                'add_new_item'  => __('Add New Activity', 'tourivo'),
                'menu_name'     => __('Activities', 'tourivo'),
            ],
            'show_ui'           => true,
            'show_in_menu'      => 'tourivo',
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'activity', 'with_front' => false],
            'show_in_rest'      => true,
        ]);

        // 3. Tour Features (Instant Confirmation, Free Cancellation, etc.)
        register_taxonomy(self::TAX_FEATURE, [self::POST_TYPE], [
            'hierarchical'      => false,
            'labels'            => [
                'name'          => _x('Tour Features', 'taxonomy general name', 'tourivo'),
                'singular_name' => _x('Feature', 'taxonomy singular name', 'tourivo'),
                'menu_name'     => __('Tour Features', 'tourivo'),
            ],
            'show_ui'           => true,
            'show_in_menu'      => 'tourivo',
            'show_admin_column' => false,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'tour-feature', 'with_front' => false],
            'show_in_rest'      => true,
        ]);
    }
}
