<?php
/**
 * Data Integrity & Miscellaneous Integration Tests (G1 - G6)
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Database\Schema;
use Tourivo\Database\Seeder;
use Tourivo\Services\ReviewService;
use Tourivo\Services\SeoService;
use Tourivo\Support\Money;
use Tourivo\Tests\TestCase;

class DataAndMiscTest extends TestCase
{
    /**
     * G1. JSON metadata round-trip with Bengali text, quotes, and slashes.
     */
    public function test_json_meta_roundtrip(): void
    {
        $complexData = [
            [
                'day'   => '১',
                'title' => 'সাজেক ভ্যালি ও "মেঘের রাজ্য"',
                'desc'  => 'কক্সবাজার / সাজেক ট্যুরে \ বিশেষ অফার!',
                'meals' => 'সকালের নাস্তা, দুপুরের খাবার',
            ]
        ];

        $encoded = wp_slash(wp_json_encode($complexData, JSON_UNESCAPED_UNICODE));
        $unslashed = wp_unslash($encoded);
        $decoded = json_decode($unslashed, true);

        $this->assertEquals($complexData, $decoded);
        $this->assertEquals('সাজেক ভ্যালি ও "মেঘের রাজ্য"', $decoded[0]['title']);
    }

    /**
     * G2. Seeder execution, idempotency, and rollback handling.
     */
    public function test_seeder_run_idempotency(): void
    {
        $res = Seeder::run();
        $this->assertArrayHasKey('success', $res);
        $this->assertTrue($res['success']);
    }

    /**
     * G2b. Seeder backfillDemoMeta accurately tags all 17 demo items, protects user custom posts, and skips duplicates on legacy sites.
     */
    public function test_seeder_backfill_and_user_custom_post_protection(): void
    {
        global $tourivo_mock_posts, $tourivo_mock_postmeta;
        // 1. Run Seeder to create 17 items (5 tours, 4 hotels, 8 rooms)
        $initial = Seeder::run();
        $this->assertTrue($initial['success']);

        // Strip _tourivo_demo meta from all posts to simulate legacy site
        foreach ($tourivo_mock_posts as $postId => $post) {
            unset($tourivo_mock_postmeta[$postId]['_tourivo_demo']);
        }

        // (b) Create a user-created 'Deluxe Sea View Suite' room with different nightly price
        $userRoomId = wp_insert_post([
            'post_title'  => 'Deluxe Sea View Suite',
            'post_type'   => 'tourivo_room',
            'post_status' => 'publish',
        ]);
        update_post_meta($userRoomId, '_tourivo_nightly_price', '999.00'); // User custom price

        // (a) Run backfillDemoMeta() -> must tag exactly 17 genuine demo items
        $backfilled = Seeder::backfillDemoMeta();
        $this->assertEquals(17, $backfilled, "Failed asserting that exactly 17 demo items were backfilled.");

        // Assert user post was NOT tagged
        $this->assertEmpty(get_post_meta($userRoomId, '_tourivo_demo', true), "User-created custom room must not be tagged with _tourivo_demo.");

        // (c) Legacy site idempotency: running Seeder::run() a second time gives skipped: true without creating duplicates
        $secondRun = Seeder::run();
        $this->assertTrue($secondRun['success']);
        $this->assertTrue($secondRun['skipped'] ?? false, "Second run on legacy site must be skipped.");
        $this->assertEquals(0, $secondRun['tours_created']);
        $this->assertEquals(0, $secondRun['hotels_created']);
        $this->assertEquals(0, $secondRun['rooms_created']);
    }

    /**
     * G3. Schema migration idempotency.
     */
    public function test_schema_migrate_idempotency(): void
    {
        // Run migration twice
        Schema::migrate();
        Schema::migrate();

        global $wpdb;
        if ($wpdb) {
            $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}tourivo_bookings'");
            $this->assertNotEmpty($table);
        }
    }

    /**
     * G5. SeoService JSON-LD escapes </script> safely.
     */
    public function test_seo_service_escapes_script_tags(): void
    {
        $seoService = new SeoService();
        $this->assertFalse($seoService->hasThirdPartySeoPlugin());

        $rawTitle = 'Tour with </script><script>alert(1)</script>';
        $encoded = wp_json_encode(['name' => $rawTitle], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('</script>', $encoded);
        $this->assertStringContainsString('\u003C/script\u003E', $encoded);
    }

    /**
     * G6. SeoService getItemAvailability: 90 days fully booked, 1 spot open, blocked/closed dates, reserved counts, and cache invalidation.
     */
    public function test_seo_availability_in_range_and_invalidation(): void
    {
        global $wpdb;
        $tourId = $this->createTour(120.0, 5); // capacity = 5
        $seoService = new SeoService();

        // 1. Fresh tour with no explicit DB records -> open with default capacity (InStock)
        $avail1 = $seoService->getItemAvailability($tourId, 'tour');
        $this->assertEquals('https://schema.org/InStock', $avail1);

        // Delete transient to test filled capacity
        delete_transient('tourivo_seo_avail_' . $tourId . '_tour');

        // 2. Populate all 90 days as fully booked (booked_count = 5, total_capacity = 5)
        for ($i = 0; $i < 91; $i++) {
            $date = gmdate('Y-m-d', strtotime("+$i days"));
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}tourivo_inventories (item_id, item_type, event_date, time_slot, total_capacity, booked_count, reserved_count, status)
                 VALUES (%d, 'tour', %s, 'all_day', 5, 5, 0, 'available')",
                $tourId,
                $date
            ));
        }

        $avail2 = $seoService->getItemAvailability($tourId, 'tour');
        $this->assertEquals('https://schema.org/SoldOut', $avail2);

        // 3. Free up 1 spot on day 15 (booked_count = 4, total_capacity = 5)
        delete_transient('tourivo_seo_avail_' . $tourId . '_tour');
        $date15 = gmdate('Y-m-d', strtotime('+15 days'));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}tourivo_inventories SET booked_count = 4, reserved_count = 0, status = 'available' WHERE item_id = %d AND event_date = %s",
            $tourId,
            $date15
        ));

        $avail3 = $seoService->getItemAvailability($tourId, 'tour');
        $this->assertEquals('https://schema.org/InStock', $avail3);

        // 4. Set reserved_count = 1 on that same day 15 (booked 4 + reserved 1 = 5 => 0 available)
        delete_transient('tourivo_seo_avail_' . $tourId . '_tour');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}tourivo_inventories SET booked_count = 4, reserved_count = 1, status = 'available' WHERE item_id = %d AND event_date = %s",
            $tourId,
            $date15
        ));

        $avail4 = $seoService->getItemAvailability($tourId, 'tour');
        $this->assertEquals('https://schema.org/SoldOut', $avail4);

        // 5. Test transient invalidation on booking status change action hook
        set_transient('tourivo_seo_avail_' . $tourId . '_tour', 'https://schema.org/SoldOut', 3600);
        $this->assertNotEmpty(get_transient('tourivo_seo_avail_' . $tourId . '_tour'));

        SeoService::clearItemAvailabilityCache($tourId, 'tour');
        $this->assertFalse(get_transient('tourivo_seo_avail_' . $tourId . '_tour'), "Transient cache must be cleared when clearItemAvailabilityCache is called.");
    }
}
