<?php
/**
 * Regression tests for the "Medium" bug batch: default status setting, date validation, booking code retry,
 * admin pagination, GDPR log scrubbing, ICS correctness, webhook v2 signature, settings validation, Config cache.
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Admin\BookingsTable;
use Tourivo\Admin\SettingsPage;
use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\LogService;
use Tourivo\Services\WebhookService;
use Tourivo\Shortcodes\ThankYouShortcode;
use Tourivo\Support\Privacy;
use Tourivo\Tests\TestCase;

class MediumFixesTest extends TestCase
{
    protected BookingService $bookings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();
        $GLOBALS['tourivo_mock_options']          = ['admin_email' => 'admin@example.com'];
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $GLOBALS['tourivo_mock_action_callbacks'] = [];
        $GLOBALS['tourivo_mock_fail_next_booking_insert'] = 0;
        $_POST = [];
        Config::flushDefaults();

        $inv = new InventoryService(new InventoryRepository());
        $this->bookings = new BookingService($inv, new EmailService());
        Container::getInstance()->instance(BookingService::class, $this->bookings);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $GLOBALS['tourivo_mock_fail_next_booking_insert'] = 0;
        parent::tearDown();
    }

    protected function payload(int $tour, int $day = 30, array $extra = []): array
    {
        return $this->createBookingPayload(array_merge([
            'item_id'  => $tour,
            'check_in' => gmdate('Y-m-d', strtotime("+{$day} days")),
            'adults'   => 1,
        ], $extra));
    }

    // ---- default_booking_status setting

    public function test_default_booking_status_setting_is_honoured(): void
    {
        $tour = $this->createTour(100.0, 10);
        global $wpdb;

        update_option('tourivo_settings', ['default_booking_status' => 'confirmed']);
        $a = $this->bookings->createBooking($this->payload($tour, 30));
        $this->assertEquals('confirmed', $wpdb->bookings[$a['booking_id']]['booking_status']);

        update_option('tourivo_settings', ['default_booking_status' => 'pending']);
        $b = $this->bookings->createBooking($this->payload($tour, 31, ['customer_email' => 'b@example.com']));
        $this->assertEquals('pending', $wpdb->bookings[$b['booking_id']]['booking_status']);

        // Garbage in the option can never create a booking with a bogus status.
        update_option('tourivo_settings', ['default_booking_status' => 'refunded']);
        $c = $this->bookings->createBooking($this->payload($tour, 32, ['customer_email' => 'c@example.com']));
        $this->assertEquals('pending', $wpdb->bookings[$c['booking_id']]['booking_status']);
    }

    // ---- date validation

    public function test_malformed_or_inverted_dates_are_rejected_before_touching_inventory(): void
    {
        $tour = $this->createTour(100.0, 10);
        $in   = gmdate('Y-m-d', strtotime('+30 days'));

        foreach ([
            ['check_out' => 'tomorrow'],
            ['check_out' => gmdate('Y-m-d', strtotime('+29 days'))],
            ['check_in' => '2030-02-30'],
        ] as $override) {
            $res = $this->bookings->createBooking($this->payload($tour, 30, $override));
            $this->assertFalse($res['success'], json_encode($override));
        }

        global $wpdb;
        $this->assertEmpty($wpdb->inventories, 'Rejected input must never reserve inventory.');
        $this->assertEmpty($wpdb->bookings);

        // A room needs at least one night.
        $room = $this->createHotelWithRooms(3, 100.0)['room_id'];
        $same = $this->bookings->createBooking($this->payload($room, 30, ['item_type' => 'room', 'check_out' => $in]));
        $this->assertFalse($same['success']);
    }

    // ---- booking code collision

    public function test_booking_code_collision_is_retried(): void
    {
        $tour = $this->createTour(100.0, 10);
        $GLOBALS['tourivo_mock_fail_next_booking_insert'] = 2; // first two draws collide

        $res = $this->bookings->createBooking($this->payload($tour));

        $this->assertTrue($res['success'], $res['message'] ?? '');
        global $wpdb;
        $this->assertCount(1, $wpdb->bookings);
    }

    public function test_exhausted_code_retries_release_inventory(): void
    {
        $tour = $this->createTour(100.0, 10);
        $GLOBALS['tourivo_mock_fail_next_booking_insert'] = 3;

        $res = $this->bookings->createBooking($this->payload($tour));

        $this->assertFalse($res['success']);
        global $wpdb;
        $date = gmdate('Y-m-d', strtotime('+30 days'));
        $this->assertEquals(0, (int) $wpdb->inventories["{$tour}_{$date}"]['booked_count']);
    }

    // ---- admin pagination

    public function test_bookings_page_is_paginated_with_items_loaded_in_bulk(): void
    {
        $tour = $this->createTour(100.0, 100);
        for ($i = 0; $i < 25; $i++) {
            $this->bookings->createBooking($this->payload($tour, 30 + $i, ['customer_email' => "p{$i}@example.com"]), true);
        }

        $page1 = BookingsTable::fetchPage('all', '', 1, 20);
        $page2 = BookingsTable::fetchPage('all', '', 2, 20);

        $this->assertEquals(25, $page1['total']);
        $this->assertCount(20, $page1['bookings']);
        $this->assertCount(5, $page2['bookings']);
        $this->assertCount(20, $page1['items'], 'One line item per listed booking, fetched in a single query.');

        $firstId = (int) $page1['bookings'][0]->id;
        $this->assertTrue(isset($page1['items'][$firstId]));
    }

    // ---- GDPR log scrubbing

    public function test_erasure_scrubs_pii_from_log_details_but_keeps_audit_entries(): void
    {
        $tour = $this->createTour(100.0, 10);
        $res  = $this->bookings->createBooking($this->payload($tour, 30, ['customer_email' => 'erase.me@example.com']));
        $id   = $res['booking_id'];

        LogService::log($id, 'email_sent', 'Email [x] sent successfully to erase.me@example.com (attempt 1).');
        LogService::log($id, 'internal_note', 'Call erase.me on +880171...');
        LogService::log($id, 'status_changed', 'Booking status changed from Pending to Confirmed');

        Privacy::erasePersonalData('erase.me@example.com');

        global $wpdb;
        foreach ($wpdb->logs as $log) {
            if ((int) $log['booking_id'] !== $id) {
                continue;
            }
            $this->assertStringNotContainsString('erase.me', (string) $log['details']);
            if ($log['action'] === 'status_changed') {
                $this->assertStringContainsString('Confirmed', (string) $log['details'], 'Non-PII audit detail stays.');
            }
            if (in_array($log['action'], ['email_sent', 'internal_note'], true)) {
                $this->assertEquals('[redacted]', $log['details']);
            }
        }
    }

    // ---- ICS

    public function test_ics_uses_real_line_breaks_exclusive_dtend_and_status(): void
    {
        $tour = $this->createTour(100.0, 10);
        $day  = gmdate('Y-m-d', strtotime('+30 days'));
        $res  = $this->bookings->createBooking($this->payload($tour, 30));

        $ics = ThankYouShortcode::generateIcsContent($res['booking_id']);
        $this->assertStringContainsString('\\nTraveler: ', $ics, 'Description must contain the RFC 5545 "\\n" escape.');
        $this->assertStringNotContainsString('\\\\n', $ics, 'No double-escaped newline.');
        $this->assertStringContainsString('STATUS:TENTATIVE', $ics, 'Pending bookings are tentative.');
        $this->assertStringContainsString('DTEND;VALUE=DATE:' . gmdate('Ymd', strtotime($day . ' +1 day')), $ics, 'A single tour day ends the next day.');

        // Hotel stay: DTEND is the check-out date itself.
        $room  = $this->createHotelWithRooms(3, 100.0)['room_id'];
        $out   = gmdate('Y-m-d', strtotime('+33 days'));
        $stay  = $this->bookings->createBooking($this->payload($room, 30, ['item_type' => 'room', 'check_out' => $out, 'customer_email' => 'stay@example.com']));
        $this->assertTrue($stay['success'], $stay['message'] ?? '');
        $stayIcs = ThankYouShortcode::generateIcsContent($stay['booking_id']);
        $this->assertStringContainsString('DTEND;VALUE=DATE:' . gmdate('Ymd', strtotime($out)), $stayIcs);

        $this->bookings->changeStatus($res['booking_id'], 'confirmed');
        $this->assertStringContainsString('STATUS:CONFIRMED', ThankYouShortcode::generateIcsContent($res['booking_id']));
        $this->bookings->changeStatus($res['booking_id'], 'cancelled');
        $this->assertStringContainsString('STATUS:CANCELLED', ThankYouShortcode::generateIcsContent($res['booking_id']));
    }

    // ---- webhook signature

    public function test_webhook_v2_signature_binds_timestamp_and_keeps_legacy_header(): void
    {
        $body = '{"event":"booking.created"}';
        $h    = WebhookService::signatureHeaders($body, 'topsecret', 1700000000);

        $this->assertEquals(hash_hmac('sha256', $body, 'topsecret'), $h['X-Tourivo-Signature'], 'Legacy header unchanged.');
        $this->assertEquals('1700000000', $h['X-Tourivo-Timestamp']);
        $this->assertEquals(hash_hmac('sha256', '1700000000.' . $body, 'topsecret'), $h['X-Tourivo-Signature-V2']);

        $replayed = WebhookService::signatureHeaders($body, 'topsecret', 1700000999);
        $this->assertNotEquals($h['X-Tourivo-Signature-V2'], $replayed['X-Tourivo-Signature-V2'], 'Different timestamp -> different signature.');

        $this->assertEquals([], WebhookService::signatureHeaders($body, ''), 'No secret, no signature headers.');
    }

    // ---- settings validation

    public function test_settings_save_whitelists_and_clamps_values(): void
    {
        $_POST = [
            'currency'               => '12',
            'default_booking_status' => 'refunded',
            'tax_rate'               => '250',
            'border_radius'          => '8px;}</style><script>',
            'webhook_events'         => ['booking.created'],
        ];
        SettingsPage::saveSettings();
        $saved = get_option('tourivo_settings');

        $this->assertEquals('USD', $saved['currency'], 'Invalid code falls back to the previous/default value.');
        $this->assertEquals('pending', $saved['default_booking_status']);
        $this->assertEquals(100.0, $saved['tax_rate']);
        $this->assertEquals('8px', $saved['border_radius']);

        $_POST = ['currency' => 'bdt', 'default_booking_status' => 'confirmed', 'border_radius' => '12px'];
        SettingsPage::saveSettings();
        $saved = get_option('tourivo_settings');
        $this->assertEquals('BDT', $saved['currency']);
        $this->assertEquals('confirmed', $saved['default_booking_status']);
        $this->assertEquals('12px', $saved['border_radius']);
    }

    // ---- Config

    public function test_config_defaults_are_built_once_and_overridden_by_saved_values(): void
    {
        $this->assertEquals('USD', Config::get('currency'));
        $this->assertTrue(Config::getDefaults() === Config::getDefaults());

        update_option('tourivo_settings', ['currency' => 'GBP']);
        $this->assertEquals('GBP', Config::get('currency'), 'Saved settings must win immediately, no stale cache.');
        $this->assertEquals('pending', Config::get('default_booking_status'), 'Unsaved keys still come from defaults.');
    }
}
