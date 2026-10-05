<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Models\Hotel;
use Tourivo\Models\Tour;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\TourPostType;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SeoService
 *
 * Generates Schema.org JSON-LD Structured Data (TouristTrip, Hotel, Product)
 * and OpenGraph / Twitter Cards social meta tags for tours and hotel stays.
 *
 * @package Tourivo\Services
 */
class SeoService
{
    /**
     * Initialize SEO hooks.
     */
    public function __construct()
    {
        add_action('wp_head', [$this, 'renderJsonLdSchema'], 20);
        add_action('wp_head', [$this, 'renderOpenGraphMeta'], 25);
        add_action('tourivo/booking_created', [__CLASS__, 'onBookingCreated'], 10, 2);
        add_action('tourivo/booking_status_changed', [__CLASS__, 'onBookingStatusChanged'], 10, 3);
    }

    /**
     * Check if a third-party SEO plugin is active (Yoast, Rank Math, AIOSEO).
     *
     * @return bool
     */
    public function hasThirdPartySeoPlugin(): bool
    {
        return defined('WPSEO_VERSION')
            || defined('RANK_MATH_VERSION')
            || defined('AIOSEO_VERSION')
            || defined('AIOSEO_DIR');
    }

    /**
     * Render Schema.org JSON-LD structured data in <head>.
     *
     * @return void
     */
    public function renderJsonLdSchema(): void
    {
        if (!is_singular([TourPostType::POST_TYPE, HotelPostType::POST_TYPE])) {
            return;
        }

        $defaultEnable = !$this->hasThirdPartySeoPlugin();
        $enableSchema  = (bool) apply_filters('tourivo/enable_schema', $defaultEnable);

        if (!$enableSchema) {
            return;
        }

        $postId = (int) get_the_ID();
        $postType = get_post_type($postId);

        if ($postType === TourPostType::POST_TYPE) {
            $schema = $this->buildTourSchema($postId);
        } elseif ($postType === HotelPostType::POST_TYPE) {
            $schema = $this->buildHotelSchema($postId);
        } else {
            return;
        }

        if (empty($schema)) {
            return;
        }

        echo "\n<!-- Tourivo Schema.org JSON-LD Structured Data -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        echo "</script>\n<!-- /Tourivo Schema -->\n\n";
    }

    /**
     * Invalidate SEO availability transient cache when a new booking is created.
     *
     * @param int                  $bookingId
     * @param array<string, mixed> $bookingData
     * @return void
     */
    public static function onBookingCreated(int $bookingId, array $bookingData = []): void
    {
        $itemId = (int) ($bookingData['item_id'] ?? 0);
        $itemType = (string) ($bookingData['item_type'] ?? '');
        if ($itemId > 0) {
            self::clearItemAvailabilityCache($itemId, $itemType);
        }
    }

    /**
     * Invalidate SEO availability transient cache when a booking status changes.
     *
     * @param int    $bookingId
     * @param string $oldStatus
     * @param string $newStatus
     * @return void
     */
    public static function onBookingStatusChanged(int $bookingId, string $oldStatus, string $newStatus): void
    {
        global $wpdb;
        if (!$wpdb || $bookingId <= 0) {
            return;
        }

        $itemsTable = $wpdb->prefix . 'tourivo_booking_items';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $items = $wpdb->get_results($wpdb->prepare("SELECT item_id, item_type FROM {$itemsTable} WHERE booking_id = %d", $bookingId));
        // phpcs:enable

        if (!empty($items)) {
            foreach ($items as $item) {
                self::clearItemAvailabilityCache((int) $item->item_id, (string) $item->item_type);
            }
        }
    }

    /**
     * Clear SEO availability transient cache for an item (and its parent hotel if room).
     *
     * @param int         $itemId
     * @param string|null $itemType 'tour', 'room', or 'hotel'
     * @return void
     */
    public static function clearItemAvailabilityCache(int $itemId, ?string $itemType = null): void
    {
        if ($itemId <= 0) {
            return;
        }

        if (empty($itemType)) {
            $postType = get_post_type($itemId);
            if ($postType === 'tourivo_tour') {
                $itemType = 'tour';
            } elseif ($postType === 'tourivo_room') {
                $itemType = 'room';
            } elseif ($postType === 'tourivo_hotel') {
                $itemType = 'hotel';
            }
        }

        if ($itemType === 'tour') {
            delete_transient('tourivo_seo_avail_' . $itemId . '_tour');
        } elseif ($itemType === 'hotel') {
            delete_transient('tourivo_seo_avail_' . $itemId . '_hotel');
        } elseif ($itemType === 'room') {
            delete_transient('tourivo_seo_avail_' . $itemId . '_room');
            $parentHotelId = (int) get_post_meta($itemId, '_tourivo_parent_hotel_id', true);
            if ($parentHotelId > 0) {
                delete_transient('tourivo_seo_avail_' . $parentHotelId . '_hotel');
            }
        }
    }

