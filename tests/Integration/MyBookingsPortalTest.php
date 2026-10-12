<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Admin\SetupWizard;
use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PricingService;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Shortcodes\MyBookingsShortcode;
use Tourivo\Tests\TestCase;
use WP_User;

/**
 * Class MyBookingsPortalTest
 *
 * Tests for Customer Account / "My Bookings" Portal ([tourivo_my_bookings]):
 * 1. Guest visitor sees login prompt, registration link, and track booking fallback.
 * 2. Logged-in traveler sees their own bookings (matched by customer_id and customer_email) with strict isolation.
 * 3. Stats calculations (Upcoming, Completed, Cancelled, Total).
 * 4. Token-secured voucher and calendar action links.
 * 5. Cancellation AJAX authorization (cross-user isolation, self-cancel vs request mode).
 * 6. Booking details AJAX authorization and breakdown data.
 * 7. SetupWizard and Config settings integration.
 */
class MyBookingsPortalTest extends TestCase
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

        BookingLookupShortcode::register();
        if (class_exists(MyBookingsShortcode::class)) {
            MyBookingsShortcode::register();
        }
    }

    protected function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }

    /**
     * 1. Test: Guest visitor sees friendly login prompt and link to Track Booking.
     */
    public function test_guest_visitor_sees_login_prompt_and_lookup_link(): void
    {
        wp_set_current_user(0);

        $output = MyBookingsShortcode::render([]);

        $this->assertStringContainsString('tourivo-my-bookings-login-prompt', $output);
        $this->assertStringContainsString('Log in', $output);
        $this->assertStringContainsString('Track', $output);
    }

    /**
     * 2. Test: Logged-in traveler sees their own bookings matched by customer_id OR customer_email.
     */
    public function test_logged_in_traveler_sees_their_bookings_with_isolation(): void
    {
        $alice = new WP_User();
        $alice->ID = 101;
        $alice->user_email = 'alice@example.com';
        $alice->display_name = 'Alice Wonderland';
        $GLOBALS['tourivo_mock_users'][101] = $alice;

        $bob = new WP_User();
        $bob->ID = 102;
        $bob->user_email = 'bob@example.com';
        $bob->display_name = 'Bob Builder';
        $GLOBALS['tourivo_mock_users'][102] = $bob;

        $tourId = $this->createTour(120.0, 20);

        // Booking 1: Alice's booking with customer_id = 101
        $res1 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+5 days')),
            'adults'         => 2,
            'customer_id'    => 101,
            'customer_name'  => 'Alice Wonderland',
            'customer_email' => 'alice@example.com',
            'customer_phone' => '1234567890',
        ], true);
        $this->assertTrue($res1['success']);

        // Booking 2: Alice's earlier guest booking (customer_id = 0, but email = alice@example.com)
        $res2 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+12 days')),
            'adults'         => 1,
            'customer_id'    => 0,
            'customer_name'  => 'Alice W',
            'customer_email' => 'alice@example.com',
            'customer_phone' => '1234567890',
        ], true);
        $this->assertTrue($res2['success']);

        // Booking 3: Bob's booking
        $res3 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+3 days')),
            'adults'         => 1,
            'customer_id'    => 102,
            'customer_name'  => 'Bob Builder',
            'customer_email' => 'bob@example.com',
            'customer_phone' => '0987654321',
        ], true);
        $this->assertTrue($res3['success']);

        // Set current user to Alice
        wp_set_current_user(101);

        $output = MyBookingsShortcode::render([]);

        $this->assertStringContainsString($res1['booking_code'], $output, 'Alice must see her booking 1');
        $this->assertStringContainsString($res2['booking_code'], $output, 'Alice must see her guest-matched booking 2');
        $this->assertStringNotContainsString($res3['booking_code'], $output, "Alice must NEVER see Bob's booking 3");
    }

    /**
     * 3. Test: Stats calculations (Upcoming, Completed, Cancelled, Total).
     */
    public function test_my_bookings_stats_calculation(): void
    {
        $user = new WP_User();
        $user->ID = 201;
        $user->user_email = 'stats.user@example.com';
        $user->display_name = 'Stats User';
        $GLOBALS['tourivo_mock_users'][201] = $user;

        $tourId = $this->createTour(100.0, 30);

        // 1 Upcoming
        $b1 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+10 days')),
            'adults'         => 1,
            'customer_id'    => 201,
            'customer_name'  => 'Stats User',
            'customer_email' => 'stats.user@example.com',
        ], true);

        // 2 Completed
        $b2 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('-5 days')),
            'adults'         => 1,
            'customer_id'    => 201,
            'customer_name'  => 'Stats User',
            'customer_email' => 'stats.user@example.com',
            'booking_status' => 'completed',
        ], true);

        // 3 Cancelled
        $b3 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+20 days')),
            'adults'         => 1,
            'customer_id'    => 201,
            'customer_name'  => 'Stats User',
            'customer_email' => 'stats.user@example.com',
        ], true);
        $this->bookingService->changeStatus((int) $b3['booking_id'], 'cancelled');

        wp_set_current_user(201);

        $stats = MyBookingsShortcode::getUserBookingStats(201, 'stats.user@example.com');

        $this->assertEquals(3, $stats['total'], 'Total bookings count must be 3');
        $this->assertEquals(1, $stats['upcoming'], 'Upcoming bookings count must be 1');
        $this->assertEquals(1, $stats['completed'], 'Completed bookings count must be 1');
        $this->assertEquals(1, $stats['cancelled'], 'Cancelled bookings count must be 1');
    }

    /**
     * 4. Test: Token-secured voucher and calendar action links.
     */
    public function test_voucher_and_calendar_action_links(): void
    {
        $user = new WP_User();
        $user->ID = 301;
        $user->user_email = 'links.user@example.com';
        $user->display_name = 'Links User';
        $GLOBALS['tourivo_mock_users'][301] = $user;

        $tourId = $this->createTour(200.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+7 days')),
            'adults'         => 1,
            'customer_id'    => 301,
            'customer_name'  => 'Links User',
            'customer_email' => 'links.user@example.com',
        ], true);

        wp_set_current_user(301);

        $output = MyBookingsShortcode::render([]);

        $this->assertStringContainsString('action=tourivo_print_voucher', $output);
        $this->assertStringContainsString('action=tourivo_download_calendar', $output);
        $this->assertStringContainsString('code=' . $res['booking_code'], $output);
    }

    /**
     * 5. Test: AJAX cancellation authorization and cross-user isolation.
     */
    public function test_ajax_cancel_booking_authorization_and_isolation(): void
    {
        $alice = new WP_User();
        $alice->ID = 401;
        $alice->user_email = 'alice401@example.com';
        $alice->allcaps = [];
        $GLOBALS['tourivo_mock_users'][401] = $alice;

        $bob = new WP_User();
        $bob->ID = 402;
        $bob->user_email = 'bob402@example.com';
        $bob->allcaps = [];
        $GLOBALS['tourivo_mock_users'][402] = $bob;

        $tourId = $this->createTour(150.0, 10);

        // Bob's booking
        $bobBooking = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+10 days')),
            'adults'         => 1,
            'customer_id'    => 402,
            'customer_name'  => 'Bob',
            'customer_email' => 'bob402@example.com',
        ], true);

        // Alice attempts to cancel Bob's booking
        wp_set_current_user(401);

        $resUnauthorized = MyBookingsShortcode::processUserCancellation(
            (int) $bobBooking['booking_id'],
            'Malicious attempt'
        );

        $this->assertFalse($resUnauthorized['success']);
        $this->assertEquals(403, $resUnauthorized['code']);

        // Bob cancels his own booking
        wp_set_current_user(402);

        $resAuthorized = MyBookingsShortcode::processUserCancellation(
            (int) $bobBooking['booking_id'],
            'Changed travel plans'
        );

        $this->assertTrue($resAuthorized['success']);
    }

    /**
     * 6. Test: Booking details AJAX authorization and breakdown payload.
     */
    public function test_booking_details_authorization_and_breakdown(): void
    {
        $user = new WP_User();
        $user->ID = 501;
        $user->user_email = 'detail.user@example.com';
        $user->allcaps = [];
        $GLOBALS['tourivo_mock_users'][501] = $user;

        $stranger = new WP_User();
        $stranger->ID = 999;
        $stranger->user_email = 'stranger@example.com';
        $stranger->allcaps = [];
        $GLOBALS['tourivo_mock_users'][999] = $stranger;

        $tourId = $this->createTour(250.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+15 days')),
            'adults'         => 2,
            'customer_id'    => 501,
            'customer_name'  => 'Detail User',
            'customer_email' => 'detail.user@example.com',
            'customer_notes' => 'Vegetarian breakfast requested',
        ], true);

        // Other user attempts to fetch details
        wp_set_current_user(999);
        $unauthDetails = MyBookingsShortcode::getUserBookingDetails((int) $res['booking_id']);
        $this->assertFalse($unauthDetails['success']);
        $this->assertEquals(403, $unauthDetails['code']);

        // Owner fetches details
        wp_set_current_user(501);
        $authDetails = MyBookingsShortcode::getUserBookingDetails((int) $res['booking_id']);
        $this->assertTrue($authDetails['success']);
        $this->assertEquals($res['booking_code'], $authDetails['booking']['booking_code']);
        $this->assertNotEmpty($authDetails['items']);
        $this->assertStringContainsString('Vegetarian', (string) $authDetails['booking']['customer_notes']);
    }

    /**
     * 7. Test: SetupWizard and Config settings support my_bookings_page_id.
     */
    public function test_setup_wizard_and_config_support_my_bookings(): void
    {
        $defaults = Config::getDefaults();
        $this->assertArrayHasKey('my_bookings_page_id', $defaults);

        $_POST['nonce'] = wp_create_nonce('tourivo_wizard_nonce');
        $_POST['pages'] = json_encode(['my_bookings']);

        $GLOBALS['tourivo_last_ajax_response'] = null;
        SetupWizard::handleCreatePages();
        $response = $GLOBALS['tourivo_last_ajax_response']['body'] ?? null;

        $this->assertNotNull($response);
        $this->assertTrue(!empty($response['success']));
        $this->assertArrayHasKey('my_bookings', $response['data']['pages']);

        $settings = get_option('tourivo_settings');
        $this->assertNotEmpty($settings['my_bookings_page_id']);
    }
}
