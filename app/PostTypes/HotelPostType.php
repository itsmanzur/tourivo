<?php

declare(strict_types=1);

namespace Tourivo\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HotelPostType
 *
 * Registers the 'tourivo_hotel' custom post type and its taxonomies.
 *
 * @package Tourivo\PostTypes
 */
class HotelPostType
{
    public const POST_TYPE = 'tourivo_hotel';
    public const TAX_AMENITY = 'tourivo_amenity';

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
            'name'                  => _x('Hotels & Stays', 'Post type general name', 'tourivo'),
            'singular_name'         => _x('Hotel', 'Post type singular name', 'tourivo'),
            'menu_name'             => _x('Hotels', 'Admin Menu text', 'tourivo'),
            'name_admin_bar'        => _x('Hotel', 'Add New on Toolbar', 'tourivo'),
            'add_new'               => __('Add New Hotel', 'tourivo'),
            'add_new_item'          => __('Add New Hotel', 'tourivo'),
            'new_item'              => __('New Hotel', 'tourivo'),
            'edit_item'             => __('Edit Hotel', 'tourivo'),
            'view_item'             => __('View Hotel', 'tourivo'),
            'all_items'             => __('All Hotels', 'tourivo'),
            'search_items'          => __('Search Hotels', 'tourivo'),
            'not_found'             => __('No hotels found.', 'tourivo'),
            'not_found_in_trash'    => __('No hotels found in Trash.', 'tourivo'),
            'featured_image'        => _x('Hotel Cover Image', 'Overrides the "Featured Image" phrase', 'tourivo'),
            'set_featured_image'    => _x('Set hotel cover image', 'Overrides the "Set featured image" phrase', 'tourivo'),
            'remove_featured_image' => _x('Remove hotel cover image', 'Overrides the "Remove featured image" phrase', 'tourivo'),
            'use_featured_image'    => _x('Use as hotel cover image', 'Overrides the "Use as featured image" phrase', 'tourivo'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'tourivo',
            'query_var'          => true,
            'rewrite'            => ['slug' => 'hotels', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => 'hotels',
            'hierarchical'       => false,
            'menu_position'      => 27,
            'supports'           => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields'],
            'show_in_rest'       => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register Taxonomies for Hotels & Rooms.
     *
     * @return void
     */
    protected static function registerTaxonomies(): void
    {
        register_taxonomy(self::TAX_AMENITY, [self::POST_TYPE, RoomPostType::POST_TYPE], [
            'hierarchical'      => false,
            'labels'            => [
                'name'          => _x('Amenities', 'taxonomy general name', 'tourivo'),
                'singular_name' => _x('Amenity', 'taxonomy singular name', 'tourivo'),
                'search_items'  => __('Search Amenities', 'tourivo'),
                'all_items'     => __('All Amenities', 'tourivo'),
                'edit_item'     => __('Edit Amenity', 'tourivo'),
                'update_item'   => __('Update Amenity', 'tourivo'),
                'add_new_item'  => __('Add New Amenity', 'tourivo'),
                'menu_name'     => __('Amenities', 'tourivo'),
            ],
            'show_ui'           => true,
            'show_in_menu'      => 'tourivo',
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'amenity', 'with_front' => false],
            'show_in_rest'      => true,
        ]);
    }
}