    /**
     * Compute actual Schema.org inventory availability with 1-hour transient caching.
     *
     * Inspects active capacity and availability over the next 90 days via hasAvailabilityInRange.
     *
     * @param int    $postId
     * @param string $itemType 'tour' or 'hotel'
     * @return string 'https://schema.org/InStock' or 'https://schema.org/SoldOut'
     */
    public function getItemAvailability(int $postId, string $itemType): string
    {
        $transientKey = 'tourivo_seo_avail_' . $postId . '_' . $itemType;
        $cached = get_transient($transientKey);
        if ($cached !== false) {
            return (string) $cached;
        }

        // Use the site timezone so "today" matches what visitors see.
        $today = wp_date('Y-m-d');
        $futureDate = wp_date('Y-m-d', time() + (90 * DAY_IN_SECONDS));
        $hasAvailability = false;

        /** @var InventoryRepository $invRepo */
        $invRepo = Container::getInstance()->has(InventoryRepository::class)
            ? Container::getInstance()->get(InventoryRepository::class)
            : new InventoryRepository();

        if ($itemType === 'tour') {
            $tour = new Tour($postId);
            $maxGuests = $tour->getMaxGuests();
            if ($maxGuests > 0) {
                $hasAvailability = $invRepo->hasAvailabilityInRange($postId, 'tour', $today, $futureDate, $maxGuests);
            }
        } elseif ($itemType === 'hotel') {
            $hotel = new Hotel($postId);
            $rooms = $hotel->getRooms();
            if (!empty($rooms)) {
                $roomIds = [];
                $maxRoomQty = 0;
                foreach ($rooms as $room) {
                    $roomId = $room->getId();
                    $qty = $room->getQuantity();
                    if ($roomId > 0 && $qty > 0) {
                        $roomIds[] = $roomId;
                        $maxRoomQty = max($maxRoomQty, $qty);
                    }
                }
                if (!empty($roomIds)) {
                    $hasAvailability = $invRepo->hasAvailabilityInRange($roomIds, 'room', $today, $futureDate, $maxRoomQty);
                }
            }
        }

        $availability = $hasAvailability ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut';
        $availability = (string) apply_filters('tourivo/seo/availability', $availability, $postId, $itemType);

        set_transient($transientKey, $availability, HOUR_IN_SECONDS);

        return $availability;
    }

