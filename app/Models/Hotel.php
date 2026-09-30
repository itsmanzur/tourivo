<?php

declare(strict_types=1);

namespace Tourivo\Models;

use Tourivo\Common\Abstracts\Model;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\RoomPostType;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Hotel
 *
 * Object model for Hotel entities.
 *
 * @package Tourivo\Models
 */
class Hotel extends Model
{
    /**
     * Get star rating (1-5).
     *
     * @return int
     */
    public function getStarRating(): int
    {
        return (int) ($this->getMeta('_tourivo_star_rating') ?: 3);
    }

    /**
     * Get full street address.
     *
     * @return string
     */
    public function getAddress(): string
    {
        return (string) ($this->getMeta('_tourivo_address') ?: '');
    }

    /**
     * Get city.
     *
     * @return string
     */
    public function getCity(): string
    {
        return (string) ($this->getMeta('_tourivo_city') ?: '');
    }

    /**
     * Get check-in time (e.g. "14:00").
     *
     * @return string
     */
    public function getCheckInTime(): string
    {
        return (string) ($this->getMeta('_tourivo_check_in_time') ?: '14:00');
    }

    /**
     * Get check-out time (e.g. "11:00").
     *
     * @return string
     */
    public function getCheckOutTime(): string
    {
        return (string) ($this->getMeta('_tourivo_check_out_time') ?: '11:00');
    }

    /**
     * Get contact phone.
     *
     * @return string
     */
    public function getPhone(): string
    {
        return (string) ($this->getMeta('_tourivo_phone') ?: '');
    }

    /**
     * Get contact email.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return (string) ($this->getMeta('_tourivo_email') ?: '');
    }

    /**
     * Get hotel amenities terms.
     *
     * @return array<\WP_Term>
     */
    public function getAmenities(): array
    {
        $terms = get_the_terms($this->id, HotelPostType::TAX_AMENITY);
        return is_array($terms) ? $terms : [];
    }

    /**
     * Get all rooms belonging to this hotel.
     *
     * @return array<Room>
     */
    public function getRooms(): array
    {
        $query = new WP_Query([
            'post_type'      => RoomPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_tourivo_parent_hotel_id',
                    'value'   => $this->id,
                    'compare' => '=',
                ],
            ],
        ]);

        $rooms = [];
        if ($query->have_posts()) {
            foreach ($query->posts as $roomPost) {
                $rooms[] = new Room($roomPost);
            }
        }

        return $rooms;
    }

    /**
     * Get starting / minimum room price for this hotel.
     *
     * @return float
     */
    public function getMinPrice(): float
    {
        $rooms = $this->getRooms();
        if (empty($rooms)) {
            return 0.0;
        }

        $minPrice = PHP_FLOAT_MAX;
        foreach ($rooms as $room) {
            $price = $room->getNightlyPrice();
            if ($price > 0 && $price < $minPrice) {
                $minPrice = $price;
            }
        }

        return ($minPrice === PHP_FLOAT_MAX) ? 0.0 : $minPrice;
    }

    /**
     * Get average customer review rating (e.g. 4.8).
     *
     * @return float
     */
    public function getAverageRating(): float
    {
        return (float) ($this->getMeta('_tourivo_average_rating') ?: 0.0);
    }

    /**
     * Get total customer review count.
     *
     * @return int
     */
    public function getReviewCount(): int
    {
        return (int) ($this->getMeta('_tourivo_review_count') ?: 0);
    }
}

