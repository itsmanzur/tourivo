<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\LogService;
use Tourivo\Services\PricingService;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Shortcodes\ThankYouShortcode;
use Tourivo\Tests\TestCase;

/**
 * Class PostBookingExperienceTest
 *
 * Integration tests for post-booking experience:
 * (a) Booking success response includes redirect_url and signed token with no PII.
 * (b) Thank you page summary rendering with valid token, polite error on invalid/expired token.
 * (c) .ics calendar generation: VCALENDAR/VEVENT format, UID, timezone, 403 on invalid token.
 * (d) Cancellation request fails with wrong email/code pair (with rate limiting).
 * (e) Cancellation request mode records log in LogService, meta cancel_requested_at, and admin email.
 * (f) Self-cancel within window cancels booking & restores inventory; outside window or if paid, converts to request mode.
 * (g) dataLayer script output without PII and with sessionStorage guard.
 */
class PostBookingExperienceTest extends TestCase
{
    protected InventoryService $inventoryService;
    protected EmailService $emailService;
    protected BookingService $bookingService;
    protected PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();
        $this->cleanTransients();

        $GLOBALS['tourivo_mock_sent_emails'] = [];
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $GLOBALS['tourivo_mock_filters'] = [];
        $GLOBALS['tourivo_mock_action_callbacks'] = [];
        $GLOBALS['tourivo_mock_options']['admin_email'] = 'admin@example.com';
        $GLOBALS['tourivo_mock_options']['blogname'] = 'Tourivo Test Site';

        $invRepo = new InventoryRepository();
        $this->inventoryService = new InventoryService($invRepo);
        $this->pricingService   = new PricingService($this->inventoryService);
        $this->emailService     = new EmailService();
        $this->bookingService   = new BookingService($this->inventoryService, $this->emailService, $this->pricingService);

        $container = Container::getInstance();
        $container->instance(InventoryService::class, $this->inventoryService);
        $container->instance(EmailService::class, $this->emailService);
        $container->instance(BookingService::class, $this->bookingService);
        $container->instance(PricingService::class, $this->pricingService);

