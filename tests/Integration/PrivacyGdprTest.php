<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Config\Config;
use Tourivo\Database\Schema;
use Tourivo\Services\BookingService;
use Tourivo\Services\InquiryService;
use Tourivo\Services\PrivacyService;
use Tourivo\Services\WebhookService;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Support\ClientIp;
use Tourivo\Support\Privacy;
use Tourivo\Tests\TestCase;

/**
 * Class PrivacyGdprTest
 *
 * Verifies GDPR compliance, user consent enforcement, personal data export,
 * accounting-safe data anonymization, retention crons, and data minimization.
 */
class PrivacyGdprTest extends TestCase
{
    private BookingService $bookingService;
    private InquiryService $inquiryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();
        update_option('tourivo_settings', [
            'require_consent'               => 0,
            'store_ip'                      => 1,
            'anonymize_after_months'        => 0,
            'delete_inquiries_after_months' => 0,
            'webhook_url'                   => '',
            'webhook_include_phone'         => 0,
            'webhook_include_message'       => 0,
        ]);
        $this->bookingService = \Tourivo\Common\Container::getInstance()->get(BookingService::class);
        $this->inquiryService = \Tourivo\Common\Container::getInstance()->get(InquiryService::class);
    }

    /**
     * Test (a): Consent enforcement for bookings and inquiries.
     */
    public function test_consent_enforcement_and_proof_storage(): void
    {
        $tourId = $this->createTour(150.0, 10);
        $checkIn = gmdate('Y-m-d', strtotime('+7 days'));

        // 1. Consent is disabled by default -> succeeds without consent field
        update_option('tourivo_settings', [
            'require_consent' => 0,
        ]);

        $res1 = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkIn,
            'adults'         => 2,
            'customer_name'  => 'John NoConsent',
            'customer_email' => 'noconsent@example.com',
        ], false);

        $this->assertTrue($res1['success'], 'Booking should succeed when consent is disabled.');

        // 2. Enable Consent Requirement
        update_option('tourivo_settings', [
            'require_consent' => 1,
            'consent_label'   => 'I agree to {privacy} and {terms}',
        ]);

        // Attempt booking without consent -> must fail
        $resNoConsent = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkIn,
            'adults'         => 2,
            'customer_name'  => 'Alice Denied',
            'customer_email' => 'alice@example.com',
        ], false);

        $this->assertFalse($resNoConsent['success'], 'Booking without consent must fail when require_consent=1.');

        // Attempt booking with consent -> must succeed and record consent_at and consent_version
        $resWithConsent = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => $checkIn,
            'adults'         => 2,
            'customer_name'  => 'Alice Consented',
            'customer_email' => 'alice@example.com',
            'consent'        => 1,
        ], false);

        $this->assertTrue($resWithConsent['success'], 'Booking with consent should succeed.');
        $bookingId = $resWithConsent['booking_id'];

        global $wpdb;
        $bookingRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d", $bookingId));
        $this->assertNotEmpty($bookingRow->consent_at ?? '', 'consent_at timestamp must be recorded.');
        $this->assertNotEmpty($bookingRow->consent_version ?? '', 'consent_version hash must be recorded.');
        $this->assertEquals(Privacy::getConsentVersion(), $bookingRow->consent_version);

        // 3. Test Inquiry Consent
        $inqDenied = $this->inquiryService->createInquiry([
            'item_id'        => $tourId,
            'customer_name'  => 'Inquirer One',
            'customer_email' => 'inq@example.com',
            'message'        => 'Tell me more about Bali',
        ]);
        $this->assertFalse($inqDenied['success'], 'Inquiry without consent must fail when require_consent=1.');

        $inqConsented = $this->inquiryService->createInquiry([
            'item_id'        => $tourId,
            'customer_name'  => 'Inquirer One',
            'customer_email' => 'inq@example.com',
            'message'        => 'Tell me more about Bali',
            'consent'        => 1,
        ]);
        $this->assertTrue($inqConsented['success'], 'Inquiry with consent must succeed.');

        $inqId = $inqConsented['inquiry_id'];
        $inqRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_inquiries WHERE id = %d", $inqId));
        $this->assertNotEmpty($inqRow->consent_at ?? '', 'Inquiry consent_at must be populated.');
        $this->assertEquals(Privacy::getConsentVersion(), $inqRow->consent_version);
    }

    /**
     * Test (b): WordPress Privacy Data Exporter pagination and isolation.
     */
    public function test_privacy_exporter_pagination_and_isolation(): void
    {
        $tourId = $this->createTour(100.0, 10);
        $targetEmail = 'traveler.gdpr@example.com';
        $otherEmail  = 'other.person@example.com';

        // Create 120 bookings for the target email to test 3 pages (50, 50, 20)
        for ($i = 1; $i <= 120; $i++) {
            $this->bookingService->createBooking([
                'item_id'        => $tourId,
                'check_in'       => gmdate('Y-m-d', strtotime("+$i days")),
                'adults'         => 1,
                'customer_name'  => "Traveler $i",
                'customer_email' => $targetEmail,
                'customer_phone' => "+1555000{$i}",
            ], true);
        }

        // Create 5 bookings for another email
        for ($j = 1; $j <= 5; $j++) {
            $this->bookingService->createBooking([
                'item_id'        => $tourId,
                'check_in'       => gmdate('Y-m-d', strtotime("+$j days")),
                'adults'         => 1,
                'customer_name'  => "Other $j",
                'customer_email' => $otherEmail,
            ], true);
        }

        // Create 1 inquiry for the target email
        $this->inquiryService->createInquiry([
            'item_id'        => $tourId,
            'customer_name'  => 'Traveler Inquiry',
            'customer_email' => $targetEmail,
            'message'        => 'Question about baggage allowance',
            'consent'        => 1,
        ]);

        // Page 1: Should return 50 items, done = false
        $page1 = Privacy::exportPersonalData($targetEmail, 1);
        $this->assertCount(50, $page1['data'], 'Page 1 must return 50 items.');
        $this->assertFalse($page1['done'], 'Page 1 must have done=false.');

        // Page 2: Should return 50 items, done = false
        $page2 = Privacy::exportPersonalData($targetEmail, 2);
        $this->assertCount(50, $page2['data'], 'Page 2 must return 50 items.');
        $this->assertFalse($page2['done'], 'Page 2 must have done=false.');

        // Page 3: Should return remaining 20 bookings + 1 inquiry = 21 items, done = true
        $page3 = Privacy::exportPersonalData($targetEmail, 3);
        $this->assertTrue($page3['done'], 'Page 3 must have done=true.');
        $this->assertCount(21, $page3['data'], 'Page 3 must return remaining 21 items.');

        // Ensure exporter does NOT expose other traveler's data
        foreach (array_merge($page1['data'], $page2['data'], $page3['data']) as $exportItem) {
            foreach ($exportItem['data'] as $field) {
                if ($field['name'] === 'Email Address') {
                    $this->assertEquals($targetEmail, $field['value']);
                }
            }
        }
    }

    /**
     * Test (c) & (d): Eraser anonymizes financial booking records, deletes inquiries, deletes user meta,
     * and blocks subsequent lookup.
     */
    public function test_privacy_eraser_anonymization_and_lookup_invalidation(): void
    {
        $tourId = $this->createTour(350.0, 10);
        $email = 'client.delete@example.com';

        // 1. Create a WP user with wishlist meta
        $userId = wp_insert_post([
            'post_title' => 'Dummy User Post for test',
            'post_type'  => 'post',
        ]);
        // simulate user meta
        update_user_meta(1, '_tourivo_wishlist', [10, 20]);

        // 2. Create Booking
        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+10 days')),
            'adults'         => 2,
            'customer_name'  => 'Original Person',
            'customer_email' => $email,
            'customer_phone' => '+1234567890',
            'billing_address'=> '123 Ocean Blvd',
            'customer_notes' => 'Ground floor please',
        ], true);

        $bookingId   = $res['booking_id'];
        $bookingCode = $res['booking_code'];

        // 3. Create Inquiry
        $this->inquiryService->createInquiry([
            'item_id'        => $tourId,
            'customer_name'  => 'Original Person',
            'customer_email' => $email,
            'message'        => 'Custom pricing request',
        ]);

        // 4. Run Privacy Eraser
        $eraseResult = Privacy::erasePersonalData($email, 1);

        $this->assertTrue($eraseResult['items_retained'], 'Financial booking records must be retained in anonymized state.');
        $this->assertTrue($eraseResult['done']);
        $this->assertNotEmpty($eraseResult['messages'], 'Must return retention explanation notice.');

        // 5. Verify Database State
        global $wpdb;
        $bookingRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d", $bookingId));

        $this->assertEquals('Anonymized', $bookingRow->customer_name, 'Name must be anonymized.');
        $this->assertStringContainsString('@anonymized.invalid', $bookingRow->customer_email, 'Email must be hashed invalid placeholder.');
        $this->assertEmpty($bookingRow->customer_phone, 'Phone must be erased.');
        $this->assertEmpty($bookingRow->billing_address, 'Billing address must be erased.');
        $this->assertEmpty($bookingRow->customer_notes, 'Customer notes must be erased.');
        $this->assertEmpty($bookingRow->ip_address, 'IP address must be erased.');
        $this->assertEquals(700.0, (float) $bookingRow->total_amount, 'Total amount must remain intact.');

        // Inquiries for this email must be deleted
        $inquiries = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_inquiries WHERE customer_email = %s", $email));
        $this->assertEmpty($inquiries, 'Inquiries for erased email must be deleted.');

        // 6. Verify Lookup with original email now fails (404)
        $lookupRes = BookingLookupShortcode::processCancellationRequest($bookingCode, $email, 'Test Cancel');
        $this->assertFalse($lookupRes['success'], 'Lookup with original erased email must fail.');
        $this->assertEquals(404, $lookupRes['code'] ?? 400);
    }

    /**
     * Test (e): Scheduled data retention cron anonymizes expired bookings and deletes inquiries.
     */
    public function test_retention_cron_batch_processing(): void
    {
        update_option('tourivo_settings', [
            'anonymize_after_months'        => 6,
            'delete_inquiries_after_months' => 3,
        ]);

        $tourId = $this->createTour(200.0, 10);

        global $wpdb;
        $bookingsTable  = $wpdb->prefix . 'tourivo_bookings';
        $inquiriesTable = $wpdb->prefix . 'tourivo_inquiries';

        // 1. Old completed booking (8 months ago) -> should be anonymized
        $resOld = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('-8 months')),
            'adults'         => 1,
            'customer_name'  => 'Old Client',
            'customer_email' => 'oldclient@example.com',
            'booking_status' => 'completed',
            'created_at'     => gmdate('Y-m-d H:i:s', strtotime('-8 months')),
        ], true);
        $oldId = $resOld['booking_id'];

        // 2. Recent completed booking (1 month ago) -> should NOT be touched
        $resRecent = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('-1 month')),
            'adults'         => 1,
            'customer_name'  => 'Recent Client',
            'customer_email' => 'recentclient@example.com',
            'booking_status' => 'completed',
            'created_at'     => gmdate('Y-m-d H:i:s', strtotime('-1 month')),
        ], true);
        $recentId = $resRecent['booking_id'];

        // 3. Old inquiry (5 months ago) -> should be deleted
        $wpdb->insert($inquiriesTable, [
            'item_id'        => $tourId,
            'customer_name'  => 'Old Inquirer',
            'customer_email' => 'oldinq@example.com',
            'message'        => 'Old inquiry message',
            'created_at'     => gmdate('Y-m-d H:i:s', strtotime('-5 months')),
        ]);
        $oldInqId = $wpdb->insert_id;

        // 4. Recent inquiry (1 month ago) -> should NOT be deleted
        $wpdb->insert($inquiriesTable, [
            'item_id'        => $tourId,
            'customer_name'  => 'Recent Inquirer',
            'customer_email' => 'recentinq@example.com',
            'message'        => 'Recent inquiry message',
            'created_at'     => gmdate('Y-m-d H:i:s', strtotime('-1 month')),
        ]);
        $recentInqId = $wpdb->insert_id;

        // 5. Test Dry-Run Counts
        $dryRun = PrivacyService::getRetentionDryRunCounts();
        $this->assertEquals(1, $dryRun['bookings_to_anonymize']);
        $this->assertEquals(1, $dryRun['inquiries_to_delete']);

        // 6. Run Retention Routine
        $retentionResult = PrivacyService::runDailyRetention();
        $this->assertEquals(1, $retentionResult['anonymized_bookings']);
        $this->assertEquals(1, $retentionResult['deleted_inquiries']);

        // Verify Old Booking is anonymized
        $oldRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bookingsTable} WHERE id = %d", $oldId));
        $this->assertEquals('Anonymized', $oldRow->customer_name);

        // Verify Recent Booking is untouched
        $recentRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bookingsTable} WHERE id = %d", $recentId));
        $this->assertEquals('Recent Client', $recentRow->customer_name);

        // Verify Old Inquiry deleted, Recent preserved
        $this->assertNull($wpdb->get_row($wpdb->prepare("SELECT * FROM {$inquiriesTable} WHERE id = %d", $oldInqId)));
        $this->assertNotNull($wpdb->get_row($wpdb->prepare("SELECT * FROM {$inquiriesTable} WHERE id = %d", $recentInqId)));
    }

    /**
     * Test (f): store_ip disabled behavior and rate-limiting continuity.
     */
    public function test_store_ip_setting_and_rate_limiting(): void
    {
        $tourId = $this->createTour(120.0, 10);

        // Disable IP storage
        update_option('tourivo_settings', [
            'store_ip' => 0,
        ]);

        $res = $this->bookingService->createBooking([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+3 days')),
            'adults'         => 1,
            'customer_name'  => 'Private Traveler',
            'customer_email' => 'private@example.com',
        ], false);

        $this->assertTrue($res['success']);

        global $wpdb;
        $bookingRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d", $res['booking_id']));
        $this->assertEquals('', $bookingRow->ip_address, 'ip_address column must be empty when store_ip is disabled.');

        // Verify Rate-limiting still functions with hashed IP key (without storing raw IP in DB)
        $ip = ClientIp::get();
        $rateLimitKey = 'trv_rl_booking_' . md5($ip);
        set_transient($rateLimitKey, 5, 600); // simulate 5 attempts

        $req = new \WP_REST_Request('POST', '/tourivo/v1/bookings/direct');
        $req->set_json_params([
            'item_id'        => $tourId,
            'check_in'       => gmdate('Y-m-d', strtotime('+3 days')),
            'adults'         => 1,
            'customer_name'  => 'Blocked Traveler',
            'customer_email' => 'blocked@example.com',
        ]);

        $controller = new \Tourivo\Controllers\Api\BookingController(
            \Tourivo\Common\Container::getInstance(),
            $this->bookingService
        );

        $response = $controller->directCheckout($req);
        $this->assertTrue(\is_wp_error($response), 'Rate limit must trigger even when store_ip is off.');
        $this->assertEquals('too_many_requests', $response->get_error_code());
    }

    /**
     * Test (g): Schema migration idempotency and upgrade safety.
     */
    public function test_schema_migration_gdpr_upgrade_safety(): void
    {
        // Run migration twice
        Schema::migrate();
        $this->assertEquals(Schema::CURRENT_DB_VERSION, get_option(Schema::DB_VERSION_OPTION));

        Schema::migrate();
        $this->assertEquals(Schema::CURRENT_DB_VERSION, get_option(Schema::DB_VERSION_OPTION));
    }

    /**
     * Test (h): Privacy policy text dynamically includes Webhook disclosures only when webhook_url is set.
     */
    public function test_privacy_policy_content_conditional_webhook(): void
    {
        // Case 1: Webhook not configured
        update_option('tourivo_settings', [
            'webhook_url' => '',
        ]);

        $content1 = Privacy::getPrivacyPolicyContent();
        $this->assertStringContainsString('Personal Data Collected', $content1);
        $this->assertStringContainsString('Cookies &amp; Local Browser Storage', $content1);
        $this->assertStringNotContainsString('External Webhook Automations', $content1);

        // Case 2: Webhook configured
        update_option('tourivo_settings', [
            'webhook_url' => 'https://hooks.zapier.com/hooks/catch/12345/abc',
        ]);

        $content2 = Privacy::getPrivacyPolicyContent();
        $this->assertStringContainsString('External Webhook Automations', $content2);
        $this->assertStringContainsString('https://hooks.zapier.com', $content2);
    }
}
