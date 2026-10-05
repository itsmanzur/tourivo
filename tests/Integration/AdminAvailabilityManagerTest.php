<?php
/**
 * Tourivo Admin Availability Manager Test Suite
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Admin\MetaBoxes\RoomMetaBox;
use Tourivo\Admin\MetaBoxes\TourMetaBox;
use Tourivo\Common\Container;
use Tourivo\Models\Tour;
use Tourivo\Providers\AdminServiceProvider;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\LogService;
use Tourivo\Services\SeoService;
use Tourivo\Tests\TestCase;
use WP_Post;

class AdminAvailabilityManagerTest extends TestCase
{
    protected InventoryRepository $invRepo;
    protected InventoryService $invService;
    protected BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();
        $this->invRepo = new InventoryRepository();
        $this->invService = new InventoryService($this->invRepo);
        $emailService = new EmailService();
        $this->bookingService = new BookingService($this->invService, $emailService);

        Container::getInstance()->singleton(InventoryRepository::class, fn () => $this->invRepo);
        Container::getInstance()->singleton(InventoryService::class, fn () => $this->invService);
        Container::getInstance()->singleton(BookingService::class, fn () => $this->bookingService);
    }

    /**
     * Test (a): Blocking a date rejects BookingService::createBooking and shows blocked in calendar.
     */
    public function test_blocked_date_rejects_create_booking_and_shows_blocked_in_calendar(): void
    {
        $tourId = $this->createTour(150.0, 10);
        $date = gmdate('Y-m-d', strtotime('+5 days'));

        // Block the date
        $res = $this->invService->updateAvailability($tourId, 'tour', $date, $date, [
            'status' => 'blocked',
        ]);

        $this->assertTrue($res['success'], 'Availability update should succeed when blocking date.');

        // Verify calendar shows blocked
        $year = (int) gmdate('Y', strtotime($date));
        $month = (int) gmdate('n', strtotime($date));
        $calendar = $this->invService->getCalendarAvailability($tourId, 'tour', $year, $month);

        $this->assertArrayHasKey($date, $calendar, "Calendar must contain entry for $date");
        $this->assertEquals('blocked', $calendar[$date]['status'], "Date status must be 'blocked' in calendar.");

        // Booking creation on blocked date must fail
        $payload = $this->createBookingPayload([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'check_out' => $date,
            'adults'    => 2,
        ]);

        $bookRes = $this->bookingService->createBooking($payload);
        $this->assertFalse($bookRes['success'], 'createBooking must fail on blocked date.');
    }

    /**
     * Test (b): Reducing capacity below booked count is rejected.
     */
    public function test_reduce_capacity_below_booked_count_is_rejected(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $date = gmdate('Y-m-d', strtotime('+10 days'));

        // Book 4 spots
        $payload = $this->createBookingPayload([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'check_out' => $date,
            'adults'    => 4,
        ]);
        $bookRes = $this->bookingService->createBooking($payload);
        $this->assertTrue($bookRes['success'], 'Initial booking of 4 spots should succeed.');

        // Attempt to reduce total capacity to 2 (below 4 booked)
        $res = $this->invService->updateAvailability($tourId, 'tour', $date, $date, [
            'capacity' => 2,
        ]);

        $this->assertFalse($res['success'], 'Setting capacity below existing booked spots must be rejected.');
        $this->assertStringContainsString('Cannot set capacity below active bookings', $res['message']);

        // Capacity setting >= 4 should succeed
        $validRes = $this->invService->updateAvailability($tourId, 'tour', $date, $date, [
            'capacity' => 6,
        ]);
        $this->assertTrue($validRes['success'], 'Setting capacity >= booked spots must succeed.');
    }

    /**
     * Test (c): Blocking booked date rejected without force, succeeds with force.
     */
    public function test_blocking_booked_date_rejected_without_force_succeeds_with_force(): void
    {
        $tourId = $this->createTour(120.0, 8);
        $date = gmdate('Y-m-d', strtotime('+12 days'));

        // Book 2 spots
        $payload = $this->createBookingPayload([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'check_out' => $date,
            'adults'    => 2,
        ]);
        $bookRes = $this->bookingService->createBooking($payload);
        $this->assertTrue($bookRes['success'], 'Initial booking should succeed.');

        // Try to block without force -> must fail
        $resNoForce = $this->invService->updateAvailability($tourId, 'tour', $date, $date, [
            'status' => 'blocked',
        ], false);

        $this->assertFalse($resNoForce['success'], 'Blocking a date with active bookings without force must fail.');
        $this->assertStringContainsString('active bookings', $resNoForce['message']);

        // Try to block WITH force -> must succeed without removing booked spots
        $resForce = $this->invService->updateAvailability($tourId, 'tour', $date, $date, [
            'status' => 'blocked',
        ], true);

        $this->assertTrue($resForce['success'], 'Blocking a date with active bookings WITH force must succeed.');

        // Verify record is blocked but booked_count is preserved
        $record = $this->invRepo->getRecord($tourId, 'tour', $date);
        $this->assertNotNull($record);
        $this->assertEquals('blocked', $record->status);
        $this->assertEquals(2, (int) $record->booked_count);
    }

    /**
     * Test (d): Price override is reflected in pricing and booking total.
     */
    public function test_price_override_reflected_in_pricing_and_booking_total(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $date = gmdate('Y-m-d', strtotime('+15 days'));

        // Default price check
        $this->assertEquals(100.0, $this->invService->getUnitPrice($tourId, 'tour', $date));

        // Set price override to 250.00
        $res = $this->invService->updateAvailability($tourId, 'tour', $date, $date, [
            'price_override' => 250.0,
        ]);
        $this->assertTrue($res['success'], 'Setting price override should succeed.');

        // Check unit price reflects override
        $this->assertEquals(250.0, $this->invService->getUnitPrice($tourId, 'tour', $date));

        // Create booking for 2 guests -> total price should be 500.00
        $payload = $this->createBookingPayload([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'check_out' => $date,
            'adults'    => 2,
        ]);
        $bookRes = $this->bookingService->createBooking($payload);
        $this->assertTrue($bookRes['success'], 'Booking with price override should succeed.');

        $booking = tourivo_get_booking($bookRes['booking_id']);
        $this->assertNotNull($booking);
        $this->assertEquals(500.0, (float) $booking->total_amount);

        // Reset price override
        $resetRes = $this->invService->updateAvailability($tourId, 'tour', $date, $date, [
            'reset_price' => true,
        ]);
        $this->assertTrue($resetRes['success'], 'Resetting price override should succeed.');
        $this->assertEquals(100.0, $this->invService->getUnitPrice($tourId, 'tour', $date));
    }

    /**
     * Test (e): Unauthorized user / invalid nonce rejected.
     */
    public function test_unauthorized_user_and_invalid_nonce_rejected(): void
    {
        $adminProvider = new AdminServiceProvider(Container::getInstance());

        // 1. Test invalid nonce
        $_POST = [
            'nonce'     => 'invalid_nonce_val',
            'item_id'   => 1,
            'item_type' => 'tour',
            'start_date'=> '2026-11-01',
            'end_date'  => '2026-11-02',
        ];

        $died = false;
        try {
            $adminProvider->handleUpdateAvailability();
        } catch (\Throwable $e) {
            $died = true;
        }

        $this->assertTrue($died, 'handleUpdateAvailability must abort on invalid nonce.');
        $this->assertEquals(403, $GLOBALS['tourivo_last_ajax_response']['status'] ?? 0);
    }

    /**
     * Test (f): Invalid date / ranges > 365 / invalid capacity rejected.
     */
    public function test_invalid_dates_ranges_over_365_and_invalid_capacity_rejected(): void
    {
        $tourId = $this->createTour(100.0, 10);

        // Invalid date format
        $resInvalidDate = $this->invService->updateAvailability($tourId, 'tour', 'not-a-date', '2026-11-05', [
            'status' => 'available',
        ]);
        $this->assertFalse($resInvalidDate['success'], 'Invalid date format must be rejected.');

        // Date range > 365 days
        $resOver365 = $this->invService->updateAvailability($tourId, 'tour', '2026-01-01', '2027-06-01', [
            'status' => 'available',
        ]);
        $this->assertFalse($resOver365['success'], 'Date range over 365 days must be rejected.');

        // Negative capacity
        $resNegCap = $this->invService->updateAvailability($tourId, 'tour', '2026-11-01', '2026-11-02', [
            'capacity' => -5,
        ]);
        $this->assertFalse($resNegCap['success'], 'Negative capacity must be rejected.');
    }

    /**
     * Test (g): SEO transient is cleared, action hook is fired, and LogService records audit entry.
     */
    public function test_seo_transient_cleared_and_log_service_audit_recorded_on_save(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $date = gmdate('Y-m-d', strtotime('+20 days'));

        // Pre-populate SEO transient
        set_transient('tourivo_seo_avail_' . $tourId . '_tour', 'https://schema.org/InStock', 3600);
        $this->assertEquals('https://schema.org/InStock', get_transient('tourivo_seo_avail_' . $tourId . '_tour'));

        // Clear mock actions
        $GLOBALS['tourivo_mock_actions'] = [];

        // Update availability
        $res = $this->invService->updateAvailability($tourId, 'tour', $date, $date, [
            'capacity' => 15,
            'status'   => 'available',
        ]);
        $this->assertTrue($res['success']);

        // 1. Verify SEO transient is deleted
        $this->assertFalse(get_transient('tourivo_seo_avail_' . $tourId . '_tour'), 'SEO transient must be cleared after availability update.');

        // 2. Verify LogService recorded audit entry in wp_tourivo_logs
        global $wpdb;
        $hasLog = false;
        foreach ($wpdb->logs as $log) {
            if (($log['action'] ?? '') === 'availability_update' && str_contains($log['details'] ?? '', (string) $tourId)) {
                $hasLog = true;
                break;
            }
        }
        $this->assertTrue($hasLog, 'LogService must record an availability_update audit log entry.');

        // 3. Verify do_action('tourivo/availability_updated', ...) fired
        $actionFired = false;
        foreach ($GLOBALS['tourivo_mock_actions'] as $act) {
            if ($act['hook'] === 'tourivo/availability_updated' && ($act['args'][0] ?? null) === $tourId) {
                $actionFired = true;
                break;
            }
        }
        $this->assertTrue($actionFired, "Hook 'tourivo/availability_updated' must be dispatched.");
    }

    /**
     * Test (h): TourMetaBox & RoomMetaBox save clears SEO transient.
     */
    public function test_tour_and_room_metabox_save_clears_seo_transients(): void
    {
        $hotelRoom = $this->createHotelWithRooms(4, 120.0);
        $hotelId = $hotelRoom['hotel_id'];
        $roomId = $hotelRoom['room_id'];

        set_transient('tourivo_seo_avail_' . $hotelId . '_hotel', 'https://schema.org/InStock', 3600);
        set_transient('tourivo_seo_avail_' . $roomId . '_room', 'https://schema.org/InStock', 3600);

        // Save RoomMetaBox
        $_POST = [
            '_tourivo_room_quantity'   => '5',
            '_tourivo_parent_hotel_id' => (string) $hotelId,
            '_tourivo_nightly_price'   => '130.00',
        ];

        $roomMetaBox = new RoomMetaBox();
        $roomPost = get_post($roomId);
        $roomMetaBox->save($roomId, $roomPost);

        $this->assertFalse(get_transient('tourivo_seo_avail_' . $hotelId . '_hotel'), 'Parent hotel SEO transient must be cleared on room save.');
        $this->assertFalse(get_transient('tourivo_seo_avail_' . $roomId . '_room'), 'Room SEO transient must be cleared on room save.');
    }
}
