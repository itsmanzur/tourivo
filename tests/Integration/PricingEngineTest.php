<?php
/**
 * Pricing Engine Integration Tests
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Controllers\Api\PricingController;
use Tourivo\Models\Room;
use Tourivo\Models\Tour;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PricingService;
use Tourivo\Tests\TestCase;

class PricingEngineTest extends TestCase
{
    protected InventoryRepository $inventoryRepo;
    protected InventoryService $inventoryService;
    protected PricingService $pricingService;
    protected BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        global $tourivo_mock_filters, $tourivo_mock_options;
        $tourivo_mock_filters = [];
        $tourivo_mock_options = [];

        $this->inventoryRepo = new InventoryRepository();
        $this->inventoryService = new InventoryService($this->inventoryRepo);
        $this->pricingService = new PricingService($this->inventoryService);
        $emailService = new EmailService();
        $this->bookingService = new BookingService($this->inventoryService, $emailService, $this->pricingService);
    }

    protected function tearDown(): void
    {
        global $tourivo_mock_filters, $tourivo_mock_options;
        $tourivo_mock_filters = [];
        $tourivo_mock_options = [];
        parent::tearDown();
    }

    /**
     * Test (a): price_override dates are respected in quote & createBooking.
     */
    public function test_price_override_dates_are_respected_in_quote_and_booking(): void
    {
        $tourId = $this->createTour(100.0, 20);
        $date = gmdate('Y-m-d', strtotime('+5 days'));

        // Upsert price override of 150.00 for this date
        $this->inventoryRepo->upsert($tourId, 'tour', $date, 'all_day', 20, 0, 0, 150.00, 'available');

        $quote = $this->pricingService->quote([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2,
            'children'  => 0,
            'infants'   => 0,
        ]);

        $this->assertTrue($quote['success']);
        $this->assertEquals(150.00, (float) $quote['unit_price']);
        $this->assertEquals(300.00, (float) $quote['subtotal']);
        $this->assertEquals(300.00, (float) $quote['total']);

        // Booking creation also uses price override
        $payload = $this->createBookingPayload([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2,
            'children'  => 0,
        ]);

        $res = $this->bookingService->createBooking($payload, false);
        $this->assertTrue($res['success']);

        global $wpdb;
        $booking = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = " . (int) $res['booking_id']);
        $this->assertEquals(300.00, (float) $booking->total_amount);
    }

    /**
     * Test (b): Multi-date room range with different nightly prices sums correctly.
     */
    public function test_multi_date_room_range_with_different_nightly_prices(): void
    {
        $hotel = $this->createHotelWithRooms(5, 200.0);
        $roomId = $hotel['room_id'];

        $d1 = gmdate('Y-m-d', strtotime('+2 days'));
        $d2 = gmdate('Y-m-d', strtotime('+3 days'));
        $d3 = gmdate('Y-m-d', strtotime('+4 days'));
        $checkout = gmdate('Y-m-d', strtotime('+5 days')); // 3 nights: d1, d2, d3

        // d1 is default 200.00, d2 has override 250.00, d3 has override 300.00
        $this->inventoryRepo->upsert($roomId, 'room', $d2, 'all_day', 5, 0, 0, 250.00, 'available');
        $this->inventoryRepo->upsert($roomId, 'room', $d3, 'all_day', 5, 0, 0, 300.00, 'available');

        // 2 rooms for 3 nights: (200 + 250 + 300) * 2 = 750 * 2 = 1500.00
        $quote = $this->pricingService->quote([
            'item_id'   => $roomId,
            'item_type' => 'room',
            'check_in'  => $d1,
            'check_out' => $checkout,
            'rooms'     => 2,
            'adults'    => 2,
            'children'  => 0,
        ]);

        $this->assertTrue($quote['success']);
        $this->assertEquals(3, $quote['nights']);
        $this->assertEquals(1500.00, (float) $quote['subtotal']);
        $this->assertEquals(1500.00, (float) $quote['total']);
    }

    /**
     * Test (c): Child pricing types (percent, fixed, free, full) and infant free.
     */
    public function test_child_pricing_types_and_infant_free(): void
    {
        $date = gmdate('Y-m-d', strtotime('+3 days'));

        // Case 1: Percent (50% of adult price)
        $tourPercent = $this->createTour(100.0, 10, [
            '_tourivo_child_price_type'  => 'percent',
            '_tourivo_child_price_value' => '50',
            '_tourivo_infants_free'      => 'yes',
        ]);

        $quote = $this->pricingService->quote([
            'item_id'   => $tourPercent,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2,   // 2 * 100 = 200
            'children'  => 2,   // 2 * 50 = 100
            'infants'   => 1,   // 1 * 0 = 0
        ]);
        $this->assertTrue($quote['success']);
        $this->assertEquals(300.00, (float) $quote['subtotal']);

        // Case 2: Fixed ($35 per child)
        $tourFixed = $this->createTour(100.0, 10, [
            '_tourivo_child_price_type'  => 'fixed',
            '_tourivo_child_price_value' => '35',
        ]);
        $quoteFixed = $this->pricingService->quote([
            'item_id'   => $tourFixed,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2,   // 2 * 100 = 200
            'children'  => 2,   // 2 * 35 = 70
            'infants'   => 0,
        ]);
        $this->assertTrue($quoteFixed['success']);
        $this->assertEquals(270.00, (float) $quoteFixed['subtotal']);

        // Case 3: Free ($0 per child)
        $tourFree = $this->createTour(100.0, 10, [
            '_tourivo_child_price_type' => 'free',
        ]);
        $quoteFree = $this->pricingService->quote([
            'item_id'   => $tourFree,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2,   // 200
            'children'  => 3,   // 0
        ]);
        $this->assertTrue($quoteFree['success']);
        $this->assertEquals(200.00, (float) $quoteFree['subtotal']);

        // Case 4: Full (100% per child)
        $tourFull = $this->createTour(100.0, 10, [
            '_tourivo_child_price_type' => 'full',
        ]);
        $quoteFull = $this->pricingService->quote([
            'item_id'   => $tourFull,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2,   // 200
            'children'  => 2,   // 200
        ]);
        $this->assertTrue($quoteFull['success']);
        $this->assertEquals(400.00, (float) $quoteFull['subtotal']);
    }

    /**
     * Test (d): min_guests validation rejects quotes/bookings below minimum.
     */
    public function test_min_guests_validation(): void
    {
        $tourId = $this->createTour(100.0, 10, [
            '_tourivo_min_guests' => 3,
        ]);
        $date = gmdate('Y-m-d', strtotime('+3 days'));

        // 1 adult + 1 child = 2 guests < 3 -> should fail
        $quoteFail = $this->pricingService->quote([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 1,
            'children'  => 1,
            'infants'   => 0,
        ]);
        $this->assertFalse($quoteFail['success']);
        $this->assertStringContainsString('minimum of 3 guests', $quoteFail['message']);

        // Booking service also rejects
        $payload = $this->createBookingPayload([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 1,
            'children'  => 1,
        ]);
        $res = $this->bookingService->createBooking($payload, false);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('minimum of 3 guests', $res['message']);

        // 2 adults + 1 child = 3 guests >= 3 -> should succeed
        $quoteSuccess = $this->pricingService->quote([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2,
            'children'  => 1,
        ]);
        $this->assertTrue($quoteSuccess['success']);
    }

    /**
     * Test (e): Tax calculations (exclusive, inclusive, disabled, applies_to).
     */
    public function test_tax_calculations(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $date = gmdate('Y-m-d', strtotime('+3 days'));

        // 1. Tax Exclusive: 10%
        update_option('tourivo_settings', [
            'tax_enabled'    => true,
            'tax_label'      => 'VAT',
            'tax_rate'       => 10.0,
            'tax_mode'       => 'exclusive',
            'tax_applies_to' => 'all',
        ]);

        $quote = $this->pricingService->quote([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2, // Subtotal 200.00
        ]);
        $this->assertTrue($quote['success']);
        $this->assertEquals(200.00, (float) $quote['subtotal']);
        $this->assertEquals(20.00, (float) $quote['tax']);
        $this->assertEquals(220.00, (float) $quote['total']);

        // 2. Tax Inclusive: 10%
        update_option('tourivo_settings', [
            'tax_enabled'    => true,
            'tax_label'      => 'VAT Included',
            'tax_rate'       => 10.0,
            'tax_mode'       => 'inclusive',
            'tax_applies_to' => 'all',
        ]);

        $quoteInc = $this->pricingService->quote([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2, // Base 200.00 total
        ]);
        $this->assertTrue($quoteInc['success']);
        $this->assertEquals(200.00, (float) $quoteInc['total']);
        // Tax portion extracted from 200: 200 - (200 / 1.10) = 18.18
        $this->assertEquals(18.18, (float) $quoteInc['tax']);

        // 3. Tax applies_to 'rooms' only -> tour has 0 tax
        update_option('tourivo_settings', [
            'tax_enabled'    => true,
            'tax_label'      => 'Room Tax',
            'tax_rate'       => 10.0,
            'tax_mode'       => 'exclusive',
            'tax_applies_to' => 'rooms',
        ]);

        $quoteRoomOnly = $this->pricingService->quote([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2,
        ]);
        $this->assertEquals(0.00, (float) $quoteRoomOnly['tax']);
        $this->assertEquals(200.00, (float) $quoteRoomOnly['total']);
    }

    /**
     * Test (f): Pro filter `tourivo/booking_price` modifies subtotal and tax applies on top.
     */
    public function test_pro_filter_compatibility(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $date = gmdate('Y-m-d', strtotime('+3 days'));

        update_option('tourivo_settings', [
            'tax_enabled'    => true,
            'tax_label'      => 'GST',
            'tax_rate'       => 10.0,
            'tax_mode'       => 'exclusive',
            'tax_applies_to' => 'all',
        ]);

        // Mock Pro plugin applying a coupon or tiered discount of $50
        add_filter('tourivo/booking_price', function ($pricingData, $context) {
            $pricingData['total_price'] = (float) $pricingData['total_price'] - 50.00;
            return $pricingData;
        }, 10, 2);

        $quote = $this->pricingService->quote([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2, // Base 200.00 -> Pro modified to 150.00
        ]);

        $this->assertTrue($quote['success']);
        $this->assertEquals(150.00, (float) $quote['subtotal']);
        $this->assertEquals(50.00, (float) $quote['discount']);
        // 10% tax on 150.00 = 15.00 -> Total 165.00
        $this->assertEquals(15.00, (float) $quote['tax']);
        $this->assertEquals(165.00, (float) $quote['total']);
    }

    /**
     * Test (g): Booking creation stores tax, discount, due amount, infants count, and pricing breakdown.
     */
    public function test_booking_creation_persists_breakdown_and_financials(): void
    {
        $tourId = $this->createTour(100.0, 10, [
            '_tourivo_child_price_type'  => 'percent',
            '_tourivo_child_price_value' => '50',
        ]);
        $date = gmdate('Y-m-d', strtotime('+3 days'));

        update_option('tourivo_settings', [
            'tax_enabled'    => true,
            'tax_label'      => 'VAT',
            'tax_rate'       => 10.0,
            'tax_mode'       => 'exclusive',
            'tax_applies_to' => 'all',
        ]);

        $payload = $this->createBookingPayload([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2, // 200
            'children'  => 1, // 50 -> subtotal 250, tax 25, total 275
            'infants'   => 1,
        ]);

        $res = $this->bookingService->createBooking($payload, false);
        $this->assertTrue($res['success']);

        global $wpdb;
        $booking = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = " . (int) $res['booking_id']);
        $this->assertEquals(275.00, (float) $booking->total_amount);
        $this->assertEquals(25.00, (float) $booking->tax_amount);
        $this->assertEquals(0.00, (float) $booking->discount_amount);
        $this->assertEquals(275.00, (float) $booking->due_amount);

        $item = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}tourivo_booking_items WHERE booking_id = " . (int) $res['booking_id']);
        $this->assertEquals(2, (int) $item->adults_count);
        $this->assertEquals(1, (int) $item->children_count);
        $this->assertEquals(1, (int) $item->infants_count);
        $this->assertNotEmpty($item->pricing_breakdown);

        $breakdown = json_decode($item->pricing_breakdown, true);
        $this->assertArrayHasKey('subtotal', $breakdown);
        $this->assertEquals(250.00, (float) $breakdown['subtotal']);
        $this->assertEquals(275.00, (float) $breakdown['total']);
    }

    /**
     * Test (h): REST PricingController /tourivo/v1/pricing/quote endpoint computes valid quote.
     */
    public function test_rest_pricing_quote_endpoint(): void
    {
        $tourId = $this->createTour(150.0, 10, [
            '_tourivo_child_price_type'  => 'percent',
            '_tourivo_child_price_value' => '50',
        ]);
        $date = gmdate('Y-m-d', strtotime('+4 days'));

        $container = Container::getInstance();
        $pricingController = new PricingController($container, $this->pricingService);

        $request = new \WP_REST_Request('GET', '/tourivo/v1/pricing/quote');
        $request->set_query_params([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2, // 300
            'children'  => 1, // 75 -> total 375
            'infants'   => 1, // 0
        ]);

        $res = $pricingController->quote($request);
        $this->assertFalse(is_wp_error($res));
        $this->assertEquals(200, $res->get_status());

        $data = $res->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(375.00, (float) $data['subtotal']);
        $this->assertEquals(375.00, (float) $data['total']);
        $this->assertCount(3, $data['lines']);
    }

    /**
     * Test (i): Infants do not consume capacity unless tourivo/infants_use_capacity filter is enabled.
     */
    public function test_infant_capacity_filter(): void
    {
        // Tour with capacity of exactly 2 spots
        $tourId = $this->createTour(100.0, 2);
        $date = gmdate('Y-m-d', strtotime('+6 days'));

        // Default: 2 adults + 1 infant = 2 paying guests (infants don't count towards capacity) -> SUT succeeds
        $payload = $this->createBookingPayload([
            'item_id'   => $tourId,
            'item_type' => 'tour',
            'check_in'  => $date,
            'adults'    => 2,
            'children'  => 0,
            'infants'   => 1,
        ]);
        $res = $this->bookingService->createBooking($payload, false);
        $this->assertTrue($res['success']);

        // Clean up database for second subtest
        $this->cleanDatabaseTables();

        // When filter returns true, 2 adults + 1 infant = 3 capacity guests > 2 spots -> rejected
        add_filter('tourivo/infants_use_capacity', '__return_true');

        $res2 = $this->bookingService->createBooking($payload, false);
        $this->assertFalse($res2['success']);
        $this->assertStringContainsString('maximum of 2 guests', $res2['message']);
    }
}
