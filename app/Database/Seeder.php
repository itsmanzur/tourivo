<?php

declare(strict_types=1);

namespace Tourivo\Database;

use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\RoomPostType;
use Tourivo\PostTypes\TourPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Seeder
 *
 * Populates sample demo tours, hotels, rooms, and taxonomies for 1-click test setups.
 *
 * @package Tourivo\Database
 */
class Seeder
{
    /**
     * Run the sample data seeder.
     *
     * @return array{tours_created: int, hotels_created: int, rooms_created: int}
     */
    public static function run(): array
    {
        // 1. Create Taxonomies
        $destBali   = self::ensureTerm('Bali', TourPostType::TAX_DESTINATION);
        $destSwiss  = self::ensureTerm('Switzerland', TourPostType::TAX_DESTINATION);
        $destDubai  = self::ensureTerm('Dubai', TourPostType::TAX_DESTINATION);

        $actBeach   = self::ensureTerm('Beach & Island', TourPostType::TAX_ACTIVITY);
        $actAdv     = self::ensureTerm('Mountain Adventure', TourPostType::TAX_ACTIVITY);
        $actCity    = self::ensureTerm('City Sightseeing', TourPostType::TAX_ACTIVITY);

        $amenWifi   = self::ensureTerm('Free High-Speed WiFi', HotelPostType::TAX_AMENITY);
        $amenPool   = self::ensureTerm('Infinity Swimming Pool', HotelPostType::TAX_AMENITY);
        $amenAc     = self::ensureTerm('Air Conditioning', HotelPostType::TAX_AMENITY);
        $amenBreak  = self::ensureTerm('Complimentary Breakfast', HotelPostType::TAX_AMENITY);
        $amenSpa    = self::ensureTerm('Luxury Spa & Wellness', HotelPostType::TAX_AMENITY);

        $toursCount = 0;
        $hotelsCount = 0;
        $roomsCount = 0;

        // 2. Seed Tours
        // Tour 1: Bali
        $tour1Id = wp_insert_post([
            'post_title'   => 'Bali Tropical Beach & Temple Explorer',
            'post_content' => 'Experience the magic of Bali with our 5-day guided tropical adventure. Visit sacred water temples, relax on pristine sandy beaches, explore lush rice terraces in Ubud, and enjoy unforgettable ocean sunset dinners.',
            'post_status'  => 'publish',
            'post_type'    => TourPostType::POST_TYPE,
        ]);

        if ($tour1Id && !is_wp_error($tour1Id)) {
            $toursCount++;
            wp_set_object_terms($tour1Id, [$destBali], TourPostType::TAX_DESTINATION);
            wp_set_object_terms($tour1Id, [$actBeach], TourPostType::TAX_ACTIVITY);

            update_post_meta($tour1Id, '_tourivo_tour_type', 'multi_day');
            update_post_meta($tour1Id, '_tourivo_base_price', '349.00');
            update_post_meta($tour1Id, '_tourivo_sale_price', '299.00');
            update_post_meta($tour1Id, '_tourivo_duration', '5 Days / 4 Nights');
            update_post_meta($tour1Id, '_tourivo_min_guests', 1);
            update_post_meta($tour1Id, '_tourivo_max_guests', 16);
            update_post_meta($tour1Id, '_tourivo_badge', 'Bestseller');
            update_post_meta($tour1Id, '_tourivo_pickup_location', 'Ngurah Rai International Airport (DPS)');
            update_post_meta($tour1Id, '_tourivo_dropoff_location', 'Kuta / Seminyak Central Hub');

            $itinerary1 = [
                ['day' => '1', 'title' => 'Arrival & Welcome Dinner', 'desc' => 'Airport pickup, hotel check-in in Seminyak, followed by a beachfront welcome seafood dinner with live acoustic music.', 'meals' => 'Dinner'],
                ['day' => '2', 'title' => 'Ubud Waterfalls & Rice Terraces', 'desc' => 'Explore the scenic Tegallalang rice paddies, Tegenungan Waterfall, and Ubud Traditional Art Market.', 'meals' => 'Breakfast, Lunch'],
                ['day' => '3', 'title' => 'Sacred Temples & Mount Batur View', 'desc' => 'Visit Ulun Danu Beratan temple on Lake Beratan, followed by lunch overlooking the Batur volcano.', 'meals' => 'Breakfast, Lunch'],
                ['day' => '4', 'title' => 'Nusa Penida Island Day Cruise', 'desc' => 'Speedboat transfer to Nusa Penida, visiting Kelingking Secret Point Cliff, Angel’s Billabong, and Broken Beach.', 'meals' => 'Breakfast, Lunch'],
                ['day' => '5', 'title' => 'Souvenir Shopping & Airport Departure', 'desc' => 'Morning leisure time, local artisan souvenir shopping, and airport drop-off.', 'meals' => 'Breakfast'],
            ];
            update_post_meta($tour1Id, '_tourivo_duration_days', 5);
            update_post_meta($tour1Id, '_tourivo_itinerary', wp_slash(wp_json_encode($itinerary1, JSON_UNESCAPED_UNICODE)));

            $inclusions1 = ['4 Nights in 4-Star Hotel Accommodation', 'Daily Breakfast and 3 Lunches', 'Private AC Vehicle Transportation', 'English Speaking Professional Guide', 'All Temple and Park Entrance Fees'];
            $exclusions1 = ['International Flights', 'Travel Insurance', 'Personal Expenses and Tips'];
            update_post_meta($tour1Id, '_tourivo_inclusions', wp_slash(wp_json_encode($inclusions1, JSON_UNESCAPED_UNICODE)));
            update_post_meta($tour1Id, '_tourivo_exclusions', wp_slash(wp_json_encode($exclusions1, JSON_UNESCAPED_UNICODE)));

            $faqs1 = [
                ['question' => 'Is pickup included from all hotels in Bali?', 'answer' => 'Yes, free pickup is provided from Kuta, Seminyak, Sanur, Nusa Dua, and Ubud central zones.'],
                ['question' => 'What is the cancellation policy?', 'answer' => 'Full refund if cancelled up to 7 days before departure date.'],
            ];
            update_post_meta($tour1Id, '_tourivo_faqs', wp_slash(wp_json_encode($faqs1, JSON_UNESCAPED_UNICODE)));
        }

        // Tour 2: Swiss Alps
        $tour2Id = wp_insert_post([
            'post_title'   => 'Swiss Alps Glacier & Panorama Trek',
            'post_content' => 'An exhilarating 4-day alpine journey across the Swiss Alps. Experience glacier viewpoints, cable car rides to peak summits, tranquil mountain lakes, and cozy alpine chalets in Interlaken and Zermatt.',
            'post_status'  => 'publish',
            'post_type'    => TourPostType::POST_TYPE,
        ]);

        if ($tour2Id && !is_wp_error($tour2Id)) {
            $toursCount++;
            wp_set_object_terms($tour2Id, [$destSwiss], TourPostType::TAX_DESTINATION);
            wp_set_object_terms($tour2Id, [$actAdv], TourPostType::TAX_ACTIVITY);

            update_post_meta($tour2Id, '_tourivo_tour_type', 'multi_day');
            update_post_meta($tour2Id, '_tourivo_base_price', '599.00');
            update_post_meta($tour2Id, '_tourivo_sale_price', '549.00');
            update_post_meta($tour2Id, '_tourivo_duration', '4 Days / 3 Nights');
            update_post_meta($tour2Id, '_tourivo_duration_days', 4);
            update_post_meta($tour2Id, '_tourivo_min_guests', 2);
            update_post_meta($tour2Id, '_tourivo_max_guests', 12);
            update_post_meta($tour2Id, '_tourivo_badge', 'Alpine Top Pick');

            $itinerary2 = [
                ['day' => '1', 'title' => 'Zurich to Interlaken & Lake Brienz', 'desc' => 'Scenic train ride to Interlaken, turquoise boat cruise on Lake Brienz, and traditional Swiss cheese fondue dinner.', 'meals' => 'Dinner'],
                ['day' => '2', 'title' => 'Jungfraujoch – Top of Europe', 'desc' => 'Ascend to the highest railway station in Europe at 3,454m. Explore the Ice Palace and Sphinx Observatory.', 'meals' => 'Breakfast, Lunch'],
                ['day' => '3', 'title' => 'Zermatt & Matterhorn Glacier Trail', 'desc' => 'Travel to car-free Zermatt. Ride the Gornergrat cogwheel train with direct vistas of the iconic Matterhorn.', 'meals' => 'Breakfast'],
                ['day' => '4', 'title' => 'Alpine Village Walk & Return to Zurich', 'desc' => 'Explore charming wooden chalets, chocolate tasting, and return express train to Zurich HB.', 'meals' => 'Breakfast'],
            ];
            update_post_meta($tour2Id, '_tourivo_itinerary', wp_slash(wp_json_encode($itinerary2, JSON_UNESCAPED_UNICODE)));
            update_post_meta($tour2Id, '_tourivo_inclusions', wp_slash(wp_json_encode(['Swiss Travel Pass 4-Day Pass', '3 Nights Alpine Hotel Stay', 'Jungfraujoch Summit Pass', 'Certified Mountain Guide'], JSON_UNESCAPED_UNICODE)));
            update_post_meta($tour2Id, '_tourivo_exclusions', wp_slash(wp_json_encode(['Ski gear rental', 'Personal meals outside itinerary'], JSON_UNESCAPED_UNICODE)));
        }

        // Tour 3: Dubai City & Safari
        $tour3Id = wp_insert_post([
            'post_title'   => 'Dubai Luxury Desert Safari & Dhow Cruise',
            'post_content' => 'The ultimate 1-day Dubai experience featuring 4x4 red dune bashing, camel riding, sandboarding, BBQ dinner under desert stars with Tanoura dance, and luxury city transfers.',
            'post_status'  => 'publish',
            'post_type'    => TourPostType::POST_TYPE,
        ]);

        if ($tour3Id && !is_wp_error($tour3Id)) {
            $toursCount++;
            wp_set_object_terms($tour3Id, [$destDubai], TourPostType::TAX_DESTINATION);
            wp_set_object_terms($tour3Id, [$actCity], TourPostType::TAX_ACTIVITY);

            update_post_meta($tour3Id, '_tourivo_tour_type', 'single_day');
            update_post_meta($tour3Id, '_tourivo_base_price', '140.00');
            update_post_meta($tour3Id, '_tourivo_sale_price', '119.00');
            update_post_meta($tour3Id, '_tourivo_duration', '8 Hours');
            update_post_meta($tour3Id, '_tourivo_duration_days', 1);
            update_post_meta($tour3Id, '_tourivo_min_guests', 1);
            update_post_meta($tour3Id, '_tourivo_max_guests', 30);
            update_post_meta($tour3Id, '_tourivo_badge', 'Popular Day Trip');
            update_post_meta($tour3Id, '_tourivo_pickup_location', 'Hotel Lobby anywhere in Dubai or Sharjah');

            $inclusions3 = ['Hotel Pickup & Drop-off in 4x4 Land Cruiser', 'Dune Bashing (30-45 mins)', 'Sunset Photo Stop', 'Camel Riding & Sandboarding', 'Lavish Buffet BBQ Dinner (Veg & Non-Veg)', 'Live Belly Dance & Fire Show'];
            update_post_meta($tour3Id, '_tourivo_inclusions', wp_slash(wp_json_encode($inclusions3, JSON_UNESCAPED_UNICODE)));
        }

        // 3. Seed Hotels & Rooms
        // Hotel 1: Ocean Paradise Resort (Bali)
        $hotel1Id = wp_insert_post([
            'post_title'   => 'Ocean Paradise Resort & Spa',
            'post_content' => 'Set directly along the pristine golden sands of Seminyak Beach, Ocean Paradise Resort & Spa offers 5-star luxury accommodations with panoramic Indian Ocean views, 3 infinity swimming pools, and an award-winning wellness sanctuary.',
            'post_status'  => 'publish',
            'post_type'    => HotelPostType::POST_TYPE,
        ]);

        if ($hotel1Id && !is_wp_error($hotel1Id)) {
            $hotelsCount++;
            wp_set_object_terms($hotel1Id, [$destBali], TourPostType::TAX_DESTINATION);
            wp_set_object_terms($hotel1Id, [$amenWifi, $amenPool, $amenAc, $amenBreak, $amenSpa], HotelPostType::TAX_AMENITY);

            update_post_meta($hotel1Id, '_tourivo_star_rating', 5);
            update_post_meta($hotel1Id, '_tourivo_city', 'Bali / Seminyak');
            update_post_meta($hotel1Id, '_tourivo_address', 'Jl. Pantai Seminyak No. 88, Badung');
            update_post_meta($hotel1Id, '_tourivo_postal_code', '80361');
            update_post_meta($hotel1Id, '_tourivo_check_in_time', '14:00');
            update_post_meta($hotel1Id, '_tourivo_check_out_time', '11:00');
            update_post_meta($hotel1Id, '_tourivo_phone', '+62 361 889900');
            update_post_meta($hotel1Id, '_tourivo_email', 'reservations@oceanparadisebali.com');
            update_post_meta($hotel1Id, '_tourivo_policy', 'Children of all ages welcome. Valid passport or national ID required at check-in.');

            // Room 1.1: Deluxe Sea View Suite
            $room1 = wp_insert_post([
                'post_title'   => 'Deluxe Sea View Suite',
                'post_content' => 'Spacious 45m² ocean-facing suite featuring a private balcony, king-size canopy bed, marble bathroom with deep soaking tub, and espresso machine.',
                'post_status'  => 'publish',
                'post_type'    => RoomPostType::POST_TYPE,
            ]);
            if ($room1 && !is_wp_error($room1)) {
                $roomsCount++;
                update_post_meta($room1, '_tourivo_parent_hotel_id', $hotel1Id);
                update_post_meta($room1, '_tourivo_nightly_price', '180.00');
                update_post_meta($room1, '_tourivo_max_adults', 2);
                update_post_meta($room1, '_tourivo_max_children', 1);
                update_post_meta($room1, '_tourivo_max_guests', 3);
                update_post_meta($room1, '_tourivo_room_quantity', 10);
                update_post_meta($room1, '_tourivo_bed_type', '1 King Bed');
                update_post_meta($room1, '_tourivo_room_size', '45 m² / 484 ft²');
            }

            // Room 1.2: Two-Bedroom Pool Villa
            $room2 = wp_insert_post([
                'post_title'   => 'Two-Bedroom Private Pool Villa',
                'post_content' => 'Ultimate tropical luxury featuring a private 8-meter infinity plunge pool, lush private sun deck, separate living pavilion, and butler service.',
                'post_status'  => 'publish',
                'post_type'    => RoomPostType::POST_TYPE,
            ]);
            if ($room2 && !is_wp_error($room2)) {
                $roomsCount++;
                update_post_meta($room2, '_tourivo_parent_hotel_id', $hotel1Id);
                update_post_meta($room2, '_tourivo_nightly_price', '320.00');
                update_post_meta($room2, '_tourivo_max_adults', 4);
                update_post_meta($room2, '_tourivo_max_children', 2);
                update_post_meta($room2, '_tourivo_max_guests', 6);
                update_post_meta($room2, '_tourivo_room_quantity', 4);
                update_post_meta($room2, '_tourivo_bed_type', '2 King Beds');
                update_post_meta($room2, '_tourivo_room_size', '120 m² / 1,290 ft²');
            }
            update_post_meta($hotel1Id, '_tourivo_min_price', 180.00);
        }

        // Hotel 2: Alpine Panorama Lodge (Switzerland)
        $hotel2Id = wp_insert_post([
            'post_title'   => 'Alpine Panorama Grand Lodge',
            'post_content' => 'Nestled in the heart of Interlaken with direct views of the Eiger and Jungfrau peaks. Combining traditional Swiss woodwork with modern alpine comforts.',
            'post_status'  => 'publish',
            'post_type'    => HotelPostType::POST_TYPE,
        ]);

        if ($hotel2Id && !is_wp_error($hotel2Id)) {
            $hotelsCount++;
            wp_set_object_terms($hotel2Id, [$destSwiss], TourPostType::TAX_DESTINATION);
            wp_set_object_terms($hotel2Id, [$amenWifi, $amenAc, $amenBreak], HotelPostType::TAX_AMENITY);

            update_post_meta($hotel2Id, '_tourivo_star_rating', 4);
            update_post_meta($hotel2Id, '_tourivo_city', 'Interlaken');
            update_post_meta($hotel2Id, '_tourivo_address', 'Höheweg 42, 3800 Interlaken');
            update_post_meta($hotel2Id, '_tourivo_check_in_time', '15:00');
            update_post_meta($hotel2Id, '_tourivo_check_out_time', '11:00');

            // Room 2.1: Mountain View Double
            $room3 = wp_insert_post([
                'post_title'   => 'Mountain View Double Room',
                'post_content' => 'Cozy wooden-furnished room with private balcony offering direct views of the Swiss Alps.',
                'post_status'  => 'publish',
                'post_type'    => RoomPostType::POST_TYPE,
            ]);
            if ($room3 && !is_wp_error($room3)) {
                $roomsCount++;
                update_post_meta($room3, '_tourivo_parent_hotel_id', $hotel2Id);
                update_post_meta($room3, '_tourivo_nightly_price', '140.00');
                update_post_meta($room3, '_tourivo_max_adults', 2);
                update_post_meta($room3, '_tourivo_max_children', 0);
                update_post_meta($room3, '_tourivo_max_guests', 2);
                update_post_meta($room3, '_tourivo_room_quantity', 8);
                update_post_meta($room3, '_tourivo_bed_type', '1 Double Bed or 2 Twins');
                update_post_meta($room3, '_tourivo_room_size', '28 m²');
            }

            // Room 2.2: Junior Alpine Suite
            $room4 = wp_insert_post([
                'post_title'   => 'Junior Alpine Suite',
                'post_content' => 'Spacious alpine suite with panoramic terrace overlooking the Eiger glacier, fireplace, and whirlpool bath.',
                'post_status'  => 'publish',
                'post_type'    => RoomPostType::POST_TYPE,
            ]);
            if ($room4 && !is_wp_error($room4)) {
                $roomsCount++;
                update_post_meta($room4, '_tourivo_parent_hotel_id', $hotel2Id);
                update_post_meta($room4, '_tourivo_nightly_price', '210.00');
                update_post_meta($room4, '_tourivo_max_adults', 3);
                update_post_meta($room4, '_tourivo_max_children', 1);
                update_post_meta($room4, '_tourivo_max_guests', 4);
                update_post_meta($room4, '_tourivo_room_quantity', 5);
                update_post_meta($room4, '_tourivo_bed_type', '1 King Bed + 1 Sofa Bed');
                update_post_meta($room4, '_tourivo_room_size', '52 m²');
            }
            update_post_meta($hotel2Id, '_tourivo_min_price', 140.00);
        }

        return [
            'tours_created'  => $toursCount,
            'hotels_created' => $hotelsCount,
            'rooms_created'  => $roomsCount,
        ];
    }

    /**
     * Ensure a term exists in taxonomy.
     *
     * @param string $termName
     * @param string $taxonomy
     * @return int
     */
    protected static function ensureTerm(string $termName, string $taxonomy): int
    {
        $existing = term_exists($termName, $taxonomy);
        if ($existing) {
            return is_array($existing) ? (int) $existing['term_id'] : (int) $existing;
        }

        $inserted = wp_insert_term($termName, $taxonomy);
        return is_array($inserted) ? (int) $inserted['term_id'] : 0;
    }
}
