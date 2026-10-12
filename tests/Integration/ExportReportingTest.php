<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Admin\BookingsTable;
use Tourivo\Common\Container;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\ExportService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PricingService;
use Tourivo\Tests\TestCase;
use WP_User;

/**
 * Class ExportReportingTest
 *
 * Tests for Booking Export and Reporting Suite:
 * 1. Unauthorized requests (missing permissions or non-logged in) are rejected.
 * 2. Invalid or missing CSRF nonces are rejected.
 * 3. Filter by booking status (all, confirmed, pending, cancelled, cancel_requested).
 * 4. Filter by payment status (all, paid, pending, etc.).
 * 5. Filter by booking date range (created_at).
 * 6. Filter by travel / check-in date range (check_in).
 * 7. Filter by specific tour/room ID or item type.
 * 8. Protection against CSV / Excel Formula Injection (=, +, -, @, \t, \r).
 * 9. UTF-8 BOM emission for international character & Excel support.
 * 10. Financial Summary Report export mode.
 * 11. Live preview AJAX handler returning matched counts and totals.
 */
class ExportReportingTest extends TestCase
{
    protected InventoryService $inventoryService;
    protected EmailService $emailService;
    protected BookingService $bookingService;
    protected PricingService $pricingService;
    protected ExportService $exportService;

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
        $this->exportService    = new ExportService();

