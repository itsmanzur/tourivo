<?php
/**
 * Anti-abuse regression tests: atomic rate limiter, per-email throttle, email verification,
 * stale/pending booking expiry, mandatory lookup nonce and cancellation-request throttling.
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Database\Schema;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Support\RateLimiter;
use Tourivo\Tests\TestCase;

class BookingHygieneTest extends TestCase
{
    protected InventoryService $inventory;
    protected EmailService $email;
    protected BookingService $bookings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();
        $GLOBALS['tourivo_mock_options']           = [];
        $GLOBALS['tourivo_mock_scheduled_events']  = [];
        $GLOBALS['tourivo_mock_action_callbacks']  = [];
        $GLOBALS['tourivo_mock_ext_object_cache']  = false;
        $GLOBALS['tourivo_last_ajax_response']     = null;
        $_POST = [];

        $this->inventory = new InventoryService(new InventoryRepository());
        $this->email     = new EmailService();
        $this->bookings  = new BookingService($this->inventory, $this->email);

        $container = Container::getInstance();
        $container->instance(InventoryService::class, $this->inventory);
        $container->instance(EmailService::class, $this->email);
        $container->instance(BookingService::class, $this->bookings);

        $this->email->register();
    }

    protected function tearDown(): void
    {
        $GLOBALS['tourivo_mock_ext_object_cache'] = false;
        $_POST = [];
        parent::tearDown();
    }

    protected function settings(array $s): void
    {
        update_option('tourivo_settings', $s);
    }

    protected function emailTypes(): array
    {
        $types = [];
        foreach ($GLOBALS['tourivo_mock_scheduled_events'] as $ev) {
            if ($ev['hook'] === 'tourivo_process_email_delivery') {
                $types[] = $ev['args'][0];
            }
        }
        return $types;
    }

    protected function ageBooking(int $bookingId, int $secondsAgo): void
    {
        global $wpdb;
        $wpdb->bookings[$bookingId]['created_at'] = gmdate('Y-m-d H:i:s', time() - $secondsAgo);
    }

    protected function book(int $tourId, int $dayOffset, string $email = 'guest@example.com', array $extra = [], bool $trusted = false): array
    {
        return $this->bookings->createBooking($this->createBookingPayload(array_merge([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime("+{$dayOffset} days")),
            'adults'         => 1,
            'customer_email' => $email,
        ], $extra)), $trusted);
    }

    // ---------------------------------------------------------------- rate limiter

    public function test_rate_limiter_blocks_after_limit_with_transient_fallback(): void
    {
        $this->assertTrue(RateLimiter::hit('t_rl', 3, 60));
        $this->assertTrue(RateLimiter::hit('t_rl', 3, 60));
        $this->assertTrue(RateLimiter::hit('t_rl', 3, 60));
        $this->assertFalse(RateLimiter::hit('t_rl', 3, 60));

        RateLimiter::reset('t_rl');
        $this->assertTrue(RateLimiter::hit('t_rl', 3, 60));
    }

    public function test_rate_limiter_uses_atomic_counter_with_object_cache(): void
    {
        $GLOBALS['tourivo_mock_ext_object_cache'] = true;

        $results = [];
        for ($i = 0; $i < 4; $i++) {
            $results[] = RateLimiter::hit('t_rl_atomic', 2, 60);
        }

        $this->assertEquals([true, true, false, false], $results);
        $this->assertFalse(get_transient('t_rl_atomic'), 'Atomic path must not touch the transient counter.');
    }

    // ---------------------------------------------------------------- per-email throttle

    public function test_public_bookings_are_throttled_per_email_address(): void
    {
        $tour = $this->createTour(100.0, 50);

        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue($this->book($tour, 20 + $i, 'victim@example.com')['success'], "Booking {$i} should pass.");
        }

        $sixth = $this->book($tour, 30, 'victim@example.com');
        $this->assertFalse($sixth['success']);

        // A different address is unaffected, and trusted (admin) bookings are never throttled.
        $this->assertTrue($this->book($tour, 30, 'other@example.com')['success']);
        $this->assertTrue($this->book($tour, 31, 'victim@example.com', [], true)['success']);
    }

    // ---------------------------------------------------------------- email verification

    public function test_unverified_booking_only_sends_verification_mail_until_confirmed(): void
    {
        $this->settings(['require_email_verification' => 1]);
        $tour = $this->createTour(100.0, 10);

        $res = $this->book($tour, 25, 'new@example.com');
        $this->assertTrue($res['success']);
        $this->assertTrue($res['requires_verification']);

        global $wpdb;
        $this->assertEmpty($wpdb->bookings[$res['booking_id']]['email_verified_at']);
        $this->assertEquals(['customer_verify_email'], $this->emailTypes(), 'Only the confirmation link may be mailed; no staff alert yet.');

        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $token = BookingService::generateVerificationToken($res['booking_id'], 'new@example.com');

        $this->assertFalse($this->bookings->verifyEmail($res['booking_code'], 'bad-token')['success']);
        $ok = $this->bookings->verifyEmail($res['booking_code'], $token);
        $this->assertTrue($ok['success']);
        $this->assertNotEmpty($wpdb->bookings[$res['booking_id']]['email_verified_at']);

        $types = $this->emailTypes();
        $this->assertTrue(in_array('admin_new_booking', $types, true), 'Staff alert is released after confirmation.');
        $this->assertTrue(in_array('customer_booking_received', $types, true), 'Normal received mail is released after confirmation.');

        // Replaying the link is harmless and does not re-send mail.
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $this->assertTrue($this->bookings->verifyEmail($res['booking_code'], $token)['success']);
        $this->assertEquals([], $this->emailTypes());
    }

    public function test_verification_disabled_marks_booking_verified_and_mails_normally(): void
    {
        $tour = $this->createTour(100.0, 10);
        $res  = $this->book($tour, 25);

        global $wpdb;
        $this->assertFalse($res['requires_verification']);
        $this->assertNotEmpty($wpdb->bookings[$res['booking_id']]['email_verified_at']);
        $this->assertTrue(in_array('customer_booking_received', $this->emailTypes(), true));
    }

    public function test_verification_link_is_bound_to_booking_and_email(): void
    {
        $this->settings(['require_email_verification' => 1]);
        $tour = $this->createTour(100.0, 10);

        $a = $this->book($tour, 25, 'a@example.com');
        $b = $this->book($tour, 26, 'b@example.com');

        $tokenForB = BookingService::generateVerificationToken($b['booking_id'], 'b@example.com');
        $this->assertFalse($this->bookings->verifyEmail($a['booking_code'], $tokenForB)['success']);
    }

    // ---------------------------------------------------------------- expiry

    public function test_unverified_bookings_expire_release_inventory_and_stay_silent(): void
    {
        $this->settings(['require_email_verification' => 1, 'unverified_expiry_minutes' => 30]);
        $tour = $this->createTour(100.0, 5);
        $date = gmdate('Y-m-d', strtotime('+25 days'));

        $stale = $this->book($tour, 25, 'stale@example.com', ['adults' => 2]);
        $fresh = $this->book($tour, 26, 'fresh@example.com');
        $this->ageBooking($stale['booking_id'], 3 * 3600);

        global $wpdb;
        $this->assertEquals(2, (int) $wpdb->inventories["{$tour}_{$date}"]['booked_count']);

        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $result = $this->bookings->expireStaleBookings();

        $this->assertEquals(1, $result['unverified']);
        $this->assertEquals('cancelled', $wpdb->bookings[$stale['booking_id']]['booking_status']);
        $this->assertEquals('pending', $wpdb->bookings[$fresh['booking_id']]['booking_status'], 'Recent bookings are left alone.');
        $this->assertEquals(0, (int) $wpdb->inventories["{$tour}_{$date}"]['booked_count'], 'Spots must be released.');
        $this->assertEquals([], $this->emailTypes(), 'No cancellation mail or staff alert for an unverified address.');
    }

    public function test_verified_booking_never_expires_as_unverified(): void
    {
        $this->settings(['require_email_verification' => 1, 'unverified_expiry_minutes' => 30]);
        $tour = $this->createTour(100.0, 5);

        $res = $this->book($tour, 25);
        $this->bookings->verifyEmail($res['booking_code'], BookingService::generateVerificationToken($res['booking_id'], 'guest@example.com'));
        $this->ageBooking($res['booking_id'], 5 * 3600);

        $this->assertEquals(['unverified' => 0, 'pending' => 0], $this->bookings->expireStaleBookings());
    }

    public function test_pending_expiry_cancels_unpaid_only_and_is_off_by_default(): void
    {
        $tour = $this->createTour(100.0, 10);
        $unpaid = $this->book($tour, 25, 'u@example.com');
        $paid   = $this->book($tour, 26, 'p@example.com', ['payment_status' => 'paid', 'booking_status' => 'pending'], true);
        $this->ageBooking($unpaid['booking_id'], 100 * 3600);
        $this->ageBooking($paid['booking_id'], 100 * 3600);

        $this->assertEquals(['unverified' => 0, 'pending' => 0], $this->bookings->expireStaleBookings(), 'Disabled by default.');

        $this->settings(['pending_expiry_hours' => 48]);
        $result = $this->bookings->expireStaleBookings();

        global $wpdb;
        $this->assertEquals(1, $result['pending']);
        $this->assertEquals('cancelled', $wpdb->bookings[$unpaid['booking_id']]['booking_status']);
        $this->assertEquals('pending', $wpdb->bookings[$paid['booking_id']]['booking_status'], 'Paid bookings must never auto-expire.');
    }

    public function test_pro_filter_can_veto_expiry(): void
    {
        $this->settings(['pending_expiry_hours' => 1]);
        $tour = $this->createTour(100.0, 10);
        $res  = $this->book($tour, 25);
        $this->ageBooking($res['booking_id'], 10 * 3600);

        add_filter('tourivo/expire_booking', fn () => false, 10, 3);

        $this->assertEquals(0, $this->bookings->expireStaleBookings()['pending']);
    }

    // ---------------------------------------------------------------- migration

    public function test_migration_backfills_existing_bookings_as_verified(): void
    {
        global $wpdb;
        $wpdb->bookings[7] = ['id' => 7, 'booking_code' => 'TRV-OLD', 'booking_status' => 'pending', 'created_at' => '2025-01-01 10:00:00', 'email_verified_at' => null];
        update_option(Schema::DB_VERSION_OPTION, '1.3.0');

        Schema::migrate();

        $this->assertEquals('2025-01-01 10:00:00', $wpdb->bookings[7]['email_verified_at']);
        $this->assertEquals(Schema::CURRENT_DB_VERSION, get_option(Schema::DB_VERSION_OPTION));
    }

    // ---------------------------------------------------------------- lookup nonce + cancel throttle

    protected function lastError(): array
    {
        $r = $GLOBALS['tourivo_last_ajax_response'];
        return ['status' => $r['status'] ?? 0, 'code' => $r['body']['data']['code'] ?? '', 'success' => $r['body']['success'] ?? null];
    }

    public function test_lookup_and_cancel_ajax_reject_missing_or_invalid_nonce(): void
    {
        $tour = $this->createTour(100.0, 10);
        $res  = $this->book($tour, 25, 'lk@example.com');

        $payload = ['booking_code' => $res['booking_code'], 'customer_email' => 'lk@example.com'];

        $_POST = $payload;
        BookingLookupShortcode::handleLookupAjax();
        $this->assertEquals(['status' => 403, 'code' => 'nonce_expired', 'success' => false], $this->lastError());

        $_POST = $payload + ['nonce' => 'forged'];
        BookingLookupShortcode::handleLookupAjax();
        $this->assertEquals('nonce_expired', $this->lastError()['code']);

        $GLOBALS['tourivo_last_ajax_response'] = null;
        $_POST = $payload + ['reason' => 'x'];
        BookingLookupShortcode::handleCancellationAjax();
        $this->assertEquals('nonce_expired', $this->lastError()['code']);

        global $wpdb;
        $this->assertEmpty($wpdb->bookings[$res['booking_id']]['cancel_requested_at'] ?? null, 'Request without nonce must not be recorded.');

        $_POST = $payload + ['nonce' => wp_create_nonce('tourivo_lookup_nonce')];
        BookingLookupShortcode::handleLookupAjax();
        $this->assertTrue(($GLOBALS['tourivo_last_ajax_response']['body']['success'] ?? false) === true);
    }

    public function test_repeated_cancellation_requests_do_not_realert_admin(): void
    {
        $tour = $this->createTour(100.0, 10);
        $res  = $this->book($tour, 25, 'cx@example.com');
        $GLOBALS['tourivo_mock_scheduled_events'] = [];

        $first  = BookingLookupShortcode::processCancellationRequest($res['booking_code'], 'cx@example.com', 'change of plans');
        $second = BookingLookupShortcode::processCancellationRequest($res['booking_code'], 'cx@example.com', 'again');

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertEquals('requested', $second['mode']);
        $this->assertEquals(['admin_booking_cancelled'], $this->emailTypes(), 'Admin is alerted exactly once.');
    }
}
