<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Core\Deactivator;
use Tourivo\Core\Uninstaller;
use Tourivo\Providers\AdminServiceProvider;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\LogService;
use Tourivo\Services\PricingService;
use Tourivo\Services\PrivacyService;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Support\Privacy;
use Tourivo\Support\SiteTime;
use Tourivo\Tests\TestCase;

/**
 * Class ReviewFixesTest
 *
 * Regression tests for the 1.5.0 review fixes:
 * 1. Every scheduled event is removed on deactivation / uninstall, whatever its args.
 * 2. Email jobs carry no personal data; the worker reloads the booking or inquiry.
 * 3. Test emails bypass the queue, deduplication and booking logs.
 * 4. Status-change deduplication is per transition.
 * 5. Reply-To: support address for customers, the customer for staff.
 * 6. Availability AJAX checks the post type and edit_post; capacity guard lives in SQL.
 * 7. Self-cancellation cutoff and dates use the site timezone.
 * 8. GDPR: exported booking items, address-free logs, scrubbed logs on erasure, trip-end retention.
 */
class ReviewFixesTest extends TestCase
{
    protected InventoryService $inventoryService;
    protected EmailService $emailService;
    protected BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();

        $GLOBALS['tourivo_mock_sent_emails']      = [];
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $GLOBALS['tourivo_mock_filters']          = [];
        $GLOBALS['tourivo_mock_action_callbacks'] = [];
        unset($GLOBALS['tourivo_mock_user_caps']);
        update_option('tourivo_settings', []);
        update_option('timezone_string', '');

        $this->inventoryService = new InventoryService(new InventoryRepository());
        $pricing                = new PricingService($this->inventoryService);
        $this->emailService     = new EmailService();
        $this->bookingService   = new BookingService($this->inventoryService, $this->emailService, $pricing);

        $container = Container::getInstance();
        $container->instance(InventoryService::class, $this->inventoryService);
        $container->instance(EmailService::class, $this->emailService);
        $container->instance(BookingService::class, $this->bookingService);
        $container->instance(PricingService::class, $pricing);

