<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Admin\AdminDashboard;
use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\InventoryService;
use Tourivo\Services\LogService;
use Tourivo\Services\PricingService;
use Tourivo\Tests\TestCase;

/**
 * Class EmailSystemTest
 *
 * Comprehensive integration tests for Tourivo email notification engine:
 * - Async dispatching & non-blocking booking request cycle
 * - Hook-based trigger separation (no direct email calls from createBooking)
 * - State transition emails (received, confirmed, cancelled)
 * - Daily trip reminders cron with date matching & deduplication
 * - Email templates, responsive styling, AltBody plain-text generation
 * - Header injection sanitization
 * - Failure handling & retry scheduling
 * - Pro / 3rd party filter compatibility (tourivo/send_email)
 * - Deduplication / idempotency key checks
 */
class EmailSystemTest extends TestCase
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

        // Register email service lifecycle hooks
        $this->emailService->register();
    }

    /**
     * Test (a): Pending booking queues customer_booking_received + admin_new_booking.
     */
    public function test_booking_created_pending_queues_received_and_admin_new_booking(): void
    {
        $tourId = $this->createTour(120.0, 10);
        $checkIn = gmdate('Y-m-d', strtotime('+5 days'));

        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkIn,
            'adults'         => 2,
            'customer_name'  => 'Rahim Ahmed',
            'customer_email' => 'rahim@example.com',
            'customer_phone' => '+8801700000000',
        ]);

        $this->assertTrue($res['success'], 'Booking creation should succeed.');
        $bookingId = $res['booking_id'];

        // 1. Verify that wp_mail was NOT called synchronously during createBooking
        $this->assertEmpty($GLOBALS['tourivo_mock_sent_emails'], 'wp_mail should not be called synchronously.');

        // 2. Verify events queued in async / cron list
        $queuedHooks = array_column($GLOBALS['tourivo_mock_scheduled_events'], 'hook');
        $this->assertNotEmpty($queuedHooks, 'Async events should be scheduled.');

        // Filter events for tourivo_process_email_delivery
        $emailEvents = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => $e['hook'] === 'tourivo_process_email_delivery');
        $this->assertCount(2, $emailEvents, 'Two email deliveries should be queued: admin_new_booking and customer_booking_received.');

        $emailTypesQueued = array_map(fn ($e) => $e['args'][0], $emailEvents);
        $this->assertTrue(in_array('admin_new_booking', $emailTypesQueued, true), 'admin_new_booking should be queued.');
        $this->assertTrue(in_array('customer_booking_received', $emailTypesQueued, true), 'customer_booking_received should be queued.');

        // 3. Dispatch the queued events
        foreach ($emailEvents as $ev) {
            $this->emailService->dispatch($ev['args'][0], $ev['args'][1], $ev['args'][2], $ev['args'][3] ?? 1);
        }

        $this->assertCount(2, $GLOBALS['tourivo_mock_sent_emails'], 'Two emails should now be sent.');

        // Find customer received email
        $customerMail = null;
        foreach ($GLOBALS['tourivo_mock_sent_emails'] as $m) {
            if ($m['to'] === 'rahim@example.com') {
                $customerMail = $m;
                break;
            }
        }

        $this->assertNotNull($customerMail, 'Customer email must be present.');
        $this->assertStringContainsString('Rahim Ahmed', $customerMail['message'], 'Customer email body should contain customer name.');
        $this->assertStringContainsString('Booking Request Received', $customerMail['message'], 'Customer email body should contain received heading.');
        $this->assertNotEmpty($customerMail['alt_body'], 'Plain text AltBody should be generated.');
    }

    /**
     * Test (b): Confirming booking queues confirmed email.
     */
    public function test_booking_status_change_confirmed_queues_confirmed_mail(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $checkIn = gmdate('Y-m-d', strtotime('+4 days'));

        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkIn,
            'adults'         => 1,
            'customer_name'  => 'Sara Connor',
            'customer_email' => 'sara@example.com',
        ]);
        $bookingId = $res['booking_id'];

        // Reset scheduled events
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $GLOBALS['tourivo_mock_sent_emails'] = [];

        // Change status to confirmed
        $changeRes = $this->bookingService->changeStatus($bookingId, 'confirmed');
        $this->assertTrue($changeRes['success']);

        $emailEvents = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => $e['hook'] === 'tourivo_process_email_delivery');
        $this->assertCount(1, $emailEvents, 'customer_booking_confirmed should be queued on confirmation.');

        $this->assertEquals('customer_booking_confirmed', $emailEvents[array_key_first($emailEvents)]['args'][0]);
        $this->assertEquals('sara@example.com', $emailEvents[array_key_first($emailEvents)]['args'][1]);

        // Dispatch worker
        $ev = reset($emailEvents);
        $this->emailService->dispatch($ev['args'][0], $ev['args'][1], $ev['args'][2], $ev['args'][3] ?? 1);

        $this->assertCount(1, $GLOBALS['tourivo_mock_sent_emails']);
        $sentMail = $GLOBALS['tourivo_mock_sent_emails'][0];
        $this->assertStringContainsString('Booking Confirmed', $sentMail['message']);
        $this->assertStringContainsString('admin-post.php?action=tourivo_print_voucher', $sentMail['message'], 'Confirmation mail should contain voucher link.');
    }

    /**
     * Test (c): Deduplication prevents duplicate emails for the same transition.
     */
    public function test_deduplication_prevents_duplicate_emails_for_same_transition(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+6 days')),
            'customer_name'  => 'John Smith',
            'customer_email' => 'john.dedup@example.com',
        ]);
        $bookingId = $res['booking_id'];

        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $this->bookingService->changeStatus($bookingId, 'confirmed');

        $emailEvents1 = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => $e['hook'] === 'tourivo_process_email_delivery');
        $this->assertCount(1, $emailEvents1);

        // Fire status change hook again for same status transition
        $this->emailService->onBookingStatusChanged($bookingId, 'pending', 'confirmed');

        $emailEvents2 = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => $e['hook'] === 'tourivo_process_email_delivery');
        $this->assertCount(1, $emailEvents2, 'Duplicate status transition should not enqueue duplicate email.');
    }

    /**
     * Test (d): Booking status change to cancelled queues customer & admin cancel emails.
     */
    public function test_booking_status_change_cancelled_queues_cancelled_mails(): void
    {
        $tourId = $this->createTour(150.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+7 days')),
            'customer_name'  => 'Elena Gilbert',
            'customer_email' => 'elena@example.com',
        ]);
        $bookingId = $res['booking_id'];

        $GLOBALS['tourivo_mock_scheduled_events'] = [];

        $this->bookingService->changeStatus($bookingId, 'cancelled');

        $emailEvents = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => $e['hook'] === 'tourivo_process_email_delivery');
        $this->assertCount(2, $emailEvents, 'customer_booking_cancelled and admin_booking_cancelled should be queued.');

        $types = array_map(fn ($e) => $e['args'][0], $emailEvents);
        $this->assertTrue(in_array('customer_booking_cancelled', $types, true));
        $this->assertTrue(in_array('admin_booking_cancelled', $types, true));
    }

    /**
     * Test (e): Disabled email type in settings is not sent.
     */
    public function test_disabled_email_type_is_not_sent(): void
    {
        update_option('tourivo_settings', [
            'email_customer_booking_cancelled_enabled' => 0,
        ]);

        $tourId = $this->createTour(150.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+8 days')),
            'customer_name'  => 'Stefan',
            'customer_email' => 'stefan@example.com',
        ]);
        $bookingId = $res['booking_id'];

        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $this->bookingService->changeStatus($bookingId, 'cancelled');

        $emailEvents = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => $e['hook'] === 'tourivo_process_email_delivery');
        $types = array_map(fn ($e) => $e['args'][0], $emailEvents);

        $this->assertFalse(in_array('customer_booking_cancelled', $types, true), 'Disabled email type should not be enqueued.');
        $this->assertTrue(in_array('admin_booking_cancelled', $types, true), 'Enabled email type should still be enqueued.');
    }

    /**
     * Test (f): Header injection sanitization strips CRLF from headers.
     */
    public function test_header_injection_sanitization(): void
    {
        $maliciousName = "John Doe\r\nBcc: hacker@example.com\r\nSubject: Injected";
        $sanitized = $this->emailService->sanitizeHeader($maliciousName);

        $this->assertStringNotContainsString("\r", $sanitized, 'CR should be removed.');
        $this->assertStringNotContainsString("\n", $sanitized, 'LF should be removed.');
        $this->assertEquals("John Doe Bcc: hacker@example.com Subject: Injected", $sanitized);
    }

    /**
     * Test (g): wp_mail failure logs to LogService and schedules retry with delay.
     */
    public function test_wp_mail_failure_logs_and_schedules_retry(): void
    {
        // Intercept pre_wp_mail filter to simulate mail failure
        add_filter('pre_wp_mail', fn () => false);

        $payload = [
            'id'             => 123,
            'booking_code'   => 'TRV-FAIL-01',
            'customer_name'  => 'Fail Test',
            'customer_email' => 'fail@example.com',
            'total_amount'   => '$100.00',
        ];

        $GLOBALS['tourivo_mock_scheduled_events'] = [];

        $dispatched = $this->emailService->dispatch('customer_booking_received', 'fail@example.com', $payload, 1);
        $this->assertFalse($dispatched, 'Dispatch should return false on mail failure.');

        // Verify log entry
        $logs = LogService::getTimeline(123);
        $this->assertNotEmpty($logs, 'LogService should have an entry for failure.');
        $this->assertEquals('email_failed', $logs[0]['action']);

        // Verify retry is scheduled for attempt 2 (delay 120s)
        $retryEvents = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => $e['hook'] === 'tourivo_process_email_delivery');
        $this->assertNotEmpty($retryEvents, 'Retry should be scheduled.');
        $retry = reset($retryEvents);
        $this->assertEquals(2, $retry['args'][3], 'Next attempt should be 2.');
    }

    /**
     * Test (h): Trip reminder cron sends reminder once on exact match date.
     */
    public function test_trip_reminder_cron_sends_once_on_correct_date(): void
    {
        update_option('tourivo_settings', [
            'reminder_days_before' => 2,
        ]);

        $tourId = $this->createTour(200.0, 10);
        $targetCheckIn = wp_date('Y-m-d', strtotime('+2 days'));

        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $targetCheckIn,
            'customer_name'  => 'Reminder Traveler',
            'customer_email' => 'reminder@example.com',
        ]);
        $bookingId = $res['booking_id'];
        $this->bookingService->changeStatus($bookingId, 'confirmed');

        $GLOBALS['tourivo_mock_scheduled_events'] = [];

        // Run daily reminders cron
        $this->emailService->processDailyReminders();

        $reminderEvents = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => ($e['args'][0] ?? '') === 'customer_trip_reminder');
        $this->assertCount(1, $reminderEvents, 'Reminder should be enqueued for confirmed booking in 2 days.');

        // Run cron again -> deduplication should prevent second enqueue
        $this->emailService->processDailyReminders();
        $reminderEventsAfter = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => ($e['args'][0] ?? '') === 'customer_trip_reminder');
        $this->assertCount(1, $reminderEventsAfter, 'Reminder should not be enqueued a second time.');
    }

    /**
     * Test (i): Pro filter tourivo/send_email can suppress email.
     */
    public function test_pro_filter_tourivo_send_email_can_suppress_email(): void
    {
        add_filter('tourivo/send_email', function ($send, $emailType, $data) {
            if ($emailType === 'customer_booking_received') {
                return false; // Suppress received email
            }
            return $send;
        }, 10, 3);

        $tourId = $this->createTour(100.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+3 days')),
            'customer_name'  => 'Filtered Customer',
            'customer_email' => 'filtered@example.com',
        ]);

        $emailEvents = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => $e['hook'] === 'tourivo_process_email_delivery');
        $types = array_map(fn ($e) => $e['args'][0], $emailEvents);

        $this->assertFalse(in_array('customer_booking_received', $types, true), 'customer_booking_received should be suppressed by filter.');
        $this->assertTrue(in_array('admin_new_booking', $types, true), 'admin_new_booking should still be queued.');
    }

    /**
     * Test (j): Admin manual booking without send_customer_email suppresses customer email.
     */
    public function test_admin_manual_booking_send_customer_email_flag(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $res = $this->bookingService->createBooking([
            'item_id'             => $tourId,
            'check_in'            => gmdate('Y-m-d', strtotime('+3 days')),
            'customer_name'       => 'Manual Customer',
            'customer_email'      => 'manual@example.com',
            'send_customer_email' => false,
        ], true);

        $emailEvents = array_filter($GLOBALS['tourivo_mock_scheduled_events'], fn ($e) => $e['hook'] === 'tourivo_process_email_delivery');
        $types = array_map(fn ($e) => $e['args'][0], $emailEvents);

        $this->assertFalse(in_array('customer_booking_received', $types, true), 'Customer email should be suppressed when send_customer_email is false.');
        $this->assertFalse(in_array('customer_booking_confirmed', $types, true), 'Customer email should be suppressed when send_customer_email is false.');
        $this->assertTrue(in_array('admin_new_booking', $types, true), 'Admin should still receive new booking alert.');
    }
}
