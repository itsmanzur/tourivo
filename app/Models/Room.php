<?php

declare(strict_types=1);

namespace Tourivo\Models;

use Tourivo\Common\Abstracts\Model;
use Tourivo\PostTypes\HotelPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Room
 *
 * Object model for Hotel Room entities.
 *
 * @package Tourivo\Models
 */
class Room extends Model
{
    /**
     * Get parent hotel ID.
     *
     * @return int
     */
    public function getHotelId(): int
    {
        return (int) ($this->getMeta('_tourivo_parent_hotel_id') ?: 0);
    }

    /**
     * Get parent hotel model instance.
     *
     * @return Hotel|null
     */
    public function getHotel(): ?Hotel
    {
        $hotelId = $this->getHotelId();
        return $hotelId > 0 ? new Hotel($hotelId) : null;
    }

    /**
     * Get base nightly rate.
     *
     * @return float
     */
    public function getNightlyPrice(): float
    {
        return (float) ($this->getMeta('_tourivo_nightly_price') ?: 0.0);
    }

    /**
     * Get formatted nightly price.
     *
     * @return string
     */
    public function getFormattedPrice(): string
    {
        return \Tourivo\Support\Money::format($this->getNightlyPrice());
    }

    /**
     * Get max adult capacity.
     *
     * @return int
     */
    public function getMaxAdults(): int
    {
        return (int) ($this->getMeta('_tourivo_max_adults') ?: 2);
    }

    /**
     * Get max children capacity.
     *
     * @return int
     */
    public function getMaxChildren(): int
    {
        return (int) ($this->getMeta('_tourivo_max_children') ?: 0);
    }

    /**
     * Get total max guests.
     *
     * @return int
     */
    public function getMaxGuests(): int
    {
        return (int) ($this->getMeta('_tourivo_max_guests') ?: ($this->getMaxAdults() + $this->getMaxChildren()));
    }

    /**
     * Get total inventory units of this room type.
     *
     * @return int
     */
    public function getQuantity(): int
    {
        return (int) ($this->getMeta('_tourivo_room_quantity') ?: 1);
    }

    /**
     * Get room size string (e.g. "35 sqm" or "380 sqft").
     *
     * @return string
     */
    public function getRoomSize(): string
    {
        return (string) ($this->getMeta('_tourivo_room_size') ?: '');
    }

    /**
     * Get bed configuration description (e.g. "1 King Bed" or "2 Twin Beds").
     *
     * @return string
     */
    public function getBedType(): string
    {
        return (string) ($this->getMeta('_tourivo_bed_type') ?: '');
    }

    /**
     * Get room amenities.
     *
     * @return array<\WP_Term>
     */
    public function getAmenities(): array
    {
        $terms = get_the_terms($this->id, HotelPostType::TAX_AMENITY);
        return is_array($terms) ? $terms : [];
    }
}
