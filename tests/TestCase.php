<?php
/**
 * Tourivo Base TestCase
 */

declare(strict_types=1);

namespace Tourivo\Tests;

if (class_exists('WP_UnitTestCase')) {
    abstract class BaseTestCaseParent extends \WP_UnitTestCase {}
} elseif (class_exists('PHPUnit\Framework\TestCase')) {
    abstract class BaseTestCaseParent extends \PHPUnit\Framework\TestCase {}
} else {
    abstract class BaseTestCaseParent {}
}

/**
 * Base TestCase for Tourivo test suite.
 */
abstract class TestCase extends BaseTestCaseParent
{
    public function assertTrue(mixed $condition, string $msg = ''): void {
        if (!$condition) throw new \Exception($msg ?: 'Failed asserting that condition is true.');
    }
    public function assertFalse(mixed $condition, string $msg = ''): void {
        if ($condition) throw new \Exception($msg ?: 'Failed asserting that condition is false.');
    }
    public function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void {
        if ($expected !== $actual) throw new \Exception($msg ?: "Failed asserting that " . var_export($actual, true) . " equals " . var_export($expected, true) . ".");
    }
    public function assertNotEquals(mixed $expected, mixed $actual, string $msg = ''): void {
        if ($expected === $actual) throw new \Exception($msg ?: "Failed asserting that " . var_export($actual, true) . " does not equal " . var_export($expected, true) . ".");
    }
    public function assertNotEmpty(mixed $value, string $msg = ''): void {
        if (empty($value)) throw new \Exception($msg ?: 'Failed asserting that value is not empty.');
    }
    public function assertEmpty(mixed $value, string $msg = ''): void {
        if (!empty($value)) throw new \Exception($msg ?: 'Failed asserting that value is empty.');
    }
    public function assertCount(int $count, mixed $array, string $msg = ''): void {
        if (!is_countable($array) || count($array) !== $count) throw new \Exception($msg ?: "Failed asserting that count is $count.");
    }
    public function assertStringContainsString(string $needle, string $haystack, string $msg = ''): void {
        if (!str_contains($haystack, $needle)) throw new \Exception($msg ?: "Failed asserting that '$haystack' contains '$needle'.");
    }
    public function assertStringNotContainsString(string $needle, string $haystack, string $msg = ''): void {
        if (str_contains($haystack, $needle)) throw new \Exception($msg ?: "Failed asserting that '$haystack' does not contain '$needle'.");
    }
    public function assertNotNull(mixed $value, string $msg = ''): void {
        if ($value === null) throw new \Exception($msg ?: 'Failed asserting that value is not null.');
    }
    public function assertNull(mixed $value, string $msg = ''): void {
        if ($value !== null) throw new \Exception($msg ?: 'Failed asserting that value is null.');
    }
    public function assertArrayHasKey(string|int $key, mixed $array, string $msg = ''): void {
        if (!is_array($array) || !array_key_exists($key, $array)) throw new \Exception($msg ?: "Failed asserting array has key '$key'.");
    }

    /**
     * Set up test preconditions.
     */
    protected function setUp(): void
    {
        $this->cleanTransients();
    }

    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void
    {
        $this->cleanTransients();
    }

    /**
     * Helper to create a Tour post with metadata.
     *
     * @param float $price
     * @param int   $maxGuests
     * @param array<string, mixed> $extraMeta
     * @return int
     */
    public function createTour(float $price = 100.0, int $maxGuests = 10, array $extraMeta = []): int
    {
        if (function_exists('wp_insert_post')) {
            $postId = (int) wp_insert_post([
                'post_title'   => 'Test Tour ' . uniqid(),
                'post_type'    => 'tourivo_tour',
                'post_status'  => 'publish',
                'post_content' => 'Test tour description',
            ]);
            update_post_meta($postId, '_tourivo_tour_type', 'single_day');
            update_post_meta($postId, '_tourivo_base_price', number_format($price, 2, '.', ''));
            update_post_meta($postId, '_tourivo_max_guests', $maxGuests);
            update_post_meta($postId, '_tourivo_min_guests', 1);

            foreach ($extraMeta as $k => $v) {
                update_post_meta($postId, $k, $v);
            }
            return $postId;
        }

        return rand(100, 999);
    }

    /**
     * Helper to create a Hotel with associated Room.
     *
     * @param int   $roomQuantity
     * @param float $roomPrice
     * @param array<string, mixed> $extraMeta
     * @return array{hotel_id: int, room_id: int}
     */
    public function createHotelWithRooms(int $roomQuantity = 5, float $roomPrice = 150.0, array $extraMeta = []): array
    {
        if (function_exists('wp_insert_post')) {
            $hotelId = (int) wp_insert_post([
                'post_title'   => 'Test Hotel ' . uniqid(),
                'post_type'    => 'tourivo_hotel',
                'post_status'  => 'publish',
            ]);

            $roomId = (int) wp_insert_post([
                'post_title'   => 'Test Room ' . uniqid(),
                'post_type'    => 'tourivo_room',
                'post_status'  => 'publish',
            ]);

            update_post_meta($roomId, '_tourivo_parent_hotel_id', $hotelId);
            update_post_meta($roomId, '_tourivo_room_quantity', $roomQuantity);
            update_post_meta($roomId, '_tourivo_nightly_price', number_format($roomPrice, 2, '.', ''));
            update_post_meta($roomId, '_tourivo_room_base_price', number_format($roomPrice, 2, '.', ''));
            update_post_meta($roomId, '_tourivo_max_adults', 2);
            update_post_meta($roomId, '_tourivo_max_children', 1);
            update_post_meta($roomId, '_tourivo_max_guests', 3);

            foreach ($extraMeta as $k => $v) {
                update_post_meta($roomId, $k, $v);
            }

            return ['hotel_id' => $hotelId, 'room_id' => $roomId];
        }

        return ['hotel_id' => 101, 'room_id' => 102];
    }

    /**
     * Helper to create standard booking payload.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function createBookingPayload(array $overrides = []): array
    {
        $checkIn = (string) ($overrides['check_in'] ?? gmdate('Y-m-d', strtotime('+3 days')));
        $checkOut = (string) ($overrides['check_out'] ?? $checkIn);

        $defaults = [
            'item_id'         => 1,
            'item_type'       => 'tour',
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'adults'          => 2,
            'children'        => 0,
            'infants'         => 0,
            'customer_name'   => 'John Doe',
            'customer_email'  => 'john@example.com',
            'customer_phone'  => '+1234567890',
            'special_notes'   => 'Non-smoking please',
            'payment_method'  => 'offline',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Clean database tables.
     */
    public function cleanDatabaseTables(): void
    {
        global $wpdb, $tourivo_mock_usermeta;
        if ($wpdb) {
            $wpdb->bookings = [];
            $wpdb->booking_items = [];
            $wpdb->inventories = [];
            $wpdb->inquiries = [];
            $wpdb->logs = [];
        }
        $tourivo_mock_usermeta = [];
    }

    /**
     * Clean mock or real transients.
     */
    public function cleanTransients(): void
    {
        global $tourivo_mock_transients;
        $tourivo_mock_transients = [];
    }
}