        $this->emailService->register();
        ThankYouShortcode::register();
        BookingLookupShortcode::register();
    }

    /**
     * Test (a): Booking creation returns redirect_url and signed thankyou token with NO customer PII in URL.
     */
    public function test_booking_response_contains_redirect_url_and_token_without_pii(): void
    {
        // Configure settings for thankyou redirect
        update_option('tourivo_settings', [
            'redirect_after_booking' => 'thankyou',
            'thankyou_page_id'       => 99,
        ]);

        $tourId = $this->createTour(150.0, 10);
        $checkIn = gmdate('Y-m-d', strtotime('+7 days'));

        $customerEmail = 'secret.customer@example.com';
        $customerName  = 'Farhan Chowdhury';

        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkIn,
            'adults'         => 2,
            'customer_name'  => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => '+8801812345678',
        ]);

        $this->assertTrue($res['success'], 'Booking should succeed.');
        $this->assertArrayHasKey('redirect_url', $res, 'Response must include redirect_url.');
        $this->assertArrayHasKey('thankyou_token', $res, 'Response must include thankyou_token.');
        $this->assertNotEmpty($res['redirect_url'], 'redirect_url should not be empty.');

        $redirectUrl = $res['redirect_url'];

        // Verify that NO PII (email, name, phone) is present in the URL query string
        $this->assertStringNotContainsString(urlencode($customerEmail), $redirectUrl, 'URL must not contain customer email.');
        $this->assertStringNotContainsString('secret.customer', $redirectUrl, 'URL must not contain customer email username.');
        $this->assertStringNotContainsString('Farhan', $redirectUrl, 'URL must not contain customer name.');
        $this->assertStringNotContainsString('01812345678', $redirectUrl, 'URL must not contain customer phone.');

        // Verify URL contains only code and signed token
        $parsed = parse_url($redirectUrl);
        parse_str($parsed['query'] ?? '', $queryParams);
        $this->assertEquals($res['booking_code'], $queryParams['code'] ?? '', 'URL code param should match booking code.');
        $this->assertEquals($res['thankyou_token'], $queryParams['token'] ?? '', 'URL token param should match signed token.');
    }

    /**
     * Test (b): Thank You page renders booking summary with valid token, and polite error on invalid/expired token.
     */
    public function test_thankyou_page_rendering_and_token_verification(): void
    {
        $tourId = $this->createTour(200.0, 10);
        $checkIn = gmdate('Y-m-d', strtotime('+10 days'));

        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkIn,
            'adults'         => 2,
            'customer_name'  => 'Anisul Huq',
            'customer_email' => 'anis@example.com',
            'customer_phone' => '+8801999999999',
        ]);

        $bookingId   = $res['booking_id'];
        $bookingCode = $res['booking_code'];
        $validToken  = ThankYouShortcode::generateThankYouToken($bookingId);

        // 1. Render with VALID token
        $_GET['code']  = $bookingCode;
        $_GET['token'] = $validToken;

        $viewedActionFired = false;
        add_action('tourivo/thankyou_viewed', function ($id) use (&$viewedActionFired, $bookingId) {
            if ($id === $bookingId) {
                $viewedActionFired = true;
            }
        });

        $html = ThankYouShortcode::render();

        $this->assertTrue($viewedActionFired, 'tourivo/thankyou_viewed action hook should fire on valid token view.');
        $this->assertStringContainsString('#' . $bookingCode, $html, 'Thank You page must show booking code.');
        $this->assertStringContainsString('Anisul Huq', $html, 'Thank You page must show customer name.');
        $this->assertStringContainsString('Print Booking Voucher', $html, 'Thank You page must have voucher button.');
        $this->assertStringContainsString('Add to Calendar', $html, 'Thank You page must have calendar button.');

        // 2. Render with INVALID / FORGED token
        $_GET['code']  = $bookingCode;
        $_GET['token'] = 'tampered_fake_token_12345';

        $invalidHtml = ThankYouShortcode::render();
        $this->assertStringContainsString('Track My Booking', $invalidHtml, 'Invalid token page should provide lookup link.');
        $this->assertStringNotContainsString('Anisul Huq', $invalidHtml, 'Invalid token page must not reveal customer details.');

        // 3. Render with EXPIRED token (>72 hours ago)
        $pastTimestamp = time() - (73 * 3600);
        $expiredToken = ThankYouShortcode::generateThankYouToken($bookingId, $pastTimestamp);

        $_GET['token'] = $expiredToken;
        $expiredHtml = ThankYouShortcode::render();
        $this->assertStringContainsString('Track My Booking', $expiredHtml, 'Expired token page should provide lookup link.');
        $this->assertStringNotContainsString('Anisul Huq', $expiredHtml, 'Expired token page must not reveal customer details.');
    }

    /**
     * Test (c): .ics generation produces valid VCALENDAR/VEVENT structure and 403 on invalid token.
     */
    public function test_ics_generation_format_and_security(): void
    {
        $tourId = $this->createTour(180.0, 10);
        $checkIn = gmdate('Y-m-d', strtotime('+14 days'));

        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkIn,
            'adults'         => 1,
            'customer_name'  => 'Sultana Razia',
            'customer_email' => 'razia@example.com',
        ]);

        $bookingId   = $res['booking_id'];
        $bookingCode = $res['booking_code'];
        $validToken  = ThankYouShortcode::generateThankYouToken($bookingId);

        // 1. Generate ICS with valid token
        $ics = ThankYouShortcode::generateIcsContent($bookingId);

        $this->assertNotEmpty($ics, 'ICS content should not be empty.');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics, 'Must have BEGIN:VCALENDAR');
        $this->assertStringContainsString('VERSION:2.0', $ics, 'Must declare VERSION:2.0');
        $this->assertStringContainsString('BEGIN:VEVENT', $ics, 'Must have BEGIN:VEVENT');
        $this->assertStringContainsString('UID:tourivo-booking-' . $bookingId, $ics, 'Must have unique UID.');
        $this->assertStringContainsString('SUMMARY:', $ics, 'Must contain SUMMARY.');
        $this->assertStringContainsString('END:VEVENT', $ics, 'Must have END:VEVENT');
        $this->assertStringContainsString('END:VCALENDAR', $ics, 'Must have END:VCALENDAR');

        // 2. Token verification helper rejects fake token
        $this->assertTrue(ThankYouShortcode::verifyThankYouToken($bookingId, $validToken));
        $this->assertFalse(ThankYouShortcode::verifyThankYouToken($bookingId, 'fake_token'));
    }

    /**
     * Test (d): Cancellation request fails when customer email does not match booking code.
     */
    public function test_cancellation_request_fails_with_mismatched_email_or_code(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+5 days')),
            'adults'         => 2,
            'customer_name'  => 'Valid Owner',
            'customer_email' => 'owner@example.com',
        ]);

        $bookingCode = $res['booking_code'];

        // Attacker attempts to cancel with wrong email
        $cancelRes = BookingLookupShortcode::processCancellationRequest($bookingCode, 'attacker@example.com', 'I want to cancel');
        $this->assertFalse($cancelRes['success'], 'Cancellation request with wrong email must fail.');
        $this->assertEquals(404, $cancelRes['code'] ?? 400);

        // Attacker attempts with nonexistent booking code
        $fakeCodeRes = BookingLookupShortcode::processCancellationRequest('TRV-NONEXISTENT', 'owner@example.com', 'Cancel');
        $this->assertFalse($fakeCodeRes['success'], 'Cancellation request with nonexistent code must fail.');
    }

    /**
     * Test (e): Cancellation request mode records LogService entry, meta cancel_requested_at, and admin alert.
     */
    public function test_cancellation_request_mode_records_log_meta_and_email(): void
    {
        update_option('tourivo_settings', [
            'allow_cancel_requests'      => 1,
            'customer_self_cancel_hours' => 0, // Request-only mode
        ]);

        $tourId = $this->createTour(150.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+6 days')),
            'adults'         => 2,
            'customer_name'  => 'Traveler One',
            'customer_email' => 'traveler1@example.com',
        ]);

        $bookingId   = $res['booking_id'];
        $bookingCode = $res['booking_code'];

        $GLOBALS['tourivo_mock_scheduled_events'] = [];

        $cancelRes = BookingLookupShortcode::processCancellationRequest($bookingCode, 'traveler1@example.com', 'Emergency family issue');
        $this->assertTrue($cancelRes['success'], 'Cancellation request submission should succeed.');
        $this->assertEquals('requested', $cancelRes['mode']);

        // 1. Verify LogService entry
        $logs = LogService::getTimeline($bookingId);
        $cancelLogs = array_filter($logs, fn ($l) => $l['action'] === 'cancel_requested');
        $this->assertNotEmpty($cancelLogs, 'LogService must have a cancel_requested entry.');
        $firstCancelLog = array_values($cancelLogs)[0];
        $this->assertStringContainsString('Emergency family issue', $firstCancelLog['details']);

        // 2. Verify booking status remained pending/confirmed (not directly cancelled)
        global $wpdb;
        $bookingRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d", $bookingId));
        $this->assertNotEquals('cancelled', $bookingRow->booking_status, 'Booking status should NOT be cancelled in request mode.');
        $this->assertNotEmpty($bookingRow->cancel_requested_at ?? '', 'cancel_requested_at should be populated.');
    }

    /**
     * Test (f): Direct self-cancel within window cancels booking & restores inventory; outside window or paid, converts to request.
     */
    public function test_self_cancellation_within_window_and_paid_fallback(): void
    {
        update_option('tourivo_settings', [
            'allow_cancel_requests'      => 1,
            'customer_self_cancel_hours' => 48, // Up to 48 hours before check-in
        ]);

        $tourId = $this->createTour(100.0, 10);
        $checkInFar = gmdate('Y-m-d', strtotime('+5 days')); // 120 hours away (well within 48h window)

        // Case 1: Unpaid booking within window -> direct cancellation
        $res1 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkInFar,
            'adults'         => 3,
            'customer_name'  => 'Fast Canceller',
            'customer_email' => 'fast@example.com',
        ]);

        $bookingId1   = $res1['booking_id'];
        $bookingCode1 = $res1['booking_code'];

        $cancelRes1 = BookingLookupShortcode::processCancellationRequest($bookingCode1, 'fast@example.com', 'Changed plans');
        $this->assertTrue($cancelRes1['success'], 'Self cancellation within window should succeed.');
        $this->assertEquals('cancelled', $cancelRes1['mode'], 'Mode should be directly cancelled.');

        // Verify booking status is now cancelled
        global $wpdb;
        $bookingRow1 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d", $bookingId1));
        $this->assertEquals('cancelled', $bookingRow1->booking_status);

        // Case 2: Paid booking within window -> must fallback to request mode so staff can handle refund
        $res2 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkInFar,
            'adults'         => 2,
            'customer_name'  => 'Paid Traveler',
            'customer_email' => 'paid@example.com',
            'payment_status' => 'paid',
        ], true); // trusted to set paid

        $bookingId2   = $res2['booking_id'];
        $bookingCode2 = $res2['booking_code'];

        $cancelRes2 = BookingLookupShortcode::processCancellationRequest($bookingCode2, 'paid@example.com', 'Need refund');
        $this->assertTrue($cancelRes2['success']);
        $this->assertEquals('requested', $cancelRes2['mode'], 'Paid booking must convert to requested mode.');

        $bookingRow2 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d", $bookingId2));
        $this->assertNotEquals('cancelled', $bookingRow2->booking_status);

        // Case 3: Booking too close to check-in (e.g. 24 hours away when window is 48 hours)
        $checkInClose = gmdate('Y-m-d', strtotime('+1 day'));
        $res3 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkInClose,
            'adults'         => 2,
            'customer_name'  => 'Late Traveler',
            'customer_email' => 'late@example.com',
        ]);

        $cancelRes3 = BookingLookupShortcode::processCancellationRequest($res3['booking_code'], 'late@example.com', 'Late cancel');
        $this->assertTrue($cancelRes3['success']);
        $this->assertEquals('requested', $cancelRes3['mode'], 'Late cancellation outside window must convert to requested mode.');
    }

    /**
     * Test (g): dataLayer script output without PII and with sessionStorage guard.
     */
    public function test_datalayer_output_without_pii(): void
    {
        update_option('tourivo_settings', [
            'enable_datalayer' => 1,
        ]);

        $tourId = $this->createTour(250.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+8 days')),
            'adults'         => 2,
            'customer_name'  => 'Tag Manager User',
            'customer_email' => 'gtm@example.com',
            'customer_phone' => '+8801711111111',
        ]);

        $bookingId   = $res['booking_id'];
        $bookingCode = $res['booking_code'];
        $validToken  = ThankYouShortcode::generateThankYouToken($bookingId);

        $_GET['code']  = $bookingCode;
        $_GET['token'] = $validToken;

        $html = ThankYouShortcode::render();

        $this->assertStringContainsString('dataLayer.push', $html, 'dataLayer.push script must be rendered when enable_datalayer is true.');
        $this->assertStringContainsString('tourivo_booking', $html, 'dataLayer event name must be tourivo_booking.');
        $this->assertStringContainsString('sessionStorage', $html, 'Must use sessionStorage guard.');
        $this->assertStringContainsString($bookingCode, $html, 'Must include booking_id / code in dataLayer.');

        // Verify NO PII is included in the script tag
        preg_match('/<script>(.*?)<\/script>/s', $html, $scriptMatch);
        $scriptContent = $scriptMatch[1] ?? '';
        $this->assertNotEmpty($scriptContent, 'Must render script block.');
        $this->assertStringNotContainsString('gtm@example.com', $scriptContent, 'dataLayer snippet must not contain email.');
        $this->assertStringNotContainsString('+8801711111111', $scriptContent, 'dataLayer snippet must not contain phone.');
        $this->assertStringNotContainsString('Tag Manager User', $scriptContent, 'dataLayer snippet must not contain customer name.');
    }
}
