<?php

declare(strict_types=1);

namespace Tourivo\Models;

use Tourivo\Common\Abstracts\Model;
use Tourivo\PostTypes\TourPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Tour
 *
 * Object model for Tour entities.
 *
 * @package Tourivo\Models
 */
class Tour extends Model
{
    /**
     * Get tour type (single_day, multi_day, hourly, fixed_departure).
     *
     * @return string
     */
    public function getTourType(): string
    {
        return (string) ($this->getMeta('_tourivo_tour_type') ?: 'single_day');
    }

    /**
     * Get regular base price.
     *
     * @return float
     */
    public function getPrice(): float
    {
        return (float) ($this->getMeta('_tourivo_base_price') ?: 0.0);
    }

    /**
     * Get sale / promotional price.
     *
     * @return float|null
     */
    public function getSalePrice(): ?float
    {
        $sale = $this->getMeta('_tourivo_sale_price');
        return ($sale !== '' && $sale !== false) ? (float) $sale : null;
    }

    /**
     * Get active price (sale price if set and lower, otherwise base price).
     *
     * @return float
     */
    public function getActivePrice(): float
    {
        $sale = $this->getSalePrice();
        if ($sale !== null && $sale > 0 && $sale < $this->getPrice()) {
            return $sale;
        }
        return $this->getPrice();
    }

    /**
     * Get formatted active price string with currency symbol.
     *
     * @return string
     */
    public function getFormattedPrice(): string
    {
        return \Tourivo\Support\Money::format($this->getActivePrice());
    }

    /**
     * Get duration string (e.g. "3 Days / 2 Nights" or "4 Hours").
     *
     * @return string
     */
    public function getDuration(): string
    {
        return (string) ($this->getMeta('_tourivo_duration') ?: '');
    }

    /**
     * Get minimum group size.
     *
     * @return int
     */
    /**
     * Get minimum group size.
     *
     * @return int
     */
    public function getMinGuests(): int
    {
        return (int) ($this->getMeta('_tourivo_min_guests') ?: 1);
    }

    /**
     * Get maximum group size / capacity.
     *
     * @return int
     */
    public function getMaxGuests(): int
    {
        return (int) ($this->getMeta('_tourivo_max_guests') ?: 20);
    }

    /**
     * Total seats that can be sold per departure date across all bookings (0 = not set, use group size).
     *
     * @return int
     */
    public function getDailyCapacity(): int
    {
        return max(0, (int) $this->getMeta('_tourivo_daily_capacity'));
    }

    /**
     * Get child pricing model ('full', 'percent', 'fixed', 'free').
     *
     * @return string
     */
    public function getChildPriceType(): string
    {
        $type = (string) $this->getMeta('_tourivo_child_price_type');
        return in_array($type, ['full', 'percent', 'fixed', 'free'], true) ? $type : 'full';
    }

    /**
     * Get child pricing value (percentage or fixed amount).
     *
     * @return float
     */
    public function getChildPriceValue(): float
    {
        return max(0.0, (float) ($this->getMeta('_tourivo_child_price_value') ?: 0.0));
    }

    /**
     * Get custom child age label (e.g. "Ages 3-11").
     *
     * @return string
     */
    public function getChildAgeLabel(): string
    {
        return (string) ($this->getMeta('_tourivo_child_age_label') ?: '');
    }

    /**
     * Check if infants are free of charge.
     *
     * @return bool
     */
    public function isInfantsFree(): bool
    {
        $val = $this->getMeta('_tourivo_infants_free');
        if ($val === '' || $val === false || $val === null) {
            return true;
        }
        return $val === 'yes' || $val === '1' || $val === 1 || $val === true;
    }

    /**
     * Get badge text (e.g. "Bestseller", "Featured", "20% Off").
     *
     * @return string
     */
    public function getBadge(): string
    {
        return (string) ($this->getMeta('_tourivo_badge') ?: '');
    }

    /**
     * Get structured itinerary array.
     *
     * @return array<int, array{day: int|string, title: string, desc: string, meals: string}>
     */
    public function getItinerary(): array
    {
        $itinerary = $this->getMeta('_tourivo_itinerary');
        if (is_string($itinerary)) {
            $decoded = json_decode($itinerary, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($itinerary) ? $itinerary : [];
    }

    /**
     * Get inclusions list.
     *
     * @return array<string>
     */
    public function getInclusions(): array
    {
        $inclusions = $this->getMeta('_tourivo_inclusions');
        if (is_string($inclusions)) {
            $decoded = json_decode($inclusions, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($inclusions) ? $inclusions : [];
    }

    /**
     * Get exclusions list.
     *
     * @return array<string>
     */
    public function getExclusions(): array
    {
        $exclusions = $this->getMeta('_tourivo_exclusions');
        if (is_string($exclusions)) {
            $decoded = json_decode($exclusions, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($exclusions) ? $exclusions : [];
    }

    /**
     * Get FAQs list.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    public function getFaqs(): array
    {
        $faqs = $this->getMeta('_tourivo_faqs');
        if (is_string($faqs)) {
            $decoded = json_decode($faqs, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($faqs) ? $faqs : [];
    }

    /**
     * Get pickup location.
     *
     * @return string
     */
    public function getPickupLocation(): string
    {
        return (string) ($this->getMeta('_tourivo_pickup_location') ?: '');
    }

    /**
     * Get drop-off location.
     *
     * @return string
     */
    public function getDropoffLocation(): string
    {
        return (string) ($this->getMeta('_tourivo_dropoff_location') ?: '');
    }

    /**
     * Get assigned destinations taxonomy terms.
     *
     * @return array<\WP_Term>
     */
    public function getDestinations(): array
    {
        $terms = get_the_terms($this->id, TourPostType::TAX_DESTINATION);
        return is_array($terms) ? $terms : [];
    }

    /**
     * Get assigned activity taxonomy terms.
     *
     * @return array<\WP_Term>
     */
    public function getActivities(): array
    {
        $terms = get_the_terms($this->id, TourPostType::TAX_ACTIVITY);
        return is_array($terms) ? $terms : [];
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

