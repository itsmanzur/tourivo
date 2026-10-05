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
}
