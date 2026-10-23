<?php
/**
 * Bookings with several line items (e.g. a package: tours plus hotel nights).
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PricingService;
use Tourivo\Tests\TestCase;

class MultiItemBookingTest extends TestCase
{
    protected BookingService $bookingService;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();
        $this->inventoryService = new InventoryService(new InventoryRepository());
        $this->bookingService = new BookingService($this->inventoryService, new EmailService(), new PricingService($this->inventoryService));
    }

    private function date(int $days): string
    {
        return gmdate('Y-m-d', strtotime('+' . $days . ' days'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsOf(int $bookingId): array
    {
        global $wpdb;
        return array_values(array_filter($wpdb->booking_items, static fn (array $i): bool => (int) $i['booking_id'] === $bookingId));
    }

    private function spotsLeft(int $itemId, string $type, string $in, ?string $out = null): bool
    {
        return (bool) $this->inventoryService->checkAvailability($itemId, $type, $in, $out, 'all_day', 1, false)['available'];
    }

    public function test_tour_and_hotel_nights_in_one_booking(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $roomId = $this->createHotelWithRooms(1, 80.0)['room_id'];
        $in = $this->date(30);

        $result = $this->bookingService->createBooking($this->createBookingPayload([
            'item_id' => $tourId, 'check_in' => $in, 'adults' => 2,
            'extra_items' => [['item_id' => $roomId, 'check_in' => $in, 'check_out' => $this->date(32), 'rooms' => 1]],
        ]), true);

        $this->assertTrue($result['success'], (string) ($result['message'] ?? ''));
        $items = $this->itemsOf((int) $result['booking_id']);
        $this->assertCount(2, $items);
        $this->assertSame('room', $items[1]['item_type']);
        $this->assertEquals(200.0, (float) $items[0]['total_price']);
        $this->assertEquals(160.0, (float) $items[1]['total_price'], 'Two nights at 80.');

        global $wpdb;
        $this->assertEquals(360.0, (float) $wpdb->bookings[(int) $result['booking_id']]['total_amount']);
        $this->assertFalse($this->spotsLeft($roomId, 'room', $in, $this->date(32)), 'The only room is taken for those nights.');
    }

    public function test_unavailable_extra_item_gives_everything_back(): void
    {
        $tourId = $this->createTour(100.0, 2);
        $roomId = $this->createHotelWithRooms(1, 80.0)['room_id'];
        $in = $this->date(30);
        // Someone already has the only room.
        $this->assertTrue($this->bookingService->createBooking($this->createBookingPayload([
            'item_id' => $roomId, 'check_in' => $in, 'check_out' => $this->date(31), 'customer_email' => 'first@example.com',
        ]), true)['success']);

        $result = $this->bookingService->createBooking($this->createBookingPayload([
            'item_id' => $tourId, 'check_in' => $in, 'adults' => 2,
            'extra_items' => [['item_id' => $roomId, 'check_in' => $in, 'check_out' => $this->date(31)]],
        ]), true);

        $this->assertFalse($result['success']);
        $this->assertTrue($this->spotsLeft($tourId, 'tour', $in), 'The tour seats were not kept.');
        global $wpdb;
        $this->assertCount(1, $wpdb->bookings, 'No half booking was saved.');
    }

    public function test_booking_adjustment_is_itemised_and_counted(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $roomId = $this->createHotelWithRooms(2, 80.0)['room_id'];
        $in = $this->date(30);
        $filter = static fn (array $adjustments): array => array_merge($adjustments, [['title' => 'Package discount', 'total' => -36.0]]);
        add_filter('tourivo/booking_adjustments', $filter);
        try {
            $result = $this->bookingService->createBooking($this->createBookingPayload([
                'item_id' => $tourId, 'check_in' => $in, 'adults' => 2,
                'extra_items' => [['item_id' => $roomId, 'check_in' => $in, 'check_out' => $this->date(32)]],
            ]), true);
        } finally {
            remove_filter('tourivo/booking_adjustments', $filter);
        }

        $this->assertTrue($result['success'], (string) ($result['message'] ?? ''));
        global $wpdb;
        $booking = $wpdb->bookings[(int) $result['booking_id']];
        $this->assertEquals(324.0, (float) $booking['total_amount'], '200 + 160 - 36');
        $this->assertEquals(36.0, (float) $booking['discount_amount']);
        $first = $this->itemsOf((int) $result['booking_id'])[0];
        $lines = array_column(json_decode((string) $first['pricing_breakdown'], true)['lines'], 'total', 'title');
        $this->assertEquals(-36.0, (float) $lines['Package discount']);
        $this->assertEquals(164.0, (float) $first['total_price'], 'The first line carries the booking-wide discount.');
    }

    public function test_cancelling_releases_every_item(): void
    {
        $tourId = $this->createTour(100.0, 2);
        $roomId = $this->createHotelWithRooms(1, 80.0)['room_id'];
        $in = $this->date(30);
        $result = $this->bookingService->createBooking($this->createBookingPayload([
            'item_id' => $tourId, 'check_in' => $in, 'adults' => 2,
            'extra_items' => [['item_id' => $roomId, 'check_in' => $in, 'check_out' => $this->date(31)]],
        ]), true);
        $this->assertTrue($result['success'], (string) ($result['message'] ?? ''));
        $this->assertFalse($this->spotsLeft($tourId, 'tour', $in));

        $this->assertTrue($this->bookingService->changeStatus((int) $result['booking_id'], 'cancelled')['success']);
        $this->assertTrue($this->spotsLeft($tourId, 'tour', $in));
        $this->assertTrue($this->spotsLeft($roomId, 'room', $in, $this->date(31)));
    }

    public function test_screens_get_one_title_and_the_full_date_range(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $roomId = $this->createHotelWithRooms(2, 80.0)['room_id'];
        $result = $this->bookingService->createBooking($this->createBookingPayload([
            'item_id' => $tourId, 'check_in' => $this->date(30), 'adults' => 2,
            'extra_items' => [['item_id' => $roomId, 'check_in' => $this->date(30), 'check_out' => $this->date(33)]],
        ]), true);
        $this->assertTrue($result['success'], (string) ($result['message'] ?? ''));
        $id = (int) $result['booking_id'];

        global $wpdb;
        $rows = array_map(static fn (array $r): object => (object) $r, $this->itemsOf($id));
        $summary = \Tourivo\Support\BookingItems::summaryRow($rows, $id);
        $this->assertStringContainsString(' + ', $summary->item_title);
        $this->assertSame($this->date(30), substr($summary->check_in, 0, 10));
        $this->assertSame($this->date(33), substr($summary->check_out, 0, 10));

        $name = static fn (string $title, int $bookingId): string => $bookingId > 0 ? 'Cox\'s Bazar 3 Days' : $title;
        add_filter('tourivo/booking_title', $name, 10, 2);
        try {
            $this->assertSame('Cox\'s Bazar 3 Days', \Tourivo\Support\BookingItems::title($rows, $id), 'Add-ons can name the booking.');
        } finally {
            remove_filter('tourivo/booking_title', $name, 10);
        }

        $this->assertSame('Only', \Tourivo\Support\BookingItems::title([(object) ['item_title' => 'Only']]));
        $this->assertNull(\Tourivo\Support\BookingItems::summaryRow([]));
    }

    public function test_public_request_cannot_add_items_on_its_own(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $roomId = $this->createHotelWithRooms(2, 80.0)['room_id'];
        $payload = $this->createBookingPayload([
            'item_id' => $tourId, 'check_in' => $this->date(30), 'adults' => 2,
            'extra_items' => [['item_id' => $roomId, 'check_in' => $this->date(30), 'check_out' => $this->date(31)]],
        ]);

        $public = $this->bookingService->createBooking($payload, false);
        $this->assertTrue($public['success'], (string) ($public['message'] ?? ''));
        $this->assertCount(1, $this->itemsOf((int) $public['booking_id']), 'The posted extra item is ignored.');

        $allow = static fn (): bool => true;
        add_filter('tourivo/accept_extra_items', $allow);
        try {
            $allowed = $this->bookingService->createBooking(array_merge($payload, ['customer_email' => 'second@example.com']), false);
        } finally {
            remove_filter('tourivo/accept_extra_items', $allow);
        }
        $this->assertTrue($allowed['success'], (string) ($allowed['message'] ?? ''));
        $this->assertCount(2, $this->itemsOf((int) $allowed['booking_id']));
    }

    public function test_extra_item_follows_the_same_rules(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $smallTour = $this->createTour(50.0, 2);
        $result = $this->bookingService->createBooking($this->createBookingPayload([
            'item_id' => $tourId, 'check_in' => $this->date(30), 'adults' => 4,
            'extra_items' => [['item_id' => $smallTour, 'check_in' => $this->date(31)]],
        ]), true);

        $this->assertFalse($result['success'], 'Four travelers do not fit a two-guest tour.');
        $this->assertStringContainsString('maximum of 2', (string) $result['message']);
    }
}
