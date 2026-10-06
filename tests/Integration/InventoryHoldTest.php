<?php
/**
 * Checkout hold binding, expiry, atomic first-row insert and blocked-date regression tests.
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Controllers\Api\AvailabilityController;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Tests\TestCase;
use WP_REST_Request;

class InventoryHoldTest extends TestCase
{
    protected InventoryService $inventory;
    protected BookingService $bookings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();
        $GLOBALS['tourivo_mock_options'] = [];
        $GLOBALS['tourivo_mock_fail_next_inventory_insert'] = false;

        $this->inventory = new InventoryService(new InventoryRepository());
        $this->bookings  = new BookingService($this->inventory, new EmailService());
    }

    protected function inv(int $itemId, string $date): ?array
    {
        global $wpdb;
        return $wpdb->inventories["{$itemId}_{$date}"] ?? null;
    }

    protected function date(int $days = 40): string
    {
        return gmdate('Y-m-d', strtotime("+{$days} days"));
    }

    public function test_hold_token_cannot_unlock_a_different_item(): void
    {
        $tourA = $this->createTour(100.0, 5);
        $tourB = $this->createTour(100.0, 2);
        $date  = $this->date();

        // Fill tour B completely.
        $fill = $this->bookings->createBooking($this->createBookingPayload(['item_id' => $tourB, 'check_in' => $date, 'adults' => 2]));
        $this->assertTrue($fill['success'], 'Setup booking must succeed.');

        // Take a 1-seat hold on tour A and try to spend it on the full tour B.
        $token = $this->inventory->holdInventory($tourA, 'tour', $date, null, 'all_day', 1);
        $this->assertTrue(is_string($token));

        $res = $this->bookings->createBooking($this->createBookingPayload([
            'item_id' => $tourB, 'check_in' => $date, 'adults' => 1, 'hold_token' => $token,
        ]));

        $this->assertFalse($res['success'], 'A hold issued for another item must not bypass capacity.');
        $this->assertEquals(2, (int) $this->inv($tourB, $date)['booked_count']);
        $this->assertEquals(1, (int) $this->inv($tourA, $date)['reserved_count'], 'Foreign hold must stay intact.');
    }

    public function test_hold_token_for_other_dates_or_smaller_quantity_is_ignored(): void
    {
        $tour = $this->createTour(100.0, 3);
        $dateA = $this->date(40);
        $dateB = $this->date(41);

        $token = $this->inventory->holdInventory($tour, 'tour', $dateA, null, 'all_day', 1);
        $this->assertTrue(is_string($token));

        // Fill dateB; the dateA token must not let a booking through.
        $this->assertTrue($this->bookings->createBooking($this->createBookingPayload(['item_id' => $tour, 'check_in' => $dateB, 'adults' => 3]))['success']);
        $res = $this->bookings->createBooking($this->createBookingPayload([
            'item_id' => $tour, 'check_in' => $dateB, 'adults' => 1, 'hold_token' => $token,
        ]));
        $this->assertFalse($res['success']);

        // Smaller hold than booked quantity is not a valid hold for the booking: normal capacity rules apply.
        $res2 = $this->bookings->createBooking($this->createBookingPayload([
            'item_id' => $tour, 'check_in' => $dateA, 'adults' => 3, 'hold_token' => $token,
        ]));
        $this->assertFalse($res2['success'], '3 guests cannot fit: 1 spot is held by the token owner, 2 left.');
        $this->assertEquals(1, (int) $this->inv($tour, $dateA)['reserved_count']);
    }

    public function test_own_hold_does_not_block_the_booking_it_was_made_for(): void
    {
        $tour = $this->createTour(100.0, 3);
        $date = $this->date();

        $token = $this->inventory->holdInventory($tour, 'tour', $date, null, 'all_day', 3);
        $this->assertTrue(is_string($token));

        // Without the token the calendar is full...
        $this->assertFalse($this->inventory->checkAvailability($tour, 'tour', $date, null, 'all_day', 1)['available']);

        // ...but the hold owner can complete the booking.
        $res = $this->bookings->createBooking($this->createBookingPayload([
            'item_id' => $tour, 'check_in' => $date, 'adults' => 3, 'hold_token' => $token,
        ]));

        $this->assertTrue($res['success'], $res['message'] ?? 'Hold owner booking failed.');
        $row = $this->inv($tour, $date);
        $this->assertEquals(3, (int) $row['booked_count']);
        $this->assertEquals(0, (int) $row['reserved_count']);
        $this->assertFalse($this->inventory->getHold($token) !== null, 'Hold must be consumed after booking.');
    }

    public function test_surplus_hold_spots_are_released_on_commit(): void
    {
        $tour = $this->createTour(100.0, 5);
        $date = $this->date();

        $token = $this->inventory->holdInventory($tour, 'tour', $date, null, 'all_day', 3);
        $res = $this->bookings->createBooking($this->createBookingPayload([
            'item_id' => $tour, 'check_in' => $date, 'adults' => 2, 'hold_token' => $token,
        ]));

        $this->assertTrue($res['success']);
        $row = $this->inv($tour, $date);
        $this->assertEquals(2, (int) $row['booked_count']);
        $this->assertEquals(0, (int) $row['reserved_count']);
    }

    public function test_expired_holds_are_released_precisely_and_live_holds_survive(): void
    {
        $tour = $this->createTour(100.0, 10);
        $date = $this->date();

        $expired = $this->inventory->holdInventory($tour, 'tour', $date, null, 'all_day', 2);
        $live    = $this->inventory->holdInventory($tour, 'tour', $date, null, 'all_day', 3);
        $this->assertEquals(5, (int) $this->inv($tour, $date)['reserved_count']);

        // Age the first hold past its expiry.
        $opt = InventoryService::HOLD_OPTION_PREFIX . $expired;
        $data = $GLOBALS['tourivo_mock_options'][$opt];
        $data['expires_at'] = time() - 5;
        $GLOBALS['tourivo_mock_options'][$opt] = $data;

        $this->assertNull($this->inventory->getHold($expired));
        $released = $this->inventory->releaseExpiredHolds();

        $this->assertEquals(1, $released);
        $this->assertEquals(3, (int) $this->inv($tour, $date)['reserved_count'], 'Only the expired hold may be released.');
        $this->assertNotNull($this->inventory->getHold($live), 'Live hold must survive the sweep.');
    }

    public function test_explicit_release_returns_spots(): void
    {
        $tour = $this->createTour(100.0, 4);
        $date = $this->date();

        $token = $this->inventory->holdInventory($tour, 'tour', $date, null, 'all_day', 2);
        $this->assertTrue($this->inventory->releaseHold((string) $token));
        $this->assertEquals(0, (int) $this->inv($tour, $date)['reserved_count']);
        $this->assertFalse($this->inventory->releaseHold((string) $token), 'Second release is a no-op.');
    }

    public function test_commit_retries_after_losing_first_row_race(): void
    {
        $tour = $this->createTour(100.0, 5);
        $date = $this->date();

        // A concurrent request creates the date row first (booked=1); our INSERT then fails and must retry as UPDATE.
        $GLOBALS['tourivo_mock_fail_next_inventory_insert'] = true;
        $res = $this->bookings->createBooking($this->createBookingPayload(['item_id' => $tour, 'check_in' => $date, 'adults' => 1]));

        $this->assertTrue($res['success'], $res['message'] ?? '');
        $this->assertEquals(2, (int) $this->inv($tour, $date)['booked_count'], 'Both bookings must be counted, none dropped.');
    }

    public function test_commit_into_blocked_date_is_rejected_inside_the_locked_section(): void
    {
        $tour = $this->createTour(100.0, 5);
        $date = $this->date();

        $this->inventory->updateAvailability($tour, 'tour', $date, $date, ['status' => 'blocked']);

        $this->assertFalse($this->inventory->commitBooking($tour, 'tour', $date, null, 'all_day', 1));
        $this->assertEquals(0, (int) $this->inv($tour, $date)['booked_count']);
    }

    public function test_full_booking_on_fresh_date_marks_sold_out(): void
    {
        $tour = $this->createTour(100.0, 2);
        $date = $this->date();

        $this->assertTrue($this->bookings->createBooking($this->createBookingPayload(['item_id' => $tour, 'check_in' => $date, 'adults' => 2]))['success']);
        $this->assertEquals('sold_out', $this->inv($tour, $date)['status']);
    }

    public function test_hold_endpoint_caps_active_holds_per_client(): void
    {
        $tour = $this->createTour(100.0, 50);
        $controller = new AvailabilityController(Container::getInstance(), $this->inventory);

        $codes = [];
        for ($i = 0; $i < AvailabilityController::MAX_ACTIVE_HOLDS_PER_CLIENT + 1; $i++) {
            $request = new WP_REST_Request('POST', '/tourivo/v1/availability/hold');
            $request->set_param('item_id', $tour);
            $request->set_param('item_type', 'tour');
            $request->set_param('start_date', $this->date(50 + $i));
            $request->set_param('count', 1);

            $response = $controller->hold($request);
            $codes[] = is_wp_error($response) ? $response->get_error_code() : 'ok';
        }

        $this->assertEquals(['ok', 'ok', 'ok', 'too_many_holds'], $codes);
    }
}
