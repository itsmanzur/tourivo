<?php

declare(strict_types=1);

namespace Tourivo\Admin\MetaBoxes;

use Tourivo\Common\Abstracts\MetaBox;
use Tourivo\PostTypes\TourPostType;
use Tourivo\Services\SeoService;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TourMetaBox
 *
 * Handles Tour settings metabox: Pricing, Duration, Itinerary, Inclusions, Locations, FAQs.
 *
 * @package Tourivo\Admin\MetaBoxes
 */
class TourMetaBox extends MetaBox
{
    protected string $id = 'tourivo_tour_settings';
    protected string $title = 'Tour Package & Booking Settings';
    protected string|array $postTypes = TourPostType::POST_TYPE;
    protected string $context = 'normal';
    protected string $priority = 'high';

    public function getTitle(): string
    {
        return __('Tour Package & Booking Settings', 'tourivo');
    }

    public function render(WP_Post $post): void
    {
        $tourType        = get_post_meta($post->ID, '_tourivo_tour_type', true) ?: 'single_day';
        $basePrice       = get_post_meta($post->ID, '_tourivo_base_price', true) ?: '';
        $salePrice       = get_post_meta($post->ID, '_tourivo_sale_price', true) ?: '';
        $duration        = get_post_meta($post->ID, '_tourivo_duration', true) ?: '';
        $minGuests       = get_post_meta($post->ID, '_tourivo_min_guests', true) ?: '1';
        $maxGuests       = get_post_meta($post->ID, '_tourivo_max_guests', true) ?: '20';
        $dailyCapacity   = get_post_meta($post->ID, '_tourivo_daily_capacity', true) ?: '';
        $allowFree       = get_post_meta($post->ID, '_tourivo_allow_free_booking', true) === '1';
        $badge           = get_post_meta($post->ID, '_tourivo_badge', true) ?: '';
        $pickupLocation  = get_post_meta($post->ID, '_tourivo_pickup_location', true) ?: '';
        $dropoffLocation = get_post_meta($post->ID, '_tourivo_dropoff_location', true) ?: '';
        $latitude        = get_post_meta($post->ID, '_tourivo_latitude', true) ?: '';
        $longitude       = get_post_meta($post->ID, '_tourivo_longitude', true) ?: '';

        // Child & Infant pricing fields
        $childPriceType  = get_post_meta($post->ID, '_tourivo_child_price_type', true) ?: 'full';
        $childPriceValue = get_post_meta($post->ID, '_tourivo_child_price_value', true) ?: '';
        $childAgeLabel   = get_post_meta($post->ID, '_tourivo_child_age_label', true) ?: '';
        $infantsFreeMeta = get_post_meta($post->ID, '_tourivo_infants_free', true);
        $infantsFree     = ($infantsFreeMeta === '' || $infantsFreeMeta === false) ? 'yes' : $infantsFreeMeta;

        // Decode JSON fields
        $itinerary = json_decode((string) get_post_meta($post->ID, '_tourivo_itinerary', true), true) ?: [];
        $inclusions = json_decode((string) get_post_meta($post->ID, '_tourivo_inclusions', true), true) ?: [];
        $exclusions = json_decode((string) get_post_meta($post->ID, '_tourivo_exclusions', true), true) ?: [];
        $faqs       = json_decode((string) get_post_meta($post->ID, '_tourivo_faqs', true), true) ?: [];

        $this->loadView('admin/metaboxes/tour-metabox.php', [
            'post'            => $post,
            'tourType'        => $tourType,
            'basePrice'       => $basePrice,
            'salePrice'       => $salePrice,
            'duration'        => $duration,
            'minGuests'       => $minGuests,
            'maxGuests'       => $maxGuests,
            'dailyCapacity'   => $dailyCapacity,
            'allowFree'       => $allowFree,
            'childPriceType'  => $childPriceType,
            'childPriceValue' => $childPriceValue,
            'childAgeLabel'   => $childAgeLabel,
            'infantsFree'     => $infantsFree,
            'badge'           => $badge,
            'pickupLocation'  => $pickupLocation,
            'dropoffLocation' => $dropoffLocation,
            'latitude'        => $latitude,
            'longitude'       => $longitude,
            'itinerary'       => $itinerary,
            'inclusions'      => $inclusions,
            'exclusions'      => $exclusions,
            'faqs'            => $faqs,
        ]);
    }

