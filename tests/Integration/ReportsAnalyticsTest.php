<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PricingService;
use Tourivo\Services\ReportingService;
use Tourivo\Tests\TestCase;
use WP_User;

/**
 * Class ReportsAnalyticsTest
 *
 * Tests for Admin Analytics, Sales & Occupancy Reports Suite:
 * 1. Date range resolution across presets (today, last_7_days, this_month, last_month, this_year, custom).
 * 2. Summary KPI metrics calculation (Total Bookings, Gross Revenue, Net Revenue, Tax, Discounts, AOV).
 * 3. Occupancy rate calculation from inventories table.
 * 4. Top performing tours and hotel rooms ranking by revenue and volume.
 * 5. Daily timeline sales and volume trend aggregation.
 * 6. Accounting & tax filing CSV export with formula injection protection and UTF-8 BOM.
 * 7. Unauthorized request rejection.
 */
class ReportsAnalyticsTest extends TestCase
{
    protected InventoryService $inventoryService;
    protected EmailService $emailService;
    protected BookingService $bookingService;
    protected PricingService $pricingService;
    protected ReportingService $reportingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();
        $this->cleanTransients();
        $_POST = [];
        $_GET  = [];

        $invRepo = new InventoryRepository();
        $this->inventoryService = new InventoryService($invRepo);
        $this->pricingService   = new PricingService($this->inventoryService);
        $this->emailService     = new EmailService();
        $this->bookingService   = new BookingService($this->inventoryService, $this->emailService, $this->pricingService);
        $this->reportingService = new ReportingService();

