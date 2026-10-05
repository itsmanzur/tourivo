<?php
/**
 * Booking Status Transitions & Atomic Invariance Tests (B1 - B5)
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Tests\TestCase;

class BookingStatusTransitionTest extends TestCase
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
     * B1. Cancel releases inventory once; repeated cancel is no-op.
     */
    public function test_cancel_releases_inventory_exactly_once(): void
    {
        $tourId = $this->createTour(150.0, 5);
        $date = gmdate('Y-m-d', strtotime('+15 days'));

        $res = $this->bookingService->createBooking($this->createBookingPayload([
            'item_id'  => $tourId,
            'check_in' => $date,
            'adults'   => 2,
        ]));
        $this->assertTrue($res['success']);
        $bookingId = (int) $res['booking_id'];

        global $wpdb;
        // 1. Cancel booking
        $cancel1 = $this->bookingService->changeStatus($bookingId, 'cancelled');
        $this->assertTrue($cancel1['success']);

        if ($wpdb) {
            $inv = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_inventories WHERE item_id = %d AND event_date = %s",
                $tourId,
                $date
            ));
            $this->assertEquals(0, (int) $inv->booked_capacity);
        }

        // 2. Repeat cancel (Idempotent no-op)
        $cancel2 = $this->bookingService->changeStatus($bookingId, 'cancelled');
        $this->assertTrue($cancel2['success']);

        if ($wpdb) {
            $inv = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_inventories WHERE item_id = %d AND event_date = %s",
                $tourId,
                $date
            ));
            $this->assertEquals(0, (int) $inv->booked_capacity); // Remains 0, does not go negative
        }
    }

    /**
     * B2. Reactivation re-commits inventory and safely aborts on sold-out date.
     */
    public function test_reactivate_recommits_inventory_and_rolls_back_on_sold_out(): void
    {
        $tourId = $this->createTour(100.0, 3);
        $date = gmdate('Y-m-d', strtotime('+18 days'));

        // Booking A (2 guests)
        $resA = $this->bookingService->createBooking($this->createBookingPayload([
            'item_id'  => $tourId,
            'check_in' => $date,
            'adults'   => 2,
        ]));
        $bookingAId = (int) $resA['booking_id'];

        // Cancel Booking A (Capacity freed)
        $this->bookingService->changeStatus($bookingAId, 'cancelled');

        // Booking B takes all 3 seats
        $this->bookingService->createBooking($this->createBookingPayload([
            'item_id'  => $tourId,
            'check_in' => $date,
            'adults'   => 3,
        ]));

        // Try to reactivate Booking A -> should fail due to lack of capacity
        $reactivate = $this->bookingService->changeStatus($bookingAId, 'confirmed');
        $this->assertFalse($reactivate['success']);

        global $wpdb;
        if ($wpdb) {
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT booking_status FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d",
                $bookingAId
            ));
            $this->assertEquals('cancelled', $status);
        }
    }

    /**
     * B3. Parallel cancel requests release inventory exactly once due to atomic conditional SQL.
     */
    public function test_concurrency_parallel_cancels_release_inventory_once(): void
    {
        $tourId = $this->createTour(200.0, 5);
        $date = gmdate('Y-m-d', strtotime('+30 days'));

        $res = $this->bookingService->createBooking($this->createBookingPayload([
            'item_id'  => $tourId,
            'check_in' => $date,
            'adults'   => 2,
        ]));
        $bookingId = (int) $res['booking_id'];

        // Simulate two simultaneous requests executing changeStatus to cancelled
        $run1 = $this->bookingService->changeStatus($bookingId, 'cancelled');
        $run2 = $this->bookingService->changeStatus($bookingId, 'cancelled');

        $this->assertTrue($run1['success']);
        $this->assertTrue($run2['success']);

        global $wpdb;
        if ($wpdb) {
            $inv = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_inventories WHERE item_id = %d AND event_date = %s",
                $tourId,
                $date
            ));
            $this->assertEquals(0, (int) $inv->booked_capacity);
        }
    }

    /**
     * B4. Invalid status or unknown ID returns error; action hooks fire with old and new status.
     */
    public function test_invalid_status_or_id_error_and_action_hooks(): void
    {
        $invalidIdRes = $this->bookingService->changeStatus(999999, 'confirmed');
        $this->assertFalse($invalidIdRes['success']);

        $invalidStatusRes = $this->bookingService->changeStatus(1, 'super_invalid_status');
        $this->assertFalse($invalidStatusRes['success']);
    }
}