    public function save(int $postId, WP_Post $post): void
    {
        // Nonce is verified by parent handleSave(), but documented here for PHPCS
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (empty($_POST)) {
            return;
        }

        // 1. Basic Fields
        $fields = [
            '_tourivo_tour_type'        => 'sanitize_text_field',
            '_tourivo_base_price'       => static fn ($v) => number_format(max(0.0, (float) str_replace(',', '', (string) $v)), 2, '.', ''),
            '_tourivo_sale_price'       => static fn ($v) => ($v !== '' && $v !== null) ? number_format(max(0.0, (float) str_replace(',', '', (string) $v)), 2, '.', '') : '',
            '_tourivo_duration'         => 'sanitize_text_field',
            '_tourivo_min_guests'       => 'absint',
            '_tourivo_max_guests'       => 'absint',
            '_tourivo_daily_capacity'   => 'absint',
            '_tourivo_child_price_type' => static fn ($v) => in_array((string) $v, ['full', 'percent', 'fixed', 'free'], true) ? (string) $v : 'full',
            '_tourivo_child_price_value'=> static fn ($v) => ($v !== '' && $v !== null) ? number_format(max(0.0, (float) str_replace(',', '', (string) $v)), 2, '.', '') : '',
            '_tourivo_child_age_label'  => 'sanitize_text_field',
            '_tourivo_infants_free'     => static fn ($v) => ($v === 'no') ? 'no' : 'yes',
            '_tourivo_badge'            => 'sanitize_text_field',
            '_tourivo_pickup_location'  => 'sanitize_text_field',
            '_tourivo_dropoff_location' => 'sanitize_text_field',
            '_tourivo_latitude'         => 'sanitize_text_field',
            '_tourivo_longitude'        => 'sanitize_text_field',
        ];

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        foreach ($fields as $field => $sanitizer) {
            if (isset($_POST[$field])) {
                $val = wp_unslash($_POST[$field]);
                $cleanVal = is_callable($sanitizer) ? $sanitizer($val) : sanitize_text_field((string)$val);
                update_post_meta($postId, $field, $cleanVal);
            }
        }

        // Checkbox: absent from the request when unticked, so persist an explicit "0".
        update_post_meta($postId, '_tourivo_allow_free_booking', isset($_POST['_tourivo_allow_free_booking']) ? '1' : '0');

        // 1.1 Compute _tourivo_duration_days for search filter queries (supports English and Bengali digits/keywords)
        $durationStr = isset($_POST['_tourivo_duration']) ? sanitize_text_field(wp_unslash($_POST['_tourivo_duration'])) : '';
        $bengaliDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $westernDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalizedDurationStr = str_replace($bengaliDigits, $westernDigits, $durationStr);

        $durationDays = 1;
        if (preg_match('/(\d+)\s*(day|days|d|দিন)/iu', $normalizedDurationStr, $matches)) {
            $durationDays = max(1, (int) $matches[1]);
        }
        update_post_meta($postId, '_tourivo_duration_days', $durationDays);

        // 2. Inclusions Array
        if (isset($_POST['_tourivo_inclusions']) && is_array($_POST['_tourivo_inclusions'])) {
            $inclusions = array_filter(array_map('sanitize_text_field', wp_unslash($_POST['_tourivo_inclusions'])));
            update_post_meta($postId, '_tourivo_inclusions', wp_slash(wp_json_encode(array_values($inclusions), JSON_UNESCAPED_UNICODE)));
        } else {
            update_post_meta($postId, '_tourivo_inclusions', wp_slash(wp_json_encode([], JSON_UNESCAPED_UNICODE)));
        }

        // 3. Exclusions Array
        if (isset($_POST['_tourivo_exclusions']) && is_array($_POST['_tourivo_exclusions'])) {
            $exclusions = array_filter(array_map('sanitize_text_field', wp_unslash($_POST['_tourivo_exclusions'])));
            update_post_meta($postId, '_tourivo_exclusions', wp_slash(wp_json_encode(array_values($exclusions), JSON_UNESCAPED_UNICODE)));
        } else {
            update_post_meta($postId, '_tourivo_exclusions', wp_slash(wp_json_encode([], JSON_UNESCAPED_UNICODE)));
        }

        // 4. Itinerary Array
        if (isset($_POST['_tourivo_itinerary']) && is_array($_POST['_tourivo_itinerary'])) {
            $cleanItinerary = [];
            $rawItinerary = wp_unslash($_POST['_tourivo_itinerary']);
            if (is_array($rawItinerary)) {
                foreach ($rawItinerary as $item) {
                    if (is_array($item) && !empty($item['title'])) {
                        $cleanItinerary[] = [
                            'day'   => sanitize_text_field($item['day'] ?? ''),
                            'title' => sanitize_text_field($item['title'] ?? ''),
                            'desc'  => sanitize_textarea_field($item['desc'] ?? ''),
                            'meals' => sanitize_text_field($item['meals'] ?? ''),
                        ];
                    }
                }
            }
            update_post_meta($postId, '_tourivo_itinerary', wp_slash(wp_json_encode($cleanItinerary, JSON_UNESCAPED_UNICODE)));
        } else {
            update_post_meta($postId, '_tourivo_itinerary', wp_slash(wp_json_encode([], JSON_UNESCAPED_UNICODE)));
        }

        // 5. FAQs Array
        if (isset($_POST['_tourivo_faqs']) && is_array($_POST['_tourivo_faqs'])) {
            $cleanFaqs = [];
            $rawFaqs = wp_unslash($_POST['_tourivo_faqs']);
            if (is_array($rawFaqs)) {
                foreach ($rawFaqs as $faq) {
                    if (is_array($faq) && !empty($faq['question'])) {
                        $cleanFaqs[] = [
                            'question' => sanitize_text_field($faq['question'] ?? ''),
                            'answer'   => sanitize_textarea_field($faq['answer'] ?? ''),
                        ];
                    }
                }
            }
            update_post_meta($postId, '_tourivo_faqs', wp_slash(wp_json_encode($cleanFaqs, JSON_UNESCAPED_UNICODE)));
        } else {
            update_post_meta($postId, '_tourivo_faqs', wp_slash(wp_json_encode([], JSON_UNESCAPED_UNICODE)));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // Invalidate SEO availability transient cache when max guests or pricing changes
        SeoService::clearItemAvailabilityCache($postId, 'tour');

        do_action('tourivo_save_tour_meta', $postId, $post);
    }
}
