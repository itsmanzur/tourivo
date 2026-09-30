<?php

declare(strict_types=1);

namespace Tourivo\Admin\MetaBoxes;

use Tourivo\Common\Abstracts\MetaBox;
use Tourivo\PostTypes\HotelPostType;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HotelMetaBox
 *
 * Handles Hotel settings metabox: Star rating, Address, Check-in/out times, Policies.
 *
 * @package Tourivo\Admin\MetaBoxes
 */
class HotelMetaBox extends MetaBox
{
    protected string $id = 'tourivo_hotel_settings';
    protected string $title = 'Hotel & Property Settings';
    protected string|array $postTypes = HotelPostType::POST_TYPE;
    protected string $context = 'normal';
    protected string $priority = 'high';

    public function render(WP_Post $post): void
    {
        $starRating   = get_post_meta($post->ID, '_tourivo_star_rating', true) ?: '3';
        $address      = get_post_meta($post->ID, '_tourivo_address', true) ?: '';
        $city         = get_post_meta($post->ID, '_tourivo_city', true) ?: '';
        $postalCode   = get_post_meta($post->ID, '_tourivo_postal_code', true) ?: '';
        $latitude     = get_post_meta($post->ID, '_tourivo_latitude', true) ?: '';
        $longitude    = get_post_meta($post->ID, '_tourivo_longitude', true) ?: '';
        $checkInTime  = get_post_meta($post->ID, '_tourivo_check_in_time', true) ?: '14:00';
        $checkOutTime = get_post_meta($post->ID, '_tourivo_check_out_time', true) ?: '11:00';
        $phone        = get_post_meta($post->ID, '_tourivo_phone', true) ?: '';
        $email        = get_post_meta($post->ID, '_tourivo_email', true) ?: '';
        $policy       = get_post_meta($post->ID, '_tourivo_policy', true) ?: '';

        $this->loadView('admin/metaboxes/hotel-metabox.php', [
            'post'         => $post,
            'starRating'   => $starRating,
            'address'      => $address,
            'city'         => $city,
            'postalCode'   => $postalCode,
            'latitude'     => $latitude,
            'longitude'    => $longitude,
            'checkInTime'  => $checkInTime,
            'checkOutTime' => $checkOutTime,
            'phone'        => $phone,
            'email'        => $email,
            'policy'       => $policy,
        ]);
    }

    public function save(int $postId, WP_Post $post): void
    {
        $fields = [
            '_tourivo_star_rating'   => 'absint',
            '_tourivo_address'       => 'sanitize_text_field',
            '_tourivo_city'          => 'sanitize_text_field',
            '_tourivo_postal_code'   => 'sanitize_text_field',
            '_tourivo_latitude'      => 'sanitize_text_field',
            '_tourivo_longitude'     => 'sanitize_text_field',
            '_tourivo_check_in_time' => 'sanitize_text_field',
            '_tourivo_check_out_time'=> 'sanitize_text_field',
            '_tourivo_phone'         => 'sanitize_text_field',
            '_tourivo_email'         => 'sanitize_email',
            '_tourivo_policy'        => 'sanitize_textarea_field',
        ];

        foreach ($fields as $field => $sanitizer) {
            if (isset($_POST[$field])) {
                $val = wp_unslash($_POST[$field]);
                $cleanVal = is_callable($sanitizer) ? $sanitizer($val) : sanitize_text_field($val);
                update_post_meta($postId, $field, $cleanVal);
            }
        }
    }
}