        $this->emailService->register();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['tourivo_mock_user_caps']);
        update_option('timezone_string', '');
        $_POST = [];
        parent::tearDown();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emailJobs(): array
    {
        return array_values(array_filter(
            $GLOBALS['tourivo_mock_scheduled_events'] ?? [],
            static fn ($e) => $e['hook'] === EmailService::QUEUE_HOOK
        ));
    }

    /**
     * Run every queued email job once, like the worker would.
     */
    private function runEmailJobs(): void
    {
        $jobs = $this->emailJobs();
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        foreach ($jobs as $job) {
            $this->emailService->dispatch(...$job['args']);
        }
    }

    private function book(string $email = 'ayesha@example.com', string $checkIn = '', array $extra = []): array
    {
        $tourId = $this->createTour(100.0, 10);
        return $this->bookingService->createBooking(array_merge([
            'item_id'        => $tourId,
            'check_in'       => $checkIn !== '' ? $checkIn : gmdate('Y-m-d', strtotime('+10 days')),
            'adults'         => 2,
            'customer_name'  => 'Ayesha Rahman',
            'customer_email' => $email,
            'customer_phone' => '+8801711000111',
        ], $extra), !empty($extra));
    }

    // ------------------------------------------------------------------ 1. Unscheduling

    public function test_deactivation_removes_every_tourivo_event_whatever_its_args(): void
    {
        wp_schedule_event(time(), 'daily', 'tourivo_daily_reminders');
        wp_schedule_single_event(time(), 'tourivo_process_email_delivery', ['customer_booking_received', 'hash', 5, 1]);
        wp_schedule_single_event(time() + 120, 'tourivo_process_webhook_delivery', ['booking.created', ['id' => 5], 2]);
        as_enqueue_async_action('tourivo_process_email_delivery', ['admin_new_booking', '', 5, 1], 'tourivo-emails');
        as_schedule_single_action(time() + 300, 'tourivo_process_webhook_delivery', ['booking.created', [], 3], 'tourivo-webhooks');
        wp_schedule_single_event(time(), 'another_plugin_hook', ['keep']);

        Deactivator::unscheduleAll();

        $left = array_column($GLOBALS['tourivo_mock_scheduled_events'], 'hook');
        $this->assertEquals(['another_plugin_hook'], array_values($left), 'Only the foreign event may remain.');
    }

    public function test_uninstall_removes_queued_actions_too(): void
    {
        wp_schedule_single_event(time(), 'tourivo_process_email_delivery', ['customer_booking_received', 'hash', 5, 1]);
        as_enqueue_async_action('tourivo_process_email_delivery', ['admin_new_booking', '', 5, 1], 'tourivo-emails');
        wp_schedule_event(time(), 'daily', 'tourivo_daily_privacy_retention');

        Uninstaller::uninstall();

        $this->assertEmpty($GLOBALS['tourivo_mock_scheduled_events'], 'Uninstall must leave no Tourivo event behind.');
    }

    // ------------------------------------------------------------------ 2. No PII in jobs

    public function test_email_jobs_hold_only_type_hash_id_and_attempt(): void
    {
        $res = $this->book('ayesha@example.com');
        $jobs = $this->emailJobs();
        $this->assertNotEmpty($jobs);

        foreach ($jobs as $job) {
            $args = $job['args'];
            $this->assertCount(4, $args);
            $this->assertTrue(is_string($args[0]) && is_string($args[1]) && is_int($args[2]) && is_int($args[3]));
            $this->assertEquals((int) $res['booking_id'], $args[2]);
            $encoded = (string) wp_json_encode($args);
            foreach (['ayesha@example.com', 'Ayesha', '8801711000111'] as $pii) {
                $this->assertStringNotContainsString($pii, $encoded, 'Job args must not contain ' . $pii);
            }
            if (str_starts_with($args[0], 'admin_')) {
                $this->assertEquals('', $args[1], 'Staff mail needs no recipient hash.');
            } else {
                $this->assertEquals($this->emailService->recipientHash('ayesha@example.com'), $args[1]);
            }
        }
    }

    public function test_worker_loads_the_booking_and_sends(): void
    {
        $this->book('ayesha@example.com');
        $this->runEmailJobs();

        $to = array_column($GLOBALS['tourivo_mock_sent_emails'], 'to');
        $this->assertTrue(in_array('ayesha@example.com', $to, true), 'Customer mail goes to the stored address.');
        $customerMail = $GLOBALS['tourivo_mock_sent_emails'][array_search('ayesha@example.com', $to, true)];
        $this->assertStringContainsString('Ayesha Rahman', (string) $customerMail['message'], 'Body is built from the reloaded booking.');
    }

    public function test_worker_does_not_mail_an_address_that_changed_after_queueing(): void
    {
        global $wpdb;
        $res = $this->book('ayesha@example.com');
        $wpdb->bookings[(int) $res['booking_id']]['customer_email'] = 'anon-1@anonymized.invalid';

        $customerJobs = array_filter($this->emailJobs(), static fn ($e) => !str_starts_with($e['args'][0], 'admin_'));
        $job = reset($customerJobs);
        $this->assertFalse($this->emailService->dispatch(...$job['args']), 'Erased or changed address must not be mailed.');
        $this->assertEmpty($GLOBALS['tourivo_mock_sent_emails']);
    }

    public function test_retry_job_is_also_free_of_personal_data(): void
    {
        $res = $this->book('ayesha@example.com');
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        add_filter('pre_wp_mail', fn () => false);

        $this->emailService->dispatch('customer_booking_received', $this->emailService->recipientHash('ayesha@example.com'), (int) $res['booking_id'], 1);

        $retries = $this->emailJobs();
        $this->assertCount(1, $retries);
        $this->assertEquals(['customer_booking_received', $this->emailService->recipientHash('ayesha@example.com'), (int) $res['booking_id'], 2], $retries[0]['args']);
    }

    public function test_inquiry_mail_is_queued_by_id_and_rebuilt_at_send_time(): void
    {
        global $wpdb;
        $tourId = $this->createTour(100.0, 10);
        $wpdb->insert($wpdb->prefix . 'tourivo_inquiries', [
            'item_id'        => $tourId,
            'item_type'      => 'tour',
            'customer_name'  => 'Karim Uddin',
            'customer_email' => 'karim@example.com',
            'customer_phone' => '+8801999000222',
            'message'        => 'Is the boat ride included?',
            'guests'         => 3,
            'created_at'     => gmdate('Y-m-d H:i:s'),
        ]);
        $inquiryId = (int) $wpdb->insert_id;

        $this->emailService->onInquiryCreated($inquiryId, ['inquiry_id' => $inquiryId, 'name' => 'Karim Uddin', 'email' => 'karim@example.com', 'message' => 'Is the boat ride included?']);

        $jobs = $this->emailJobs();
        $this->assertCount(1, $jobs);
        $this->assertEquals(['admin_new_inquiry', '', $inquiryId, 1], $jobs[0]['args']);

        $this->runEmailJobs();
        $this->assertCount(1, $GLOBALS['tourivo_mock_sent_emails']);
        $mail = $GLOBALS['tourivo_mock_sent_emails'][0];
        $this->assertStringContainsString('Is the boat ride included?', (string) $mail['message']);
        $this->assertStringContainsString('Reply-To: Karim Uddin <karim@example.com>', implode("\n", (array) $mail['headers']));
    }

    public function test_job_queued_by_an_older_version_still_sends_from_fresh_data(): void
    {
        $res = $this->book('ayesha@example.com');
        $GLOBALS['tourivo_mock_sent_emails'] = [];

        $sent = $this->emailService->dispatch('customer_booking_received', 'ayesha@example.com', [
            'id'             => (int) $res['booking_id'],
            'customer_email' => 'ayesha@example.com',
            'customer_name'  => 'Stale Name',
        ], 1);

        $this->assertTrue($sent);
        $this->assertStringNotContainsString('Stale Name', (string) $GLOBALS['tourivo_mock_sent_emails'][0]['message']);
    }

    // ------------------------------------------------------------------ 3. Test email

    public function test_test_email_is_sent_directly_without_logs_transients_or_queue(): void
    {
        global $wpdb, $tourivo_mock_transients;
        $logsBefore       = count($wpdb->logs);
        $transientsBefore = $tourivo_mock_transients;

        $this->assertTrue($this->emailService->sendTestEmail('qa@example.com'));

        $this->assertCount(1, $GLOBALS['tourivo_mock_sent_emails']);
        $this->assertEquals('qa@example.com', $GLOBALS['tourivo_mock_sent_emails'][0]['to']);
        $this->assertCount($logsBefore, $wpdb->logs, 'No booking log for a test email.');
        $this->assertEquals($transientsBefore, $tourivo_mock_transients, 'No dedup or other transient.');
        $this->assertEmpty($this->emailJobs(), 'Nothing is queued.');
        $this->assertEmpty(LogService::getTimeline(999), 'The old mock booking ID 999 is gone.');
    }

    // ------------------------------------------------------------------ 4. Dedup per transition

    public function test_confirmation_after_cancellation_mails_again(): void
    {
        $res = $this->book('ayesha@example.com');
        $id  = (int) $res['booking_id'];
        $GLOBALS['tourivo_mock_scheduled_events'] = [];

        $this->assertTrue($this->bookingService->changeStatus($id, 'confirmed')['success']);
        $this->assertTrue($this->bookingService->changeStatus($id, 'cancelled')['success']);
        $this->assertTrue($this->bookingService->changeStatus($id, 'confirmed')['success']);

        $confirmed = array_filter($this->emailJobs(), static fn ($e) => $e['args'][0] === 'customer_booking_confirmed');
        $this->assertCount(2, $confirmed, 'confirmed -> cancelled -> confirmed sends the confirmation twice.');
    }

    public function test_same_transition_without_context_falls_back_to_updated_at(): void
    {
        $res = $this->book('ayesha@example.com');
        $GLOBALS['tourivo_mock_scheduled_events'] = [];

        $this->emailService->onBookingStatusChanged((int) $res['booking_id'], 'pending', 'confirmed');
        $this->emailService->onBookingStatusChanged((int) $res['booking_id'], 'pending', 'confirmed');

        $this->assertCount(1, $this->emailJobs(), 'A repeated hook call for the same change mails once.');
    }

    // ------------------------------------------------------------------ 5. Reply-To

    public function test_customer_mail_replies_go_to_the_site_address(): void
    {
        update_option('tourivo_settings', ['email_notification_address' => 'support@tourivo.test']);
        $this->book('ayesha@example.com');
        $this->runEmailJobs();

        foreach ($GLOBALS['tourivo_mock_sent_emails'] as $mail) {
            $headers = implode("\n", (array) $mail['headers']);
            if ($mail['to'] === 'ayesha@example.com') {
                $this->assertStringContainsString('<support@tourivo.test>', $headers);
                $this->assertStringNotContainsString('Reply-To: Ayesha', $headers);
            } else {
                $this->assertStringContainsString('Reply-To: Ayesha Rahman <ayesha@example.com>', $headers, 'Staff can reply straight to the customer.');
            }
        }
    }

    public function test_staff_reply_to_is_sanitized_and_falls_back_without_a_valid_address(): void
    {
        update_option('tourivo_settings', ['email_notification_address' => 'support@tourivo.test']);

        $value = $this->emailService->replyTo('admin_new_booking', [
            'customer_email' => 'eve@example.com',
            'customer_name'  => "Eve <x>\r\nBcc: evil@example.com",
        ], 'Tourivo');
        $this->assertStringNotContainsString("\r", $value);
        $this->assertStringNotContainsString("\n", $value);
        $this->assertStringContainsString('<eve@example.com>', $value);

        $fallback = $this->emailService->replyTo('admin_new_booking', ['customer_email' => 'not-an-address'], 'Tourivo');
        $this->assertEquals('Tourivo <support@tourivo.test>', $fallback);
    }

    // ------------------------------------------------------------------ 6. Availability auth + capacity guard

    private function postAvailability(int $itemId, string $itemType): array
    {
        $_POST = [
            'nonce'      => wp_create_nonce('tourivo_admin_nonce'),
            'item_id'    => $itemId,
            'item_type'  => $itemType,
            'start_date' => gmdate('Y-m-d', strtotime('+20 days')),
            'capacity'   => '8',
        ];
        $GLOBALS['tourivo_last_ajax_response'] = null;
        (new AdminServiceProvider(Container::getInstance()))->handleUpdateAvailability();
        return (array) $GLOBALS['tourivo_last_ajax_response'];
    }

    public function test_availability_rejects_an_id_of_another_post_type(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $GLOBALS['tourivo_mock_user_caps'] = ['edit_post' => true];

        $this->assertEquals(403, $this->postAvailability($tourId, 'room')['status'] ?? 0, 'A tour ID sent as a room is refused.');
        $this->assertEquals(403, $this->postAvailability($tourId, 'gadget')['status'] ?? 0, 'Unknown item types are refused.');
        $this->assertEquals(200, $this->postAvailability($tourId, 'tour')['status'] ?? 0);
    }

    public function test_availability_needs_edit_post_not_just_plugin_caps(): void
    {
        $tourId = $this->createTour(100.0, 10);

        $GLOBALS['tourivo_mock_user_caps'] = ['manage_tourivo' => true, 'manage_tourivo_tours' => true, 'manage_tourivo_hotels' => true];
        $this->assertEquals(403, $this->postAvailability($tourId, 'tour')['status'] ?? 0, 'Plugin caps alone no longer pass.');

        $GLOBALS['tourivo_mock_user_caps'] = ['edit_post' => true];
        $this->assertEquals(200, $this->postAvailability($tourId, 'tour')['status'] ?? 0);
    }

    public function test_capacity_cut_below_active_seats_is_refused_by_the_write_itself(): void
    {
        global $wpdb;
        $repo   = new InventoryRepository();
        $tourId = $this->createTour(100.0, 10);
        $date   = gmdate('Y-m-d', strtotime('+15 days'));

        $this->assertTrue($repo->upsert($tourId, 'tour', $date, 'all_day', 10));
        // Seats taken after the admin screen validated the change.
        $wpdb->inventories["{$tourId}_{$date}"]['booked_count']   = 3;
        $wpdb->inventories["{$tourId}_{$date}"]['reserved_count'] = 1;

        $this->assertFalse($repo->upsert($tourId, 'tour', $date, 'all_day', 2), 'Capacity 2 < 4 active seats must fail.');
        $this->assertEquals('capacity_below_active', $repo->lastError);
        $this->assertEquals(10, $wpdb->inventories["{$tourId}_{$date}"]['total_capacity'], 'Row stays untouched.');

        $this->assertTrue($repo->upsert($tourId, 'tour', $date, 'all_day', 4), 'Exactly the active seats is fine.');
        $this->assertEquals(4, $wpdb->inventories["{$tourId}_{$date}"]['total_capacity']);
        $this->assertEquals('', $repo->lastError);
    }

    // ------------------------------------------------------------------ 7. Site timezone

    public function test_cancel_cutoff_does_not_shift_by_the_utc_offset_in_dhaka(): void
    {
        update_option('timezone_string', 'Asia/Dhaka');

        $cutoff = SiteTime::cancelCutoff('2026-11-14', 24);
        $this->assertEquals(strtotime('2026-11-12 18:00:00 UTC'), $cutoff->getTimestamp(), '24 h before 14 Nov 00:00 Dhaka = 12 Nov 18:00 UTC.');

        $naive = strtotime('2026-11-14') - 24 * 3600;
        $this->assertEquals(6 * 3600, $naive - $cutoff->getTimestamp(), 'The old UTC reading was 6 hours late.');
    }

    public function test_self_cancel_closes_on_dhaka_time_not_utc(): void
    {
        update_option('timezone_string', 'Asia/Dhaka');

        // Trip starts at local midnight in 2 days; the window closes 3 hours ago in Dhaka time,
        // but would still be open for 3 more hours if the date were read as UTC.
        $now      = current_datetime();
        $checkIn  = $now->modify('+2 days')->format('Y-m-d');
        $midnight = new \DateTimeImmutable($checkIn . ' 00:00:00', wp_timezone());
        $hours    = (int) ceil(($midnight->getTimestamp() - $now->getTimestamp()) / 3600) + 3;

        update_option('tourivo_settings', ['allow_cancel_requests' => 1, 'customer_self_cancel_hours' => $hours]);
        $res = $this->book('late@example.com', $checkIn);

        $out = BookingLookupShortcode::processCancellationRequest((string) $res['booking_code'], 'late@example.com', 'Too late');
        $this->assertTrue($out['success']);
        $this->assertNotEquals('cancelled', $out['mode'] ?? '', 'Past the local cutoff only a request is possible.');
    }

    public function test_trip_dates_display_without_shift_and_utc_stamps_convert(): void
    {
        update_option('timezone_string', 'Asia/Dhaka');

        $this->assertEquals('Nov 14, 2026', SiteTime::formatTripDate('2026-11-14'));
        $this->assertEquals('2026-11-14', SiteTime::tripDate('2026-11-14 00:00:00'));
        $this->assertEquals('2026-11-14', SiteTime::formatGmt('2026-11-13 20:00:00', 'Y-m-d'), '20:00 UTC is already the next day in Dhaka.');
    }

    // ------------------------------------------------------------------ 8. GDPR

    public function test_exporter_lists_booked_items_with_dates_and_party(): void
    {
        $checkIn = gmdate('Y-m-d', strtotime('+12 days'));
        $this->book('export@example.com', $checkIn);

        $export = Privacy::exportPersonalData('export@example.com');
        $fields = [];
        foreach ($export['data'] as $group) {
            foreach ($group['data'] as $f) {
                $fields[] = $f;
            }
        }
        $items = array_values(array_filter($fields, static fn ($f) => $f['name'] === 'Booked Item'));
        $this->assertCount(1, $items);
        $this->assertStringContainsString('Test Tour', $items[0]['value']);
        $this->assertStringContainsString($checkIn, $items[0]['value']);
        $this->assertStringContainsString('adults: 2', $items[0]['value']);
    }

    public function test_email_log_entries_name_no_address(): void
    {
        $res = $this->book('ayesha@example.com');
        $this->runEmailJobs();

        $entries = array_filter(LogService::getTimeline((int) $res['booking_id']), static fn ($l) => str_starts_with((string) $l['action'], 'email_'));
        $this->assertNotEmpty($entries);
        foreach ($entries as $l) {
            $this->assertStringNotContainsString('@', (string) $l['details']);
        }
    }

    public function test_erasure_scrubs_the_travelers_values_from_every_log_entry(): void
    {
        $res = $this->book('jane@example.com', '', ['customer_name' => 'Jane Roe', 'customer_phone' => '+8801755000333']);
        $id  = (int) $res['booking_id'];
        LogService::log($id, 'payment_recorded', 'Cash from Jane Roe (+8801755000333), receipt sent to jane@example.com');
        LogService::log($id, 'status_changed', 'Booking status changed from Pending to Confirmed');

        Privacy::erasePersonalData('jane@example.com');

        $all = implode(' | ', array_column(LogService::getTimeline($id), 'details'));
        foreach (['Jane Roe', '+8801755000333', 'jane@example.com'] as $pii) {
            $this->assertStringNotContainsString($pii, $all, 'Log still holds ' . $pii);
        }
        $this->assertStringContainsString('Booking status changed from Pending to Confirmed', $all, 'Non-personal history is kept.');
    }

    public function test_retention_counts_from_trip_end_and_honours_statuses(): void
    {
        global $wpdb;
        update_option('tourivo_settings', ['anonymize_after_days' => 30, 'retention_statuses' => ['completed']]);

        $old = $this->book('old@example.com', gmdate('Y-m-d', strtotime('-40 days')), ['booking_status' => 'completed']);
        // Created long ago, but travelled 10 days ago: not due yet.
        $recentTrip = $this->book('recent@example.com', gmdate('Y-m-d', strtotime('-10 days')), [
            'booking_status' => 'completed',
            'created_at'     => gmdate('Y-m-d H:i:s', strtotime('-200 days')),
        ]);
        $cancelled = $this->book('cancel@example.com', gmdate('Y-m-d', strtotime('-40 days')), ['booking_status' => 'cancelled']);

        // A stay whose check-in is old but check-out is recent counts from the check-out.
        $hotel = $this->createHotelWithRooms(3, 120.0);
        $stay  = $this->bookingService->createBooking([
            'item_id'        => $hotel['room_id'],
            'item_type'      => 'room',
            'check_in'       => gmdate('Y-m-d', strtotime('-40 days')),
            'check_out'      => gmdate('Y-m-d', strtotime('-20 days')),
            'adults'         => 1,
            'customer_name'  => 'Long Stay',
            'customer_email' => 'stay@example.com',
            'booking_status' => 'completed',
        ], true);

        $this->assertEquals(1, PrivacyService::getRetentionDryRunCounts()['bookings_to_anonymize']);
        $this->assertEquals(1, PrivacyService::runDailyRetention()['anonymized_bookings']);

        $this->assertEquals('Anonymized', $wpdb->bookings[(int) $old['booking_id']]['customer_name']);
        $this->assertNotEquals('Anonymized', $wpdb->bookings[(int) $recentTrip['booking_id']]['customer_name']);
        $this->assertNotEquals('Anonymized', $wpdb->bookings[(int) $cancelled['booking_id']]['customer_name'], 'Cancelled is not in the configured statuses.');
        $this->assertNotEquals('Anonymized', $wpdb->bookings[(int) $stay['booking_id']]['customer_name'], 'Counted from the check-out, 20 days ago.');
    }

    public function test_legacy_month_setting_still_applies_as_days(): void
    {
        update_option('tourivo_settings', ['anonymize_after_months' => 2]);
        $this->assertEquals(60, PrivacyService::retentionDays());
        $this->assertEquals(['completed', 'cancelled'], PrivacyService::retentionStatuses());

        update_option('tourivo_settings', ['anonymize_after_months' => 2, 'anonymize_after_days' => 14]);
        $this->assertEquals(14, PrivacyService::retentionDays(), 'A saved day value wins.');
    }
}