        $container = Container::getInstance();
        $container->instance(InventoryService::class, $this->inventoryService);
        $container->instance(EmailService::class, $this->emailService);
        $container->instance(BookingService::class, $this->bookingService);
        $container->instance(PricingService::class, $this->pricingService);
        $container->instance(ExportService::class, $this->exportService);
    }

    protected function tearDown(): void
    {
        wp_set_current_user(0);
        $_POST = [];
        $_GET  = [];
        parent::tearDown();
    }

    /**
     * Helper to insert a mock booking and associated line item.
     *
     * @param array<string, mixed> $bookingOverrides
     * @param array<string, mixed> $itemOverrides
     * @return int
     */
    protected function insertMockBooking(array $bookingOverrides = [], array $itemOverrides = []): int
    {
        global $wpdb;

        $bookingDefaults = [
            'booking_code'    => 'TRV-' . strtoupper(bin2hex(random_bytes(4))),
            'customer_id'     => 1,
            'customer_name'   => 'John Traveler',
            'customer_email'  => 'john@example.com',
            'customer_phone'  => '+1234567890',
            'total_amount'    => 250.00,
            'tax_amount'      => 25.00,
            'discount_amount' => 0.00,
            'paid_amount'     => 250.00,
            'due_amount'      => 0.00,
            'currency'        => 'USD',
            'payment_method'  => 'offline',
            'payment_status'  => 'paid',
            'booking_status'  => 'confirmed',
            'customer_notes'  => 'Window seat please',
            'created_at'      => '2026-10-10 12:00:00',
        ];

        $bData = array_merge($bookingDefaults, $bookingOverrides);
        $wpdb->insert($wpdb->prefix . 'tourivo_bookings', $bData);
        $bookingId = (int) $wpdb->insert_id;

        $itemDefaults = [
            'booking_id'     => $bookingId,
            'item_id'        => 101,
            'item_type'      => 'tour',
            'item_title'     => 'Bali Paradise Tour',
            'check_in'       => '2026-11-01 09:00:00',
            'check_out'      => '2026-11-05 18:00:00',
            'adults_count'   => 2,
            'children_count' => 0,
            'infants_count'  => 0,
            'quantity'       => 2,
            'unit_price'     => 125.00,
            'total_price'    => 250.00,
        ];

        $iData = array_merge($itemDefaults, $itemOverrides);
        $iData['booking_id'] = $bookingId;
        $wpdb->insert($wpdb->prefix . 'tourivo_booking_items', $iData);

        return $bookingId;
    }

    /**
     * 1. Test: Unauthorized users without 'manage_tourivo_bookings' are rejected.
     */
    public function test_export_unauthorized_user_is_rejected(): void
    {
        wp_set_current_user(0);
        $_GET['_wpnonce'] = wp_create_nonce('tourivo_export_csv_action');

        $rejected = false;
        try {
            ExportService::handleExportRequest();
        } catch (\RuntimeException $e) {
            $rejected = ($e->getCode() === 403 || str_contains($e->getMessage(), 'Unauthorized'));
        }

        $this->assertTrue($rejected, 'Non-authenticated users must be rejected with 403 on export.');
    }

    /**
     * 2. Test: Invalid nonce is rejected.
     */
    public function test_export_invalid_nonce_is_rejected(): void
    {
        $adminUser = wp_set_current_user(1);
        $adminUser->allcaps['manage_tourivo_bookings'] = true;

        $_GET['_wpnonce'] = 'invalid_tampered_nonce';

        $rejected = false;
        try {
            ExportService::handleExportRequest();
        } catch (\RuntimeException $e) {
            $rejected = ($e->getCode() === 403 || str_contains($e->getMessage(), 'nonce'));
        }

        $this->assertTrue($rejected, 'Requests with invalid CSRF nonce must be rejected.');
    }

    /**
     * 3. Test: Filter by booking status.
     */
    public function test_export_filters_by_booking_status(): void
    {
        $idConfirmed = $this->insertMockBooking(['booking_status' => 'confirmed', 'booking_code' => 'CONF111']);
        $idPending   = $this->insertMockBooking(['booking_status' => 'pending', 'booking_code' => 'PEND222']);
        $idCancelled = $this->insertMockBooking(['booking_status' => 'cancelled', 'booking_code' => 'CANC333']);

        $confirmedResults = $this->exportService->queryBookings(['status' => 'confirmed']);
        $codes = array_map(static fn ($r) => $r['booking_code'] ?? '', $confirmedResults);

        $this->assertContains('CONF111', $codes);
        $this->assertNotContains('PEND222', $codes);
        $this->assertNotContains('CANC333', $codes);
    }

    /**
     * 4. Test: Filter by payment status.
     */
    public function test_export_filters_by_payment_status(): void
    {
        $this->insertMockBooking(['payment_status' => 'paid', 'booking_code' => 'PAID111']);
        $this->insertMockBooking(['payment_status' => 'pending', 'booking_code' => 'UNPAID222']);

        $paidResults = $this->exportService->queryBookings(['payment_status' => 'paid']);
        $codes = array_map(static fn ($r) => $r['booking_code'] ?? '', $paidResults);

        $this->assertContains('PAID111', $codes);
        $this->assertNotContains('UNPAID222', $codes);
    }

    /**
     * 5. Test: Filter by booking creation date range.
     */
    public function test_export_filters_by_booking_created_date_range(): void
    {
        $this->insertMockBooking(['created_at' => '2026-10-01 10:00:00', 'booking_code' => 'OCT01']);
        $this->insertMockBooking(['created_at' => '2026-10-12 14:00:00', 'booking_code' => 'OCT12']);
        $this->insertMockBooking(['created_at' => '2026-10-25 18:00:00', 'booking_code' => 'OCT25']);

        $results = $this->exportService->queryBookings([
            'date_type'  => 'booking_date',
            'start_date' => '2026-10-05',
            'end_date'   => '2026-10-15',
        ]);
        $codes = array_map(static fn ($r) => $r['booking_code'] ?? '', $results);

        $this->assertNotContains('OCT01', $codes);
        $this->assertContains('OCT12', $codes);
        $this->assertNotContains('OCT25', $codes);
    }

    /**
     * 6. Test: Filter by travel / check-in date range.
     */
    public function test_export_filters_by_travel_checkin_date_range(): void
    {
        $this->insertMockBooking(
            ['booking_code' => 'NOV_TRIP'],
            ['check_in' => '2026-11-10 09:00:00']
        );
        $this->insertMockBooking(
            ['booking_code' => 'DEC_TRIP'],
            ['check_in' => '2026-12-20 09:00:00']
        );

        $results = $this->exportService->queryBookings([
            'date_type'  => 'travel_date',
            'start_date' => '2026-11-01',
            'end_date'   => '2026-11-30',
        ]);
        $codes = array_map(static fn ($r) => $r['booking_code'] ?? '', $results);

        $this->assertContains('NOV_TRIP', $codes);
        $this->assertNotContains('DEC_TRIP', $codes);
    }

    /**
     * 7. Test: Filter by item ID and item type.
     */
    public function test_export_filters_by_item_id_and_type(): void
    {
        $this->insertMockBooking(
            ['booking_code' => 'BALI_TOUR'],
            ['item_id' => 101, 'item_type' => 'tour', 'item_title' => 'Bali Tour']
        );
        $this->insertMockBooking(
            ['booking_code' => 'MALDIVES_ROOM'],
            ['item_id' => 202, 'item_type' => 'room', 'item_title' => 'Maldives Villa']
        );

        // Filter by item_id
        $tourResults = $this->exportService->queryBookings(['item_id' => 101]);
        $tourCodes = array_map(static fn ($r) => $r['booking_code'] ?? '', $tourResults);
        $this->assertContains('BALI_TOUR', $tourCodes);
        $this->assertNotContains('MALDIVES_ROOM', $tourCodes);

        // Filter by item_type
        $roomResults = $this->exportService->queryBookings(['item_type' => 'room']);
        $roomCodes = array_map(static fn ($r) => $r['booking_code'] ?? '', $roomResults);
        $this->assertContains('MALDIVES_ROOM', $roomCodes);
        $this->assertNotContains('BALI_TOUR', $roomCodes);
    }

    /**
     * 8. Test: Protection against CSV / Excel Formula Injection (=, +, -, @, \t, \r).
     */
    public function test_csv_generation_sanitizes_formula_injection(): void
    {
        $this->insertMockBooking([
            'booking_code'   => '+1234567',
            'customer_name'  => '=cmd|\' /C calc\'!A0',
            'customer_notes' => '@SUM(A1:A99)',
        ]);

        $stream = fopen('php://memory', 'r+');
        $this->exportService->generateCsv([], 'bookings', $stream);
        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        // Cells starting with dangerous prefixes must be escaped with single quote '
        $this->assertStringContainsString("'+1234567", $csvContent);
        $this->assertStringContainsString("'=cmd|", $csvContent);
        $this->assertStringContainsString("'@SUM", $csvContent);
    }

    /**
     * 9. Test: UTF-8 BOM emission and standard column headers.
     */
    public function test_csv_generation_includes_utf8_bom_and_headers(): void
    {
        $this->insertMockBooking([
            'customer_name' => 'সাকিব আহমেদ', // Bengali Unicode test
            'booking_code'  => 'BNG999',
        ]);

        $stream = fopen('php://memory', 'r+');
        $this->exportService->generateCsv([], 'bookings', $stream);
        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        // Verify UTF-8 BOM is present at the very beginning
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent, 'CSV must start with UTF-8 BOM for Excel support.');
        $this->assertStringContainsString('সাকিব আহমেদ', $csvContent);
        $this->assertStringContainsString('Booking Code', $csvContent);
        $this->assertStringContainsString('Trip / Stay Title', $csvContent);
        $this->assertStringContainsString('Total Amount', $csvContent);
    }

    /**
     * 10. Test: Financial Summary Report mode.
     */
    public function test_export_financial_summary_mode(): void
    {
        $this->insertMockBooking(['total_amount' => 500.00, 'tax_amount' => 50.00, 'discount_amount' => 20.00, 'booking_status' => 'confirmed']);
        $this->insertMockBooking(['total_amount' => 300.00, 'tax_amount' => 30.00, 'discount_amount' => 0.00, 'booking_status' => 'confirmed']);
        $this->insertMockBooking(['total_amount' => 200.00, 'tax_amount' => 20.00, 'discount_amount' => 0.00, 'booking_status' => 'cancelled']);

        $summary = $this->exportService->getExportSummary(['status' => 'all']);

        $this->assertSame(3, $summary['total_bookings']);
        $this->assertSame(2, $summary['confirmed_bookings']);
        $this->assertEquals(800.00, $summary['confirmed_revenue']); // 500 + 300 (excluding cancelled)

        // Generate financial summary CSV
        $stream = fopen('php://memory', 'r+');
        $this->exportService->generateCsv([], 'financial_summary', $stream);
        rewind($stream);
        $summaryCsv = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringContainsString('Financial Summary Report', $summaryCsv);
        $this->assertStringContainsString('Total Revenue', $summaryCsv);
        $this->assertStringContainsString('800.00', $summaryCsv);
    }

    /**
     * 11. Test: Live preview AJAX endpoint returns count and amounts.
     */
    public function test_export_preview_ajax(): void
    {
        $adminUser = wp_set_current_user(1);
        $adminUser->allcaps['manage_tourivo_bookings'] = true;

        $this->insertMockBooking(['booking_status' => 'confirmed', 'total_amount' => 400.00]);
        $this->insertMockBooking(['booking_status' => 'pending', 'total_amount' => 150.00]);

        $_GET['nonce']  = wp_create_nonce('tourivo_admin_nonce');
        $_GET['status'] = 'confirmed';

        $this->exportService->handlePreviewAjax();

        $response = $GLOBALS['tourivo_last_ajax_response'] ?? null;
        $this->assertNotNull($response);
        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertSame(1, $response['body']['data']['count']);
        $this->assertEquals(400.00, (float) $response['body']['data']['total_amount']);
    }
}