    /**
     * Build Schema.org structure for a Tour post.
     *
     * @param int $postId
     * @return array<string, mixed>
     */
    public function buildTourSchema(int $postId): array
    {
        $tour = new Tour($postId);
        $post = get_post($postId);
        if (!$post) {
            return [];
        }

        $currency    = (string) apply_filters('tourivo/currency_code', Config::get('currency', 'USD'));
        $siteName    = get_bloginfo('name');
        $siteUrl     = home_url();
        $touristType = (string) apply_filters('tourivo/seo/tourist_type', 'Leisure', $postId, $tour);

        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'TouristTrip',
            '@id'           => get_permalink($postId) . '#trip',
            'name'          => get_the_title($postId),
            'description'   => wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 35)),
            'url'           => get_permalink($postId),
            'touristType'   => $touristType,
            'provider'      => [
                '@type' => 'Organization',
                'name'  => $siteName,
                'url'   => $siteUrl,
            ],
            'offers'        => [
                '@type'         => 'Offer',
                'price'         => number_format($tour->getActivePrice(), 2, '.', ''),
                'priceCurrency' => $currency,
                'availability'  => $this->getItemAvailability($postId, 'tour'),
                'url'           => get_permalink($postId),
                'validFrom'     => gmdate('Y-m-d'),
            ],
        ];

        // Featured Image
        if (has_post_thumbnail($postId)) {
            $imgUrl = get_the_post_thumbnail_url($postId, 'full');
            if ($imgUrl) {
                $schema['image'] = esc_url_raw($imgUrl);
            }
        }

        // Itinerary ItemList of TouristAttraction
        $itinerary = $tour->getItinerary();
        if (!empty($itinerary)) {
            $itineraryList = [];
            $pos = 1;
            foreach ($itinerary as $step) {
                $itineraryList[] = [
                    '@type'       => 'ListItem',
                    'position'    => $pos++,
                    'item'        => [
                        '@type'       => 'TouristAttraction',
                        'name'        => $step['title'] ?? ('Day ' . ($step['day'] ?? '')),
                        'description' => $step['desc'] ?? '',
                    ],
                ];
            }
            $schema['itinerary'] = [
                '@type'           => 'ItemList',
                'numberOfItems'   => count($itineraryList),
                'itemListElement' => $itineraryList,
            ];
        }

        // Aggregate Rating
        if ($tour->getReviewCount() > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => number_format($tour->getAverageRating(), 1, '.', ''),
                'reviewCount' => $tour->getReviewCount(),
                'bestRating'  => '5',
                'worstRating' => '1',
            ];
        }

        return (array) apply_filters('tourivo/schema/tour', $schema, $postId, $tour);
    }

    /**
     * Build Schema.org structure for a Hotel post.
     *
     * @param int $postId
     * @return array<string, mixed>
     */
    public function buildHotelSchema(int $postId): array
    {
        $hotel = new Hotel($postId);
        $post  = get_post($postId);
        if (!$post) {
            return [];
        }

        $currency = (string) apply_filters('tourivo/currency_code', Config::get('currency', 'USD'));
        $siteName = get_bloginfo('name');
        $siteUrl  = home_url();

        $amenities = $hotel->getAmenities();
        $amenityNames = !empty($amenities) ? array_map(static fn ($t) => $t->name, $amenities) : [];

        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'Hotel',
            '@id'           => get_permalink($postId) . '#hotel',
            'name'          => get_the_title($postId),
            'description'   => wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 35)),
            'url'           => get_permalink($postId),
            'checkinTime'   => $hotel->getCheckInTime(),
            'checkoutTime'  => $hotel->getCheckOutTime(),
        ];

        // Star Rating
        if ($hotel->getStarRating() > 0) {
            $schema['starRating'] = [
                '@type'       => 'Rating',
                'ratingValue' => $hotel->getStarRating(),
            ];
        }

        // Address
        if ($hotel->getAddress() || $hotel->getCity()) {
            $schema['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $hotel->getAddress(),
                'addressLocality' => $hotel->getCity(),
                'postalCode'      => $hotel->getPostalCode(),
            ];
        }

        // Phone & Email
        if ($hotel->getPhone()) {
            $schema['telephone'] = $hotel->getPhone();
        }
        if ($hotel->getEmail()) {
            $schema['email'] = $hotel->getEmail();
        }

        // Geocoordinates
        if ($hotel->getLatitude() && $hotel->getLongitude()) {
            $schema['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $hotel->getLatitude(),
                'longitude' => $hotel->getLongitude(),
            ];
        }

        // Amenities
        if (!empty($amenityNames)) {
            $schema['amenityFeature'] = array_map(static fn ($name) => [
                '@type' => 'LocationFeatureSpecification',
                'name'  => $name,
                'value' => true,
            ], $amenityNames);
        }

        // Image
        if (has_post_thumbnail($postId)) {
            $imgUrl = get_the_post_thumbnail_url($postId, 'full');
            if ($imgUrl) {
                $schema['image'] = esc_url_raw($imgUrl);
            }
        }

        // Price range & Offers
        $minPrice = $hotel->getMinPrice();
        if ($minPrice > 0) {
            $schema['priceRange'] = Money::format($minPrice, $currency);
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => number_format($minPrice, 2, '.', ''),
                'priceCurrency' => $currency,
                'availability'  => $this->getItemAvailability($postId, 'hotel'),
                'url'           => get_permalink($postId),
                'validFrom'     => gmdate('Y-m-d'),
            ];
        }

        // Aggregate Rating
        if ($hotel->getReviewCount() > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => number_format($hotel->getAverageRating(), 1, '.', ''),
                'reviewCount' => $hotel->getReviewCount(),
                'bestRating'  => '5',
                'worstRating' => '1',
            ];
        }

        return (array) apply_filters('tourivo/schema/hotel', $schema, $postId, $hotel);
    }

    /**
     * Render OpenGraph and Twitter Cards social meta tags in <head>.
     *
     * @return void
     */
    public function renderOpenGraphMeta(): void
    {
        if (!is_singular([TourPostType::POST_TYPE, HotelPostType::POST_TYPE])) {
            return;
        }

        $defaultEnable = !$this->hasThirdPartySeoPlugin();
        $enableOg      = (bool) apply_filters('tourivo/enable_og', apply_filters('tourivo/enable_opengraph', $defaultEnable));

        if (!$enableOg) {
            return;
        }

        $postId = (int) get_the_ID();
        $post   = get_post($postId);
        if (!$post) {
            return;
        }

        $title       = get_the_title($postId);
        $desc        = wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 30));
        $url         = get_permalink($postId);
        $siteName    = get_bloginfo('name');
        $imgUrl      = has_post_thumbnail($postId) ? get_the_post_thumbnail_url($postId, 'large') : '';
        $currency    = (string) apply_filters('tourivo/currency_code', Config::get('currency', 'USD'));
        $postType    = get_post_type($postId);

        $price = 0.0;
        if ($postType === TourPostType::POST_TYPE) {
            $tour  = new Tour($postId);
            $price = $tour->getActivePrice();
        } else {
            $hotel = new Hotel($postId);
            $price = $hotel->getMinPrice();
        }

        echo "\n<!-- Tourivo OpenGraph & Social Meta Tags -->\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($siteName) . '" />' . "\n";

        if ($imgUrl) {
            echo '<meta property="og:image" content="' . esc_url($imgUrl) . '" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($imgUrl) . '" />' . "\n";
        }

        if ($price > 0) {
            echo '<meta property="product:price:amount" content="' . esc_attr(number_format($price, 2, '.', '')) . '" />' . "\n";
            echo '<meta property="product:price:currency" content="' . esc_attr($currency) . '" />' . "\n";
        }

        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($desc) . '" />' . "\n";
        echo "<!-- /Tourivo Social Meta -->\n\n";
    }
}
