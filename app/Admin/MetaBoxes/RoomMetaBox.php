<?php

declare(strict_types=1);

namespace Tourivo\Admin\MetaBoxes;

use Tourivo\Common\Abstracts\MetaBox;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\RoomPostType;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RoomMetaBox
 *
 * Handles Room settings metabox: Parent Hotel link, Nightly price, Max capacity, Beds, Size, Quantity.
 * Also maintains the cached minimum room price on the parent Hotel post across all room lifecycle events.
 *
 * @package Tourivo\Admin\MetaBoxes
 */
class RoomMetaBox extends MetaBox
{
    protected string $id = 'tourivo_room_settings';
    protected string $title = 'Room Configuration & Pricing';
    protected string|array $postTypes = RoomPostType::POST_TYPE;
    protected string $context = 'normal';
    protected string $priority = 'high';

    public function __construct()
    {
        parent::__construct();
        $this->addAction('transition_post_status', [$this, 'onRoomStatusTransition'], 10, 3);
        $this->addAction('deleted_post', [$this, 'onRoomDeleted'], 10, 2);
        $this->addAction('trashed_post', [$this, 'onRoomTrashed'], 10, 1);
        $this->addAction('untrashed_post', [$this, 'onRoomUntrashed'], 10, 1);
    }

    public function render(WP_Post $post): void
    {
        $parentHotelId = (int) (get_post_meta($post->ID, '_tourivo_parent_hotel_id', true) ?: 0);
        $nightlyPrice  = get_post_meta($post->ID, '_tourivo_nightly_price', true) ?: '';
        $maxAdults     = get_post_meta($post->ID, '_tourivo_max_adults', true) ?: '2';
        $maxChildren   = get_post_meta($post->ID, '_tourivo_max_children', true) ?: '0';
        $maxGuests     = get_post_meta($post->ID, '_tourivo_max_guests', true) ?: '2';
        $roomQuantity  = get_post_meta($post->ID, '_tourivo_room_quantity', true) ?: '1';
        $roomSize      = get_post_meta($post->ID, '_tourivo_room_size', true) ?: '';
        $bedType       = get_post_meta($post->ID, '_tourivo_bed_type', true) ?: '';

        // Query available hotels for dropdown
        $hotelsQuery = new WP_Query([
            'post_type'      => HotelPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $hotels = $hotelsQuery->posts;

        $this->loadView('admin/metaboxes/room-metabox.php', [
            'post'          => $post,
            'parentHotelId' => $parentHotelId,
            'nightlyPrice'  => $nightlyPrice,
            'maxAdults'     => $maxAdults,
            'maxChildren'   => $maxChildren,
            'maxGuests'     => $maxGuests,
            'roomQuantity'  => $roomQuantity,
            'roomSize'      => $roomSize,
            'bedType'       => $bedType,
            'hotels'        => $hotels,
        ]);
    }

    public function save(int $postId, WP_Post $post): void
    {
        $oldParentHotelId = (int) (get_post_meta($postId, '_tourivo_parent_hotel_id', true) ?: 0);

        $fields = [
            '_tourivo_parent_hotel_id' => 'absint',
            '_tourivo_nightly_price'   => static fn ($v) => number_format(max(0.0, (float) str_replace(',', '', (string) $v)), 2, '.', ''),
            '_tourivo_max_adults'      => 'absint',
            '_tourivo_max_children'    => 'absint',
            '_tourivo_max_guests'      => 'absint',
            '_tourivo_room_quantity'   => 'absint',
            '_tourivo_room_size'       => 'sanitize_text_field',
            '_tourivo_bed_type'        => 'sanitize_text_field',
        ];

        foreach ($fields as $field => $sanitizer) {
            if (isset($_POST[$field])) {
                $val = wp_unslash($_POST[$field]);
                $cleanVal = is_callable($sanitizer) ? $sanitizer($val) : sanitize_text_field($val);
                update_post_meta($postId, $field, $cleanVal);
            }
        }

        $newParentHotelId = isset($_POST['_tourivo_parent_hotel_id']) ? absint($_POST['_tourivo_parent_hotel_id']) : 0;

        if ($newParentHotelId > 0) {
            self::updateHotelMinPrice($newParentHotelId);
        }
        if ($oldParentHotelId > 0 && $oldParentHotelId !== $newParentHotelId) {
            self::updateHotelMinPrice($oldParentHotelId);
        }

        do_action('tourivo_save_room_meta', $postId, $post);
    }

    /**
     * Recompute parent hotel min price when a room's post status changes.
     */
    public function onRoomStatusTransition(string $newStatus, string $oldStatus, WP_Post $post): void
    {
        if ($post->post_type !== RoomPostType::POST_TYPE || $newStatus === $oldStatus) {
            return;
        }

        $parentHotelId = (int) get_post_meta($post->ID, '_tourivo_parent_hotel_id', true);
        if ($parentHotelId > 0) {
            self::updateHotelMinPrice($parentHotelId);
        }
    }

    /**
     * Recompute parent hotel min price when a room is permanently deleted.
     */
    public function onRoomDeleted(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== RoomPostType::POST_TYPE) {
            return;
        }

        $parentHotelId = (int) get_post_meta($postId, '_tourivo_parent_hotel_id', true);
        if ($parentHotelId > 0) {
            self::updateHotelMinPrice($parentHotelId);
        }
    }

    /**
     * Recompute parent hotel min price when a room is moved to trash.
     */
    public function onRoomTrashed(int $postId): void
    {
        if (get_post_type($postId) !== RoomPostType::POST_TYPE) {
            return;
        }

        $parentHotelId = (int) get_post_meta($postId, '_tourivo_parent_hotel_id', true);
        if ($parentHotelId > 0) {
            self::updateHotelMinPrice($parentHotelId);
        }
    }

    /**
     * Recompute parent hotel min price when a room is untrashed.
     */
    public function onRoomUntrashed(int $postId): void
    {
        if (get_post_type($postId) !== RoomPostType::POST_TYPE) {
            return;
        }

        $parentHotelId = (int) get_post_meta($postId, '_tourivo_parent_hotel_id', true);
        if ($parentHotelId > 0) {
            self::updateHotelMinPrice($parentHotelId);
        }
    }

    /**
     * Compute and cache minimum room nightly price on parent Hotel post.
     * Deletes the meta key if no published rooms with price > 0 remain.
     *
     * @param int $hotelId
     * @return void
     */
    public static function updateHotelMinPrice(int $hotelId): void
    {
        if ($hotelId <= 0) {
            return;
        }

        global $wpdb;
        $minPrice = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(CAST(pm_price.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_hotel ON p.ID = pm_hotel.post_id AND pm_hotel.meta_key = '_tourivo_parent_hotel_id'
             INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_tourivo_nightly_price'
             WHERE p.post_type = %s AND p.post_status = 'publish' AND pm_hotel.meta_value = %d AND CAST(pm_price.meta_value AS DECIMAL(10,2)) > 0",
            RoomPostType::POST_TYPE,
            $hotelId
        ));

        if ($minPrice !== null && is_numeric($minPrice)) {
            update_post_meta($hotelId, '_tourivo_min_price', (float) $minPrice);
        } else {
            delete_post_meta($hotelId, '_tourivo_min_price');
        }
    }
}
