<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Config\Config;
use Tourivo\Models\Hotel;
use Tourivo\Models\Tour;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\TourPostType;
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

        if (!apply_filters('tourivo/enable_schema', true)) {
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
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        echo "</script>\n<!-- /Tourivo Schema -->\n\n";
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

        $currency = (string) apply_filters('tourivo/currency_code', Config::get('currency', 'USD'));
        $siteName = get_bloginfo('name');
        $siteUrl  = home_url();

        $destinations = $tour->getDestinations();
        $destNames    = !empty($destinations) ? array_map(static fn ($t) => $t->name, $destinations) : [];

        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'TouristTrip',
            '@id'           => get_permalink($postId) . '#trip',
            'name'          => get_the_title($postId),
            'description'   => wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 35)),
            'url'           => get_permalink($postId),
            'touristType'   => !empty($destNames) ? implode(', ', $destNames) : 'Leisure',
            'provider'      => [
                '@type' => 'Organization',
                'name'  => $siteName,
                'url'   => $siteUrl,
            ],
            'offers'        => [
                '@type'         => 'Offer',
                'price'         => number_format($tour->getActivePrice(), 2, '.', ''),
                'priceCurrency' => $currency,
                'availability'  => 'https://schema.org/InStock',
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

        // Itinerary Days
        $itinerary = $tour->getItinerary();
        if (!empty($itinerary)) {
            $itineraryList = [];
            foreach ($itinerary as $step) {
                $itineraryList[] = [
                    '@type'       => 'Day',
                    'name'        => $step['title'] ?? 'Day ' . ($step['day'] ?? ''),
                    'description' => $step['desc'] ?? '',
                ];
            }
            $schema['itinerary'] = [
                '@type'           => 'ItemList',
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

        // Price range
        $minPrice = $hotel->getMinPrice();
        if ($minPrice > 0) {
            $schema['priceRange'] = Money::format($minPrice, $currency);
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

        if (!apply_filters('tourivo/enable_opengraph', true)) {
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
