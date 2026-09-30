<?php

declare(strict_types=1);

namespace Tourivo\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RoomPostType
 *
 * Registers the 'tourivo_room' custom post type.
 *
 * @package Tourivo\PostTypes
 */
class RoomPostType
{
    public const POST_TYPE = 'tourivo_room';

    /**
     * Register post type.
     *
     * @return void
     */
    public static function register(): void
    {
        $labels = [
            'name'                  => _x('Rooms & Units', 'Post type general name', 'tourivo'),
            'singular_name'         => _x('Room', 'Post type singular name', 'tourivo'),
            'menu_name'             => _x('Rooms', 'Admin Menu text', 'tourivo'),
            'name_admin_bar'        => _x('Room', 'Add New on Toolbar', 'tourivo'),
            'add_new'               => __('Add New Room', 'tourivo'),
            'add_new_item'          => __('Add New Room', 'tourivo'),
            'new_item'              => __('New Room', 'tourivo'),
            'edit_item'             => __('Edit Room', 'tourivo'),
            'view_item'             => __('View Room', 'tourivo'),
            'all_items'             => __('All Rooms', 'tourivo'),
            'search_items'          => __('Search Rooms', 'tourivo'),
            'not_found'             => __('No rooms found.', 'tourivo'),
            'not_found_in_trash'    => __('No rooms found in Trash.', 'tourivo'),
            'featured_image'        => _x('Room Photo', 'Overrides the "Featured Image" phrase', 'tourivo'),
            'set_featured_image'    => _x('Set room photo', 'Overrides the "Set featured image" phrase', 'tourivo'),
            'remove_featured_image' => _x('Remove room photo', 'Overrides the "Remove featured image" phrase', 'tourivo'),
            'use_featured_image'    => _x('Use as room photo', 'Overrides the "Use as featured image" phrase', 'tourivo'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'tourivo',
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'exclude_from_search'=> true,
            'menu_position'      => 28,
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'show_in_rest'       => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }
}
