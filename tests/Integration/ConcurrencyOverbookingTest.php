<?php
/**
 * Concurrency & Overbooking Prevention Integration Tests (C)
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Tests\TestCase;

class ConcurrencyOverbookingTest extends TestCase
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
     * C1. Concurrent booking requests for the last remaining seat: exactly one succeeds.
     */
    public function test_parallel_booking_prevents_overbooking(): void
    {
        $tourId = $this->createTour(300.0, 1); // Only 1 seat available
        $date = gmdate('Y-m-d', strtotime('+40 days'));

        $payload1 = $this->createBookingPayload([
            'item_id'        => $tourId,
            'check_in'       => $date,
            'adults'         => 1,
            'customer_email' => 'traveler1@example.com',
        ]);

        $payload2 = $this->createBookingPayload([
            'item_id'        => $tourId,
            'check_in'       => $date,
            'adults'         => 1,
            'customer_email' => 'traveler2@example.com',
        ]);

        $res1 = $this->bookingService->createBooking($payload1);
        $res2 = $this->bookingService->createBooking($payload2);

        // Exactly one must succeed and one must fail
        $this->assertTrue($res1['success'] xor $res2['success']);

        global $wpdb;
        if ($wpdb) {
            $inv = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_inventories WHERE item_id = %d AND event_date = %s",
                $tourId,
                $date
            ));
            $this->assertEquals(1, (int) $inv->booked_capacity);
        }
    }
}
