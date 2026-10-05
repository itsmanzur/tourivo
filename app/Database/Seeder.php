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
     * Get all centralized demo post definitions (Tours, Hotels, Rooms) with their metadata and key identifiers.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function getDemoDefinitions(): array
    {
        return [
            // ==========================================
            // Tours (5)
            // ==========================================
            'tour_1' => [
                'post_title'   => 'Bali Tropical Beach & Temple Explorer',
                'post_type'    => TourPostType::POST_TYPE,
                'post_content' => 'Experience the magic of Bali with our 5-day guided tropical adventure. Visit sacred water temples, relax on pristine sandy beaches, explore lush rice terraces in Ubud, and enjoy unforgettable ocean sunset dinners.',
                'terms'        => [
                    TourPostType::TAX_DESTINATION => 'Bali',
                    TourPostType::TAX_ACTIVITY    => 'Beach & Island',
                ],
                'key_meta'     => [
                    '_tourivo_duration'   => '5 Days / 4 Nights',
                    '_tourivo_base_price' => '349.00',
                ],
                'meta'         => [
                    '_tourivo_tour_type'        => 'multi_day',
                    '_tourivo_base_price'       => '349.00',
                    '_tourivo_sale_price'       => '299.00',
                    '_tourivo_duration'         => '5 Days / 4 Nights',
                    '_tourivo_duration_days'    => 5,
                    '_tourivo_min_guests'       => 1,
                    '_tourivo_max_guests'       => 16,
                    '_tourivo_badge'            => 'Bestseller',
                    '_tourivo_pickup_location'  => 'Ngurah Rai International Airport (DPS)',
                    '_tourivo_dropoff_location' => 'Kuta / Seminyak Central Hub',
                    '_tourivo_itinerary'        => [
                        ['day' => '1', 'title' => 'Arrival & Welcome Dinner', 'desc' => 'Airport pickup, hotel check-in in Seminyak, followed by a beachfront welcome seafood dinner with live acoustic music.', 'meals' => 'Dinner'],
                        ['day' => '2', 'title' => 'Ubud Waterfalls & Rice Terraces', 'desc' => 'Explore the scenic Tegallalang rice paddies, Tegenungan Waterfall, and Ubud Traditional Art Market.', 'meals' => 'Breakfast, Lunch'],
                        ['day' => '3', 'title' => 'Sacred Temples & Mount Batur View', 'desc' => 'Visit Ulun Danu Beratan temple on Lake Beratan, followed by lunch overlooking the Batur volcano.', 'meals' => 'Breakfast, Lunch'],
                        ['day' => '4', 'title' => 'Nusa Penida Island Day Cruise', 'desc' => 'Speedboat transfer to Nusa Penida, visiting Kelingking Secret Point Cliff, Angel’s Billabong, and Broken Beach.', 'meals' => 'Breakfast, Lunch'],
                        ['day' => '5', 'title' => 'Souvenir Shopping & Airport Departure', 'desc' => 'Morning leisure time, local artisan souvenir shopping, and airport drop-off.', 'meals' => 'Breakfast'],
                    ],
                    '_tourivo_inclusions'       => ['4 Nights in 4-Star Hotel Accommodation', 'Daily Breakfast and 3 Lunches', 'Private AC Vehicle Transportation', 'English Speaking Professional Guide', 'All Temple and Park Entrance Fees'],
                    '_tourivo_exclusions'       => ['International Flights', 'Travel Insurance', 'Personal Expenses and Tips'],
                    '_tourivo_faqs'             => [
                        ['question' => 'Is pickup included from all hotels in Bali?', 'answer' => 'Yes, free pickup is provided from Kuta, Seminyak, Sanur, Nusa Dua, and Ubud central zones.'],
                        ['question' => 'What is the cancellation policy?', 'answer' => 'Full refund if cancelled up to 7 days before departure date.'],
                    ],
                ],
            ],

            'tour_2' => [
                'post_title'   => 'Swiss Alps Glacier & Panorama Trek',
                'post_type'    => TourPostType::POST_TYPE,
                'post_content' => 'An exhilarating 4-day alpine journey across the Swiss Alps. Experience glacier viewpoints, cable car rides to peak summits, tranquil mountain lakes, and cozy alpine chalets in Interlaken and Zermatt.',
                'terms'        => [
                    TourPostType::TAX_DESTINATION => 'Switzerland',
                    TourPostType::TAX_ACTIVITY    => 'Mountain Adventure',
                ],
                'key_meta'     => [
                    '_tourivo_duration'   => '4 Days / 3 Nights',
                    '_tourivo_base_price' => '599.00',
                ],
                'meta'         => [
                    '_tourivo_tour_type'     => 'multi_day',
                    '_tourivo_base_price'    => '599.00',
                    '_tourivo_sale_price'    => '549.00',
                    '_tourivo_duration'      => '4 Days / 3 Nights',
                    '_tourivo_duration_days' => 4,
                    '_tourivo_min_guests'    => 2,
                    '_tourivo_max_guests'    => 12,
                    '_tourivo_badge'         => 'Alpine Top Pick',
                    '_tourivo_itinerary'     => [
                        ['day' => '1', 'title' => 'Zurich to Interlaken & Lake Brienz', 'desc' => 'Scenic train ride to Interlaken, turquoise boat cruise on Lake Brienz, and traditional Swiss cheese fondue dinner.', 'meals' => 'Dinner'],
                        ['day' => '2', 'title' => 'Jungfraujoch – Top of Europe', 'desc' => 'Ascend to the highest railway station in Europe at 3,454m. Explore the Ice Palace and Sphinx Observatory.', 'meals' => 'Breakfast, Lunch'],
                        ['day' => '3', 'title' => 'Zermatt & Matterhorn Glacier Trail', 'desc' => 'Travel to car-free Zermatt. Ride the Gornergrat cogwheel train with direct vistas of the iconic Matterhorn.', 'meals' => 'Breakfast'],
                        ['day' => '4', 'title' => 'Alpine Village Walk & Return to Zurich', 'desc' => 'Explore charming wooden chalets, chocolate tasting, and return express train to Zurich HB.', 'meals' => 'Breakfast'],
                    ],
                    '_tourivo_inclusions'    => ['Swiss Travel Pass 4-Day Pass', '3 Nights Alpine Hotel Stay', 'Jungfraujoch Summit Pass', 'Certified Mountain Guide'],
                    '_tourivo_exclusions'    => ['Ski gear rental', 'Personal meals outside itinerary'],
                ],
            ],

            'tour_3' => [
                'post_title'   => 'Dubai Luxury Desert Safari & Dhow Cruise',
                'post_type'    => TourPostType::POST_TYPE,
                'post_content' => 'The ultimate 1-day Dubai experience featuring 4x4 red dune bashing, camel riding, sandboarding, BBQ dinner under desert stars with Tanoura dance, and luxury city transfers.',
                'terms'        => [
                    TourPostType::TAX_DESTINATION => 'Dubai',
                    TourPostType::TAX_ACTIVITY    => 'City Sightseeing',
                ],
                'key_meta'     => [
                    '_tourivo_duration'   => '8 Hours',
                    '_tourivo_base_price' => '140.00',
                ],
                'meta'         => [
                    '_tourivo_tour_type'        => 'single_day',
                    '_tourivo_base_price'       => '140.00',
                    '_tourivo_sale_price'       => '119.00',
                    '_tourivo_duration'         => '8 Hours',
                    '_tourivo_duration_days'    => 1,
                    '_tourivo_min_guests'       => 1,
                    '_tourivo_max_guests'       => 30,
                    '_tourivo_badge'            => 'Popular Day Trip',
                    '_tourivo_pickup_location'  => 'Hotel Lobby anywhere in Dubai or Sharjah',
                    '_tourivo_inclusions'       => ['Hotel Pickup & Drop-off in 4x4 Land Cruiser', 'Dune Bashing (30-45 mins)', 'Sunset Photo Stop', 'Camel Riding & Sandboarding', 'Lavish Buffet BBQ Dinner (Veg & Non-Veg)', 'Live Belly Dance & Fire Show'],
                ],
            ],

            'tour_4' => [
                'post_title'   => "কক্সবাজার ৩ দিন ২ রাত বিচ ট্যুর ও মেরিন ড্রাইভ (Cox's Bazar Beach Tour)",
                'post_type'    => TourPostType::POST_TYPE,
                'post_content' => 'বিশ্বের দীর্ঘতম প্রাকৃতিক সমুদ্র সৈকত কক্সবাজারে ৩ দিন ২ রাতের প্রিমিয়াম হলিডে ট্যুর। ইনানী বিচ, হিমছড়ি ঝরনা, মেরিন ড্রাইভ সূর্যাস্ত এবং কলাতলী সৈকতের এক্সক্লুসিভ সি-ফুড ডিনারের দারুণ অভিজ্ঞতা।',
                'terms'        => [
                    TourPostType::TAX_DESTINATION => "Cox's Bazar / কক্সবাজার",
                    TourPostType::TAX_ACTIVITY    => 'Beach & Island',
                ],
                'key_meta'     => [
                    '_tourivo_duration'   => '৩ দিন / ২ রাত',
                    '_tourivo_base_price' => '9500.00',
                ],
                'meta'         => [
                    '_tourivo_tour_type'        => 'multi_day',
                    '_tourivo_base_price'       => '9500.00',
                    '_tourivo_sale_price'       => '8200.00',
                    '_tourivo_duration'         => '৩ দিন / ২ রাত',
                    '_tourivo_duration_days'    => 3,
                    '_tourivo_min_guests'       => 1,
                    '_tourivo_max_guests'       => 20,
                    '_tourivo_badge'            => 'হট ডিল',
                    '_tourivo_pickup_location'  => 'কক্সবাজার বিমানবন্দর বা বাস টার্মিনাল',
                    '_tourivo_dropoff_location' => 'কলাতলী মোড় / বিমানবন্দর',
                    '_tourivo_itinerary'        => [
                        ['day' => '১', 'title' => 'কক্সবাজার আগমন ও সুগন্ধা বিচ সূর্যাস্ত', 'desc' => 'বিমানবন্দর/বাস টার্মিনাল থেকে পিকআপ ও রিসোর্টে চেক-ইন। বিকেলে লাবণী ও সুগন্ধা বিচে সময় কাটানো এবং সন্ধ্যায় বিখ্যাত রুপচাঁদা ফ্রাই সহ ওয়েলকাম ডিনার।', 'meals' => 'ডিনার'],
                        ['day' => '২', 'title' => 'মেরিন ড্রাইভ, হিমছড়ি ঝরনা ও ইনানী বিচ ড্রাইভ', 'desc' => 'খোলা জিপে মেরিন ড্রাইভ রোড ভ্রমণ, হিমছড়ি পাহাড়ের চূড়া থেকে সমুদ্র দর্শন এবং ইনানী প্রবাল দ্বীপে স্নান।', 'meals' => 'ব্রেকফাস্ট, লাঞ্চ'],
                        ['day' => '৩', 'title' => 'বার্মিজ মার্কেট শপিং ও ঢাকা প্রস্থান', 'desc' => 'সকালে বিচে মুক্ত সময় ও ছবি তোলা। দুপুরের পর ঐতিহ্যবাহী বার্মিজ মার্কেটে শুঁটকি ও আচার শপিং শেষে বিমানবন্দরে ড্রপ।', 'meals' => 'ব্রেকফাস্ট'],
                    ],
                    '_tourivo_inclusions'       => ['২ রাত প্রিমিয়াম বিচ রিসোর্টে থাকার ব্যবস্থা', 'প্রতিদিন সকালের নাস্তা এবং একটি স্পেশাল সি-ফুড ডিনার', 'মেরিন ড্রাইভ ও ইনানী সাইটসিয়িং প্রাইভেট গাড়ি', 'অভিজ্ঞ বাংলা ও ইংরেজি গাইড সার্ভিস'],
                    '_tourivo_exclusions'       => ['ঢাকা-কক্সবাজার এয়ার বা বাস টিকিট', 'ব্যক্তিগত কেনাকাটা ও রাইড খরচ'],
                    '_tourivo_faqs'             => [
                        ['question' => 'ফ্যামিলি বা কাপলদের জন্য কি নিরাপদ?', 'answer' => 'সম্পূর্ণ নিরাপদ এবং পারিবারিক পরিবেশের মানসম্মত রিসোর্টে ব্যবস্থা করা হয়।'],
                        ['question' => 'বুকিং ক্যান্সেল করলে রিফান্ড পাওয়া যাবে?', 'answer' => 'যাত্রার ৩ দিন আগে জানালে শতভাগ রিফান্ড প্রদান করা হয়।'],
                    ],
                ],
            ],

            'tour_5' => [
                'post_title'   => 'সাজেক ভ্যালি ও মেঘের রাজ্য ৩ দিন ২ রাত প্রিমিয়াম ট্যুর (Sajek Valley Tour)',
                'post_type'    => TourPostType::POST_TYPE,
                'post_content' => 'পাহাড়ের চূড়ায় মেঘের দেশে হারিয়ে যাওয়ার রোমাঞ্চকর ৩ দিন ২ রাতের সফর। খোলা চাঁদের গাড়িতে পাহাড়ি আঁকাবাঁকা পথ ভ্রমণ, কংলাক পাহাড় ট্র্যাকিং, রুইলুই পাড়ার ঐতিহ্যবাহী জীবন এবং পাহাড়ি ব্যাম্বু চিকেন ডিনার।',
                'terms'        => [
                    TourPostType::TAX_DESTINATION => 'Sajek Valley / সাজেক ভ্যালি',
                    TourPostType::TAX_ACTIVITY    => 'Mountain Adventure',
                ],
                'key_meta'     => [
                    '_tourivo_duration'   => '৩ দিন / ২ রাত',
                    '_tourivo_base_price' => '7500.00',
                ],
                'meta'         => [
                    '_tourivo_tour_type'        => 'multi_day',
                    '_tourivo_base_price'       => '7500.00',
                    '_tourivo_sale_price'       => '6500.00',
                    '_tourivo_duration'         => '৩ দিন / ২ রাত',
                    '_tourivo_duration_days'    => 3,
                    '_tourivo_min_guests'       => 1,
                    '_tourivo_max_guests'       => 24,
                    '_tourivo_badge'            => 'ক্লাউড ট্যুর',
                    '_tourivo_pickup_location'  => 'খাগড়াছড়ি বাসস্ট্যান্ড বা দীঘিনালা',
                    '_tourivo_dropoff_location' => 'খাগড়াছড়ি বাসস্ট্যান্ড',
                    '_tourivo_itinerary'        => [
                        ['day' => '১', 'title' => 'চাঁদের গাড়িতে মেঘের দেশে যাত্রা ও রুইলুই সূর্যাস্ত', 'desc' => 'দীঘিনালা আর্মি এস্কর্টের সাথে পাহাড়ি মেঘের মধ্য দিয়ে সাজেক পৌঁছানো। বিকেলে রুইলুই পাড়ায় মুক্ত বিচরণ ও হেলিপ্যাডে সূর্যাস্ত উপভোগ। রাতে স্পেশাল ব্যাম্বু চিকেন বারবিকিউ।', 'meals' => 'লাঞ্চ, ডিনার'],
                        ['day' => '২', 'title' => 'হেলিপ্যাডে মেঘের সমুদ্র দর্শন ও কংলাক পাহাড় অভিযান', 'desc' => 'ভোরে কটেজের ব্যালকনি থেকে মেঘের ভেলা অবলোকন। সকালে সাজেকের সর্বোচ্চ চূড়া কংলাক পাহাড়ে ট্র্যাকিং ও স্থানীয় আদিবাসী সংস্কৃতির অভিজ্ঞতা।', 'meals' => 'ব্রেকফাস্ট, লাঞ্চ, ডিনার'],
                        ['day' => '৩', 'title' => 'আলুটিলা গুহা ও তারাতারেং ঝরনা ভ্রমণ শেষে প্রস্থান', 'desc' => 'সকালে সাজেক থেকে খাগড়াছড়ি ফিরে আলুটিলা রহস্যময় গুহা ও রিছাং ঝরনায় স্নান। ঐতিহ্যবাহী পাহাড়ি রেস্তোরাঁয় লাঞ্চ শেষে ঢাকার গাড়িতে উঠা।', 'meals' => 'ব্রেকফাস্ট, লাঞ্চ'],
                    ],
                    '_tourivo_inclusions'       => ['২ রাত সাজেকের সেরা প্রিমিয়াম রিসোর্টে থাকার ব্যবস্থা', 'খাগড়াছড়ি-সাজেক আপ-ডাউন রিজার্ভ চাঁদের গাড়ি', 'প্রতিদিনের সকল খাবার (৬ বেলা)', 'সকল এন্ট্রি ফি ও অভিজ্ঞ গাইড ফি'],
                    '_tourivo_exclusions'       => ['ঢাকা-খাগড়াছড়ি দূরপাল্লার বাস টিকিট', 'ব্যক্তিগত ওষুধ ও শপিং খরচ'],
                    '_tourivo_faqs'             => [
                        ['question' => 'চাঁদের গাড়িতে কি অন্যান্য যাত্রীদের সাথে শেয়ার করতে হবে?', 'answer' => 'না, আমাদের প্রতিটি ট্রিপেই নির্ধারিত গ্রুপের জন্য সম্পূর্ণ প্রাইভেট রিজার্ভ গাড়ি দেওয়া হয়।'],
                        ['question' => 'সাজেকে বিদ্যুৎ ও ওয়াইফাই সুবিধা কেমন?', 'answer' => 'আমাদের নির্বাচিত প্রিমিয়াম রিসোর্টে সার্বক্ষণিক সোলার/জেনারেটর ব্যাকআপ ও ওয়াইফাই সুবিধা রয়েছে।'],
                    ],
                ],
            ],

            // ==========================================
            // Hotels (4)
            // ==========================================
            'hotel_1' => [
                'post_title'   => 'Ocean Paradise Resort & Spa',
                'post_type'    => HotelPostType::POST_TYPE,
                'post_content' => 'Set directly along the pristine golden sands of Seminyak Beach, Ocean Paradise Resort & Spa offers 5-star luxury accommodations with panoramic Indian Ocean views, 3 infinity swimming pools, and an award-winning wellness sanctuary.',
                'terms'        => [
                    TourPostType::TAX_DESTINATION => 'Bali',
                    HotelPostType::TAX_AMENITY    => ['Free High-Speed WiFi', 'Infinity Swimming Pool', 'Air Conditioning', 'Complimentary Breakfast', 'Luxury Spa & Wellness'],
                ],
                'key_meta'     => [
                    '_tourivo_star_rating' => 5,
                ],
                'meta'         => [
                    '_tourivo_star_rating'    => 5,
                    '_tourivo_city'           => 'Bali / Seminyak',
                    '_tourivo_address'        => 'Jl. Pantai Seminyak No. 88, Badung',
                    '_tourivo_postal_code'    => '80361',
                    '_tourivo_check_in_time'  => '14:00',
                    '_tourivo_check_out_time' => '11:00',
                    '_tourivo_phone'          => '+62 361 889900',
                    '_tourivo_email'          => 'reservations@oceanparadisebali.com',
                    '_tourivo_policy'         => 'Children of all ages welcome. Valid passport or national ID required at check-in.',
                ],
            ],

            'hotel_2' => [
                'post_title'   => 'Alpine Panorama Grand Lodge',
                'post_type'    => HotelPostType::POST_TYPE,
                'post_content' => 'Nestled in the heart of Interlaken with direct views of the Eiger and Jungfrau peaks. Combining traditional Swiss woodwork with modern alpine comforts.',
                'terms'        => [
                    TourPostType::TAX_DESTINATION => 'Switzerland',
                    HotelPostType::TAX_AMENITY    => ['Free High-Speed WiFi', 'Air Conditioning', 'Complimentary Breakfast'],
                ],
                'key_meta'     => [
                    '_tourivo_star_rating' => 4,
                ],
                'meta'         => [
                    '_tourivo_star_rating'    => 4,
                    '_tourivo_city'           => 'Interlaken',
                    '_tourivo_address'        => 'Höheweg 42, 3800 Interlaken',
                    '_tourivo_check_in_time'  => '15:00',
                    '_tourivo_check_out_time' => '11:00',
                ],
            ],

            'hotel_3' => [
                'post_title'   => "সায়মন বিচ রিসোর্ট - কক্সবাজার (Sayeman Beach Resort)",
                'post_type'    => HotelPostType::POST_TYPE,
                'post_content' => 'কলাতলী সমুদ্র সৈকতে সরাসরি ওশান-ভিউ সহ কক্সবাজারের অন্যতম ঐতিহ্যবাহী ও লাক্সারি ৫-স্টার রিসোর্ট। ইনফিনিটি সুইমিং পুল, বিশ্বমানের স্পা ও সি-ফুড ডাইনিংয়ের সেরা অভিজ্ঞতা।',
                'terms'        => [
                    TourPostType::TAX_DESTINATION => "Cox's Bazar / কক্সবাজার",
                    HotelPostType::TAX_AMENITY    => ['Free High-Speed WiFi', 'Infinity Swimming Pool', 'Air Conditioning', 'Complimentary Breakfast', 'Luxury Spa & Wellness'],
                ],
                'key_meta'     => [
                    '_tourivo_star_rating' => 5,
                ],
                'meta'         => [
                    '_tourivo_star_rating'    => 5,
                    '_tourivo_city'           => "Cox's Bazar",
                    '_tourivo_address'        => 'মেরিন ড্রাইভ রোড, কলাতলী, কক্সবাজার',
                    '_tourivo_check_in_time'  => '14:00',
                    '_tourivo_check_out_time' => '12:00',
                ],
            ],

            'hotel_4' => [
                'post_title'   => 'মেঘপল্লী রিসোর্ট - সাজেক ভ্যালি (Meghpolli Resort Sajek)',
                'post_type'    => HotelPostType::POST_TYPE,
                'post_content' => 'সাজেক ভ্যালির সর্বোচ্চ চূড়ায় অবস্থিত প্রিমিয়াম ইকো-রিসোর্ট। প্রতিটি রুমের সুবিশাল ব্যালকনি থেকে মেঘের ভেলা স্পর্শ করার এবং সূর্যোদয়ের মায়াবী দৃশ্য অবলোকনের অনন্য অভিজ্ঞতা।',
                'terms'        => [
                    TourPostType::TAX_DESTINATION => 'Sajek Valley / সাজেক ভ্যালি',
                    HotelPostType::TAX_AMENITY    => ['Free High-Speed WiFi', 'Complimentary Breakfast'],
                ],
                'key_meta'     => [
                    '_tourivo_star_rating' => 4,
                ],
                'meta'         => [
                    '_tourivo_star_rating'    => 4,
                    '_tourivo_city'           => 'Sajek Valley',
                    '_tourivo_address'        => 'রুইলুই পাড়া, সাজেক ভ্যালি, বাঘাইছড়ি',
                    '_tourivo_check_in_time'  => '13:00',
                    '_tourivo_check_out_time' => '11:00',
                ],
            ],

            // ==========================================
            // Rooms (8)
            // ==========================================
            'room_1_1' => [
                'post_title'       => 'Deluxe Sea View Suite',
                'post_type'        => RoomPostType::POST_TYPE,
                'post_content'     => 'Spacious 45m² ocean-facing suite featuring a private balcony, king-size canopy bed, marble bathroom with deep soaking tub, and espresso machine.',
                'parent_hotel_key' => 'hotel_1',
                'key_meta'         => [
                    '_tourivo_nightly_price' => '180.00',
                ],
                'meta'             => [
                    '_tourivo_nightly_price' => '180.00',
                    '_tourivo_max_adults'    => 2,
                    '_tourivo_max_children'  => 1,
                    '_tourivo_max_guests'    => 3,
                    '_tourivo_room_quantity' => 10,
                    '_tourivo_bed_type'      => '1 King Bed',
                    '_tourivo_room_size'     => '45 m² / 484 ft²',
                ],
            ],

            'room_1_2' => [
                'post_title'       => 'Two-Bedroom Private Pool Villa',
                'post_type'        => RoomPostType::POST_TYPE,
                'post_content'     => 'Ultimate tropical luxury featuring a private 8-meter infinity plunge pool, lush private sun deck, separate living pavilion, and butler service.',
                'parent_hotel_key' => 'hotel_1',
                'hotel_min_price'  => 180.00,
                'key_meta'         => [
                    '_tourivo_nightly_price' => '320.00',
                ],
                'meta'             => [
                    '_tourivo_nightly_price' => '320.00',
                    '_tourivo_max_adults'    => 4,
                    '_tourivo_max_children'  => 2,
                    '_tourivo_max_guests'    => 6,
                    '_tourivo_room_quantity' => 4,
                    '_tourivo_bed_type'      => '2 King Beds',
                    '_tourivo_room_size'     => '120 m² / 1,290 ft²',
                ],
            ],

            'room_2_1' => [
                'post_title'       => 'Mountain View Double Room',
                'post_type'        => RoomPostType::POST_TYPE,
                'post_content'     => 'Cozy wooden-furnished room with private balcony offering direct views of the Swiss Alps.',
                'parent_hotel_key' => 'hotel_2',
                'key_meta'         => [
                    '_tourivo_nightly_price' => '140.00',
                ],
                'meta'             => [
                    '_tourivo_nightly_price' => '140.00',
                    '_tourivo_max_adults'    => 2,
                    '_tourivo_max_children'  => 0,
                    '_tourivo_max_guests'    => 2,
                    '_tourivo_room_quantity' => 8,
                    '_tourivo_bed_type'      => '1 Double Bed or 2 Twins',
                    '_tourivo_room_size'     => '28 m²',
                ],
            ],

            'room_2_2' => [
                'post_title'       => 'Junior Alpine Suite',
                'post_type'        => RoomPostType::POST_TYPE,
                'post_content'     => 'Spacious alpine suite with panoramic terrace overlooking the Eiger glacier, fireplace, and whirlpool bath.',
                'parent_hotel_key' => 'hotel_2',
                'hotel_min_price'  => 140.00,
                'key_meta'         => [
                    '_tourivo_nightly_price' => '210.00',
                ],
                'meta'             => [
                    '_tourivo_nightly_price' => '210.00',
                    '_tourivo_max_adults'    => 3,
                    '_tourivo_max_children'  => 1,
                    '_tourivo_max_guests'    => 4,
                    '_tourivo_room_quantity' => 5,
                    '_tourivo_bed_type'      => '1 King Bed + 1 Sofa Bed',
                    '_tourivo_room_size'     => '52 m²',
                ],
            ],

            'room_3_1' => [
                'post_title'       => 'সি ভিউ ডিলাক্স রুম (Sea View Deluxe)',
                'post_type'        => RoomPostType::POST_TYPE,
                'post_content'     => 'সরাসরি বঙ্গোপসাগরের ঢেউ দেখার মতো প্রাইভেট বারান্দা, এসি, বাথটাব এবং কিং বেড সমৃদ্ধ প্রিমিয়াম রুম।',
                'parent_hotel_key' => 'hotel_3',
                'key_meta'         => [
                    '_tourivo_nightly_price' => '7500.00',
                ],
                'meta'             => [
                    '_tourivo_nightly_price' => '7500.00',
                    '_tourivo_max_adults'    => 2,
                    '_tourivo_max_children'  => 1,
                    '_tourivo_max_guests'    => 3,
                    '_tourivo_room_quantity' => 12,
                    '_tourivo_bed_type'      => '১টি কিং সাইজ বেড',
                    '_tourivo_room_size'     => '৩৫ বর্গমিটার',
                ],
            ],

            'room_3_2' => [
                'post_title'       => 'ওশান ফ্রন্ট প্যানোরামিক স্যুইট (Ocean Suite)',
                'post_type'        => RoomPostType::POST_TYPE,
                'post_content'     => 'বিশাল লিভিং রুম, ডাইনিং স্পেস ও ১৮০ ডিগ্রি সমুদ্রের দৃশ্য সহ সর্বাধুনিক লাক্সারি স্যুইট।',
                'parent_hotel_key' => 'hotel_3',
                'hotel_min_price'  => 7500.00,
                'key_meta'         => [
                    '_tourivo_nightly_price' => '14500.00',
                ],
                'meta'             => [
                    '_tourivo_nightly_price' => '14500.00',
                    '_tourivo_max_adults'    => 4,
                    '_tourivo_max_children'  => 2,
                    '_tourivo_max_guests'    => 5,
                    '_tourivo_room_quantity' => 4,
                    '_tourivo_bed_type'      => '২টি কিং বেড + ১টি সোফা বেড',
                    '_tourivo_room_size'     => '৮৫ বর্গমিটার',
                ],
            ],

            'room_4_1' => [
                'post_title'       => 'ক্লাউড ভিউ উডেন কটেজ (Cloud View Cottage)',
                'post_type'        => RoomPostType::POST_TYPE,
                'post_content'     => 'সেগুন ও বাঁশের প্রাকৃতিক নকশায় নির্মিত প্রিমিয়াম কাপল কটেজ। সাথে রয়েছে মেঘের প্যানোরামিক ভিউ সহ প্রাইভেট বারান্দা।',
                'parent_hotel_key' => 'hotel_4',
                'key_meta'         => [
                    '_tourivo_nightly_price' => '4500.00',
                ],
                'meta'             => [
                    '_tourivo_nightly_price' => '4500.00',
                    '_tourivo_max_adults'    => 2,
                    '_tourivo_max_children'  => 1,
                    '_tourivo_max_guests'    => 3,
                    '_tourivo_room_quantity' => 8,
                    '_tourivo_bed_type'      => '১টি কুইন বেড',
                    '_tourivo_room_size'     => '২৮ বর্গমিটার',
                ],
            ],

            'room_4_2' => [
                'post_title'       => 'ভিআইপি ফ্যামিলি ক্লাউড স্যুইট (VIP Family Suite)',
                'post_type'        => RoomPostType::POST_TYPE,
                'post_content'     => 'পাহাড় ও মেঘের ১৮০ ডিগ্রি ভিউ সহ বিশাল পারিবারিক কটেজ স্যুইট। একসাথে ৪-৫ জনের আরামদায়ক থাকার সুবিধা।',
                'parent_hotel_key' => 'hotel_4',
                'hotel_min_price'  => 4500.00,
                'key_meta'         => [
                    '_tourivo_nightly_price' => '7500.00',
                ],
                'meta'             => [
                    '_tourivo_nightly_price' => '7500.00',
                    '_tourivo_max_adults'    => 4,
                    '_tourivo_max_children'  => 2,
                    '_tourivo_max_guests'    => 5,
                    '_tourivo_room_quantity' => 4,
                    '_tourivo_bed_type'      => '২টি ডাবল বেড',
                    '_tourivo_room_size'     => '৫৫ বর্গমিটার',
                ],
            ],
        ];
    }

    /**
     * Backfill _tourivo_demo=1 meta on legacy demo posts imported before meta tagging was introduced.
     * Matches both post title and exact key metadata from getDemoDefinitions() to avoid tagging user posts.
     *
     * @return int Number of legacy posts backfilled.
     */
    public static function backfillDemoMeta(): int
    {
        $definitions = self::getDemoDefinitions();
        $updated = 0;

        foreach ($definitions as $def) {
            $title = (string) $def['post_title'];
            $postType = (string) $def['post_type'];
            $keyMeta = (array) ($def['key_meta'] ?? []);

            $posts = get_posts([
                'post_type'      => $postType,
                'title'          => $title,
                'post_status'    => 'any',
                'posts_per_page' => 10,
                'fields'         => 'ids',
            ]);

            if (!empty($posts)) {
                foreach ($posts as $postId) {
                    $intId = (int) $postId;
                    if (get_post_meta($intId, '_tourivo_demo', true)) {
                        continue;
                    }

                    // Verify key meta signature matches definition
                    $matchesSignature = true;
                    foreach ($keyMeta as $metaKey => $expectedVal) {
                        $actualVal = get_post_meta($intId, $metaKey, true);
                        if ((string) $actualVal !== (string) $expectedVal) {
                            $matchesSignature = false;
                            break;
                        }
                    }

                    if ($matchesSignature) {
                        update_post_meta($intId, '_tourivo_demo', '1');
                        $updated++;
                    }
                }
            }
        }

        return $updated;
    }

    /**
     * Check if demo data already exists in database.
     *
     * @return bool
     */
    public static function hasExistingDemoData(): bool
    {
        $existing = get_posts([
            'post_type'      => [TourPostType::POST_TYPE, HotelPostType::POST_TYPE],
            'meta_key'       => '_tourivo_demo',
            'meta_value'     => '1',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);

        return !empty($existing);
    }

    /**
     * Delete partially created demo posts on creation failure.
     *
     * @param array<int> $postIds
     * @return void
     */
    protected static function rollbackCreatedPosts(array $postIds): void
    {
        foreach ($postIds as $id) {
            if ($id > 0) {
                wp_delete_post((int) $id, true);
            }
        }
    }

    /**
     * Run the sample data seeder using centralized demo definitions.
     *
     * @return array{tours_created: int, hotels_created: int, rooms_created: int, skipped?: bool, success?: bool, message?: string}
     */
    public static function run(): array
    {
        // First backfill any legacy un-tagged demo data to avoid duplicating existing demo content
        self::backfillDemoMeta();

        if (self::hasExistingDemoData()) {
            return [
                'success'        => true,
                'tours_created'  => 0,
                'hotels_created' => 0,
                'rooms_created'  => 0,
                'skipped'        => true,
                'message'        => __('Demo content is already imported. Skipping duplicate generation.', 'tourivo'),
            ];
        }

        $definitions = self::getDemoDefinitions();
        $createdPostIds = [];
        $createdPostMap = [];

        $toursCount = 0;
        $hotelsCount = 0;
        $roomsCount = 0;

        foreach ($definitions as $key => $def) {
            $postType = (string) $def['post_type'];
            $title    = (string) $def['post_title'];
            $content  = (string) $def['post_content'];

            $postId = self::insertDemoPost([
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => $postType,
            ]);

            if (!$postId) {
                self::rollbackCreatedPosts($createdPostIds);
                return [
                    'tours_created'  => 0,
                    'hotels_created' => 0,
                    'rooms_created'  => 0,
                    'success'        => false,
                    'message'        => __('Failed to insert demo item. Aborted and rolled back.', 'tourivo'),
                ];
            }

            $createdPostIds[] = $postId;
            $createdPostMap[$key] = $postId;

            if ($postType === TourPostType::POST_TYPE) {
                $toursCount++;
            } elseif ($postType === HotelPostType::POST_TYPE) {
                $hotelsCount++;
            } elseif ($postType === RoomPostType::POST_TYPE) {
                $roomsCount++;
            }

            // Assign taxonomy terms
            if (!empty($def['terms'])) {
                foreach ($def['terms'] as $taxonomy => $termData) {
                    $termNames = (array) $termData;
                    $termIds = [];
                    foreach ($termNames as $termName) {
                        $termId = self::ensureTerm((string) $termName, (string) $taxonomy);
                        if ($termId > 0) {
                            $termIds[] = $termId;
                        }
                    }
                    if (!empty($termIds)) {
                        wp_set_object_terms($postId, $termIds, (string) $taxonomy);
                    }
                }
            }

            // Assign parent hotel for rooms
            if (!empty($def['parent_hotel_key'])) {
                $parentHotelKey = (string) $def['parent_hotel_key'];
                $parentHotelId = $createdPostMap[$parentHotelKey] ?? 0;
                if ($parentHotelId > 0) {
                    update_post_meta($postId, '_tourivo_parent_hotel_id', $parentHotelId);
                    if (isset($def['hotel_min_price'])) {
                        update_post_meta($parentHotelId, '_tourivo_min_price', (float) $def['hotel_min_price']);
                    }
                }
            }

            // Assign all meta fields
            if (!empty($def['meta'])) {
                foreach ($def['meta'] as $metaKey => $metaValue) {
                    if (is_array($metaValue)) {
                        update_post_meta($postId, (string) $metaKey, wp_slash(wp_json_encode($metaValue, JSON_UNESCAPED_UNICODE)));
                    } else {
                        update_post_meta($postId, (string) $metaKey, $metaValue);
                    }
                }
            }
        }

        return [
            'success'        => true,
            'tours_created'  => $toursCount,
            'hotels_created' => $hotelsCount,
            'rooms_created'  => $roomsCount,
            'message'        => sprintf(
                /* translators: 1: Tours count, 2: Hotels count, 3: Rooms count */
                __('Sample data successfully imported! Created %1$d Tours, %2$d Hotels, and %3$d Rooms.', 'tourivo'),
                $toursCount,
                $hotelsCount,
                $roomsCount
            ),
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

    /**
     * Insert a demo post and tag it with _tourivo_demo=1.
     *
     * @param array<string, mixed> $args
     * @return int Post ID or 0 on failure.
     */
    protected static function insertDemoPost(array $args): int
    {
        $postId = wp_insert_post($args);
        if ($postId && !is_wp_error($postId)) {
            update_post_meta((int) $postId, '_tourivo_demo', '1');
            return (int) $postId;
        }
        return 0;
    }
}
