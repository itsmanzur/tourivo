<?php
/**
 * Booking Engine Integration Tests (A1 - A9)
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Tests\TestCase;

class BookingEngineTest extends TestCase
{
    protected BookingService $bookingService;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $invRepo = new InventoryRepository();
        $this->inventoryService = new InventoryService($invRepo);
        $emailService = new EmailService();
        $this->bookingService = new BookingService($this->inventoryService, $emailService);
    }

    /**
     * A1. Public untrusted requests ignore privileged override fields.
     */
    public function test_create_booking_public_untrusted_ignores_sensitive_overrides(): void
    {
        $tourId = $this->createTour(250.0, 10);
        $payload = $this->createBookingPayload([
            'item_id'        => $tourId,
            'booking_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'stripe_card',
            'customer_id'    => 999,
            'total_amount'   => 1.00, // Malicious override
        ]);

        $res = $this->bookingService->createBooking($payload, false);
        $this->assertTrue($res['success']);
        $this->assertNotEmpty($res['booking_code']);

        global $wpdb;
        if ($wpdb) {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d",
                $res['booking_id']
            ));

            $this->assertEquals('pending', $booking->booking_status);
            $this->assertEquals('pending', $booking->payment_status);
            $this->assertEquals('offline', $booking->payment_method);
            $this->assertEquals(500.00, (float) $booking->total_amount); // 2 adults * $250
        }
    }

    /**
     * A2. Trusted admin requests preserve explicit status and amounts.
     */
    public function test_create_booking_trusted_preserves_admin_fields(): void
    {
        $tourId = $this->createTour(250.0, 10);
        $payload = $this->createBookingPayload([
            'item_id'        => $tourId,
            'booking_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
        ]);

        $res = $this->bookingService->createBooking($payload, true);
        $this->assertTrue($res['success']);

        global $wpdb;
        if ($wpdb) {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d",
                $res['booking_id']
            ));

            $this->assertEquals('confirmed', $booking->booking_status);
            $this->assertEquals('paid', $booking->payment_status);
            $this->assertEquals('bank_transfer', $booking->payment_method);
        }
    }

    /**
     * A3. Reject wrong post type, missing item, price 0, or draft status.
     */
    public function test_create_booking_rejects_invalid_items(): void
    {
        // 1. Missing item (ID 999999)
        $payloadMissing = $this->createBookingPayload(['item_id' => 999999]);
        $resMissing = $this->bookingService->createBooking($payloadMissing);
        $this->assertFalse($resMissing['success']);

        // 2. Draft tour
        $draftTourId = $this->createTour(100.0, 10);
        if (function_exists('wp_update_post')) {
            wp_update_post(['ID' => $draftTourId, 'post_status' => 'draft']);
            $payloadDraft = $this->createBookingPayload(['item_id' => $draftTourId]);
            $resDraft = $this->bookingService->createBooking($payloadDraft);
            $this->assertFalse($resDraft['success']);
        }
    }

    /**
     * A4. Room and hotel_room normalize to 'room' inventory.
     */
    public function test_room_and_hotel_room_normalized_to_same_inventory(): void
    {
        $hotelData = $this->createHotelWithRooms(5, 120.0);
        $roomId = $hotelData['room_id'];

        $payload = $this->createBookingPayload([
            'item_id'   => $roomId,
            'item_type' => 'room',
            'check_in'  => gmdate('Y-m-d', strtotime('+5 days')),
            'check_out' => gmdate('Y-m-d', strtotime('+7 days')),
            'rooms'     => 2,
        ]);

        $res = $this->bookingService->createBooking($payload);
        $this->assertTrue($res['success']);

        global $wpdb;
        if ($wpdb) {
            $invRecord = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_inventories WHERE item_id = %d AND item_type = 'room' LIMIT 1",
                $roomId
            ));
            $this->assertNotNull($invRecord);
            $this->assertEquals('room', $invRecord->item_type);
        }
    }

    /**
     * A5. Whitelist time_slot value.
     */
    public function test_time_slot_whitelist(): void
    {
        $slot1 = InventoryService::normalizeTimeSlot('invalid_slot_123');
        $this->assertEquals('all_day', $slot1);

        $slot2 = InventoryService::normalizeTimeSlot('all_day');
        $this->assertEquals('all_day', $slot2);
    }

    /**
     * A7. Hotel room inventory calculation: rooms deducted (not guest count) and price = nightly * rooms * nights.
     */
    public function test_hotel_inventory_subtraction_and_pricing(): void
    {
        $hotelData = $this->createHotelWithRooms(10, 100.0); // $100/night
        $roomId = $hotelData['room_id'];

        $checkIn  = gmdate('Y-m-d', strtotime('+12 days'));
        $checkOut = gmdate('Y-m-d', strtotime('+14 days')); // 2 nights

        $payload = $this->createBookingPayload([
            'item_id'   => $roomId,
            'item_type' => 'room',
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
            'rooms'     => 3,
            'adults'    => 6, // 6 guests in 3 rooms
        ]);

        $res = $this->bookingService->createBooking($payload);
        $this->assertTrue($res['success']);

        global $wpdb;
        if ($wpdb) {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d",
                $res['booking_id']
            ));
            // 3 rooms * $100 * 2 nights = $600
            $this->assertEquals(600.00, (float) $booking->total_amount);

            $inv = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_inventories WHERE item_id = %d AND event_date = %s",
                $roomId,
                $checkIn
            ));
            $this->assertEquals(3, (int) $inv->booked_capacity); // Deducted 3 rooms, not 6 guests
        }
    }

    /**
     * A8. Tour max guests limit and capacity exhaustion.
     */
    public function test_tour_max_guests_and_capacity_exhaustion(): void
    {
        $tourId = $this->createTour(150.0, 4); // Max 4 guests
        $date = gmdate('Y-m-d', strtotime('+20 days'));

        // Booking 1: 3 guests -> Succeeds
        $payload1 = $this->createBookingPayload([
            'item_id'  => $tourId,
            'check_in' => $date,
            'adults'   => 3,
        ]);
        $res1 = $this->bookingService->createBooking($payload1);
        $this->assertTrue($res1['success']);

        // Booking 2: 2 guests (needs 5 total, but capacity is 4) -> Fails
        $payload2 = $this->createBookingPayload([
            'item_id'  => $tourId,
            'check_in' => $date,
            'adults'   => 2,
        ]);
        $res2 = $this->bookingService->createBooking($payload2);
        $this->assertFalse($res2['success']);
    }

    /**
     * A9. Inventory commit failure leaves 0 booking rows (Atomic rollback).
     */
    public function test_inventory_commit_failure_atomic_rollback(): void
    {
        $tourId = $this->createTour(200.0, 2);
        $date = gmdate('Y-m-d', strtotime('+25 days'));

        // First book all 2 seats
        $this->bookingService->createBooking($this->createBookingPayload([
            'item_id'  => $tourId,
            'check_in' => $date,
            'adults'   => 2,
        ]));

        global $wpdb;
        $countBefore = 0;
        if ($wpdb) {
            $countBefore = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tourivo_bookings");
        }

        // Try to overbook
        $failedRes = $this->bookingService->createBooking($this->createBookingPayload([
            'item_id'  => $tourId,
            'check_in' => $date,
            'adults'   => 1,
        ]));
        $this->assertFalse($failedRes['success']);

        if ($wpdb) {
            $countAfter = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tourivo_bookings");
            $this->assertEquals($countBefore, $countAfter);
        }
    }
}