        $container = Container::getInstance();
        $container->instance(InventoryService::class, $this->inventoryService);
        $container->instance(EmailService::class, $this->emailService);
        $container->instance(BookingService::class, $this->bookingService);
        $container->instance(ReportingService::class, $this->reportingService);
    }

    /**
     * Helper to insert a mock booking and optional line items.
     */
    protected function insertMockBooking(array $bookingOverrides = [], array $itemOverrides = []): int
    {
        global $wpdb;

        $id = count($wpdb->bookings) + 1;
        $code = 'TRV-' . strtoupper(substr(md5((string) $id), 0, 8));

        $booking = array_merge([
            'id'                  => $id,
            'booking_code'        => $code,
            'customer_id'         => 1,
            'customer_name'       => 'Traveler ' . $id,
            'customer_email'      => "traveler{$id}@example.com",
            'customer_phone'      => '+1234567890',
            'billing_address'     => '123 Travel Way',
            'total_amount'        => 500.00,
            'tax_amount'          => 50.00,
            'discount_amount'     => 0.00,
            'paid_amount'         => 500.00,
            'due_amount'          => 0.00,
            'currency'            => 'USD',
            'payment_method'      => 'stripe',
            'payment_status'      => 'paid',
            'booking_status'      => 'confirmed',
            'check_in_status'     => 'pending',
            'transaction_id'      => 'txn_' . md5((string) $id),
            'customer_notes'      => '',
            'admin_notes'         => '',
            'cancel_requested_at' => null,
            'email_verified_at'   => '2026-10-01 12:00:00',
            'access_key'          => md5($code . 'secret'),
            'created_at'          => '2026-10-05 10:00:00',
            'updated_at'          => '2026-10-05 10:00:00',
        ], $bookingOverrides);

        $wpdb->bookings[$id] = $booking;

        // If line items specified or by default, add one line item
        $itemId = count($wpdb->booking_items) + 1;
        $item = array_merge([
            'id'             => $itemId,
            'booking_id'     => $id,
            'item_id'        => 101,
            'item_type'      => 'tour',
            'parent_item_id' => null,
            'item_title'     => 'Sundarbans Adventure',
            'check_in'       => '2026-10-15 09:00:00',
            'check_out'      => '2026-10-18 18:00:00',
            'time_slot'      => 'morning',
            'adults_count'   => 2,
            'children_count' => 0,
            'infants_count'  => 0,
            'quantity'       => 2,
            'unit_price'     => 225.00,
            'total_price'    => 450.00,
        ], $itemOverrides);

        $wpdb->booking_items[$itemId] = $item;

        return $id;
    }

    /**
     * 1. Test: Date range presets resolution.
     */
    public function test_date_range_resolution_presets(): void
    {
        $today = $this->reportingService->resolveDateRange('today');
        $this->assertSame(date('Y-m-d') . ' 00:00:00', $today['start']);
        $this->assertSame(date('Y-m-d') . ' 23:59:59', $today['end']);

        $last7 = $this->reportingService->resolveDateRange('last_7_days');
        $expectedLast7Start = date('Y-m-d', strtotime('-6 days')) . ' 00:00:00';
        $this->assertSame($expectedLast7Start, $last7['start']);

        $thisMonth = $this->reportingService->resolveDateRange('this_month');
        $this->assertSame(date('Y-m-01') . ' 00:00:00', $thisMonth['start']);

        $custom = $this->reportingService->resolveDateRange('custom', '2026-05-10', '2026-05-20');
        $this->assertSame('2026-05-10 00:00:00', $custom['start']);
        $this->assertSame('2026-05-20 23:59:59', $custom['end']);
    }

    /**
     * 2. Test: Summary KPI metrics calculations (Gross Revenue, Tax, Discounts, AOV).
     */
    public function test_kpi_metrics_calculations(): void
    {
        // Booking 1: Confirmed ($500 gross, $50 tax, $20 discount, $0 due)
        $this->insertMockBooking([
            'booking_status'  => 'confirmed',
            'total_amount'    => 500.00,
            'tax_amount'      => 50.00,
            'discount_amount' => 20.00,
            'due_amount'      => 0.00,
            'created_at'      => '2026-10-02 10:00:00',
        ]);

        // Booking 2: Pending ($300 gross, $30 tax, $0 discount, $300 due)
        $this->insertMockBooking([
            'booking_status'  => 'pending',
            'total_amount'    => 300.00,
            'tax_amount'      => 30.00,
            'discount_amount' => 0.00,
            'due_amount'      => 300.00,
            'created_at'      => '2026-10-04 11:00:00',
        ]);

        // Booking 3: Cancelled ($200 - should be excluded from active revenue calculations)
        $this->insertMockBooking([
            'booking_status'  => 'cancelled',
            'total_amount'    => 200.00,
            'tax_amount'      => 20.00,
            'discount_amount' => 0.00,
            'due_amount'      => 0.00,
            'created_at'      => '2026-10-06 12:00:00',
        ]);

        $metrics = $this->reportingService->getKpiMetrics('2026-10-01 00:00:00', '2026-10-10 23:59:59');

        $this->assertSame(3, $metrics['total_bookings']);
        $this->assertSame(1, $metrics['confirmed_bookings']);
        $this->assertSame(1, $metrics['cancelled_bookings']);
        $this->assertSame(1, $metrics['pending_bookings']);

        // Gross Revenue: 500 + 300 = 800.00
        $this->assertEquals(800.00, $metrics['gross_revenue']);

        // Total Tax: 50 + 30 = 80.00
        $this->assertEquals(80.00, $metrics['total_tax']);

        // Total Discounts: 20.00
        $this->assertEquals(20.00, $metrics['total_discount']);

        // Net Revenue: Gross (800) - Tax (80) - Discount (20) = 700.00
        $this->assertEquals(700.00, $metrics['net_revenue']);

        // Due Amount: 300.00
        $this->assertEquals(300.00, $metrics['due_amount']);

        // Average Order Value (AOV): 800 / 2 non-cancelled = 400.00
        $this->assertEquals(400.00, $metrics['average_order_value']);
    }

    /**
     * 3. Test: Occupancy rate calculation from inventories table.
     */
    public function test_occupancy_rate_calculation(): void
    {
        global $wpdb;

        // Inventory 1: Tour item, 40 total capacity, 30 booked (75%)
        $wpdb->inventories['101_2026-10-15'] = [
            'id'             => 1,
            'item_id'        => 101,
            'item_type'      => 'tour',
            'event_date'     => '2026-10-15',
            'time_slot'      => 'morning',
            'total_capacity' => 40,
            'booked_count'   => 30,
            'reserved_count' => 0,
            'status'         => 'available',
        ];

        // Inventory 2: Hotel room, 60 total capacity, 45 booked (75%)
        $wpdb->inventories['201_2026-10-16'] = [
            'id'             => 2,
            'item_id'        => 201,
            'item_type'      => 'room',
            'event_date'     => '2026-10-16',
            'time_slot'      => 'all_day',
            'total_capacity' => 60,
            'booked_count'   => 45,
            'reserved_count' => 0,
            'status'         => 'available',
        ];

        // Total: 75 booked / 100 capacity = 75.0%
        $occupancy = $this->reportingService->getOccupancyRate('2026-10-01 00:00:00', '2026-10-31 23:59:59');

        $this->assertEquals(75.0, $occupancy['rate']);
        $this->assertSame(75, $occupancy['total_booked']);
        $this->assertSame(100, $occupancy['total_capacity']);
    }

    /**
     * 4. Test: Top performing tours and hotel rooms ranking.
     */
    public function test_top_performing_items_ranking(): void
    {
        // Booking 1: Tour A ($900, 3 guests)
        $this->insertMockBooking(
            ['total_amount' => 900.00, 'created_at' => '2026-10-02 10:00:00'],
            [
                'item_id'        => 101,
                'item_type'      => 'tour',
                'item_title'     => 'Tour Alpha',
                'quantity'       => 3,
                'adults_count'   => 3,
                'total_price'    => 900.00,
            ]
        );

        // Booking 2: Tour B ($200, 1 guest)
        $this->insertMockBooking(
            ['total_amount' => 200.00, 'created_at' => '2026-10-03 10:00:00'],
            [
                'item_id'        => 102,
                'item_type'      => 'tour',
                'item_title'     => 'Tour Beta',
                'quantity'       => 1,
                'adults_count'   => 1,
                'total_price'    => 200.00,
            ]
        );

        // Booking 3: Room C ($1200, 2 guests)
        $this->insertMockBooking(
            ['total_amount' => 1200.00, 'created_at' => '2026-10-04 10:00:00'],
            [
                'item_id'        => 201,
                'item_type'      => 'room',
                'item_title'     => 'Deluxe Ocean Suite',
                'quantity'       => 4,
                'adults_count'   => 2,
                'total_price'    => 1200.00,
            ]
        );

        $top = $this->reportingService->getTopPerformingItems('2026-10-01 00:00:00', '2026-10-10 23:59:59');

        $this->assertCount(2, $top['tours']);
        $this->assertSame('Tour Alpha', $top['tours'][0]['item_title']);
        $this->assertEquals(900.00, $top['tours'][0]['total_sales']);
        $this->assertSame('Tour Beta', $top['tours'][1]['item_title']);
        $this->assertEquals(200.00, $top['tours'][1]['total_sales']);

        $this->assertCount(1, $top['rooms']);
        $this->assertSame('Deluxe Ocean Suite', $top['rooms'][0]['item_title']);
        $this->assertEquals(1200.00, $top['rooms'][0]['total_sales']);
    }

    /**
     * 5. Test: Timeline trend data (daily revenue & bookings count).
     */
    public function test_timeline_trend_data(): void
    {
        $this->insertMockBooking(['total_amount' => 300.00, 'created_at' => '2026-10-05 10:00:00']);
        $this->insertMockBooking(['total_amount' => 500.00, 'created_at' => '2026-10-05 14:00:00']);
        $this->insertMockBooking(['total_amount' => 450.00, 'created_at' => '2026-10-06 09:00:00']);

        $timeline = $this->reportingService->getTimelineData('2026-10-01 00:00:00', '2026-10-10 23:59:59');

        $this->assertArrayHasKey('2026-10-05', $timeline);
        $this->assertSame(2, $timeline['2026-10-05']['booking_count']);
        $this->assertEquals(800.00, $timeline['2026-10-05']['revenue']);

        $this->assertArrayHasKey('2026-10-06', $timeline);
        $this->assertSame(1, $timeline['2026-10-06']['booking_count']);
        $this->assertEquals(450.00, $timeline['2026-10-06']['revenue']);
    }

    /**
     * 6. Test: Accounting & Tax CSV export format, headers, UTF-8 BOM, and formula protection.
     */
    public function test_accounting_csv_export_format_and_security(): void
    {
        $this->insertMockBooking([
            'booking_code'    => 'TRV-TAX01',
            'customer_name'   => '=cmd|calc!A1', // Formula injection attempt
            'customer_email'  => 'taxpayer@example.com',
            'total_amount'    => 1150.00,
            'tax_amount'      => 150.00,
            'discount_amount' => 50.00,
            'paid_amount'     => 1150.00,
            'payment_status'  => 'paid',
            'payment_method'  => 'stripe',
            'transaction_id'  => 'ch_999999',
            'created_at'      => '2026-10-05 10:00:00',
        ], [
            'item_title'      => 'Cox\'s Bazar Safari',
            'item_type'       => 'tour',
            'unit_price'      => 525.00,
            'quantity'        => 2,
            'total_price'     => 1050.00,
        ]);

        $stream = fopen('php://memory', 'r+');
        $this->reportingService->exportAccountingCsv('2026-10-01 00:00:00', '2026-10-10 23:59:59', $stream);
        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        // UTF-8 BOM check
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);

        // Required Accounting Headers
        $this->assertStringContainsString('Booking Code', $csvContent);
        $this->assertStringContainsString('Tax Amount', $csvContent);
        $this->assertStringContainsString('Subtotal (Excl. Tax)', $csvContent);
        $this->assertStringContainsString('Discount Amount', $csvContent);
        $this->assertStringContainsString('Gross Total', $csvContent);
        $this->assertStringContainsString('Transaction ID', $csvContent);

        // Formula injection sanitized
        $this->assertStringContainsString("'=cmd|calc!A1", $csvContent);
        $this->assertStringContainsString('150.00', $csvContent);
        $this->assertStringContainsString('TRV-TAX01', $csvContent);
    }

    /**
     * 7. Test: Unauthorized user cannot export accounting reports.
     */
    public function test_unauthorized_user_cannot_export_or_view(): void
    {
        $nonAdmin = wp_set_current_user(99);
        $nonAdmin->allcaps['manage_tourivo_bookings'] = false;

        $caughtException = false;
        try {
            $this->reportingService->handleExportRequest();
        } catch (\RuntimeException $e) {
            $caughtException = true;
            $this->assertSame(403, $e->getCode());
        }

        $this->assertTrue($caughtException, 'Expected wp_die 403 on unauthorized export attempt.');
    }
}
