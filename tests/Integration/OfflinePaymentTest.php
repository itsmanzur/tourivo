<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PaymentService;
use Tourivo\Services\PricingService;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Shortcodes\ThankYouShortcode;
use Tourivo\Tests\TestCase;
use WP_User;

/**
 * Class OfflinePaymentTest
 *
 * Tests for Offline Payment Expansion (Bank Transfer & Cash on Arrival):
 * 1. Configuration of structured bank transfer details and cash on arrival.
 * 2. Structured bank card and HTML generation.
 * 3. Customer transaction reference submission with HMAC signed token.
 * 4. Customer transaction reference submission with email verification.
 * 5. Rejection of invalid reference submissions.
 * 6. Admin quick payment recording (Full settlement, auto-confirmation, audit logging).
 * 7. Admin recording partial payment and due amount calculation.
 * 8. Unauthorized payment recording rejection.
 * 9. Direct booking preservation of whitelisted offline payment methods.
 */
class OfflinePaymentTest extends TestCase
{
    protected InventoryService $inventoryService;
    protected EmailService $emailService;
    protected BookingService $bookingService;
    protected PricingService $pricingService;
    protected PaymentService $paymentService;

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
        $this->paymentService   = new PaymentService();

        $container = Container::getInstance();
        $container->instance(InventoryService::class, $this->inventoryService);
        $container->instance(EmailService::class, $this->emailService);
        $container->instance(BookingService::class, $this->bookingService);
        $container->instance(PaymentService::class, $this->paymentService);
    }

    /**
     * Helper to create a mock booking in database.
     */
    protected function insertMockBooking(array $overrides = []): int
    {
        global $wpdb;

        $id = count($wpdb->bookings) + 1;
        $code = 'TRV-' . strtoupper(substr(md5((string) $id), 0, 8));

        $booking = array_merge([
            'id'                  => $id,
            'booking_code'        => $code,
            'customer_id'         => 1,
            'customer_name'       => 'Test Traveler',
            'customer_email'      => 'traveler@example.com',
            'customer_phone'      => '+1234567890',
            'billing_address'     => '123 Travel Street',
            'total_amount'        => 500.00,
            'tax_amount'          => 50.00,
            'discount_amount'     => 0.00,
            'paid_amount'         => 0.00,
            'due_amount'          => 500.00,
            'currency'            => 'USD',
            'payment_method'      => 'bank_transfer',
            'payment_status'      => 'pending',
            'booking_status'      => 'pending',
            'check_in_status'     => 'pending',
            'transaction_id'      => null,
            'customer_notes'      => '',
            'admin_notes'         => '',
            'cancel_requested_at' => null,
            'email_verified_at'   => '2026-10-01 12:00:00',
            'access_key'          => md5($code . 'secret_key'),
            'created_at'          => '2026-10-05 10:00:00',
            'updated_at'          => '2026-10-05 10:00:00',
        ], $overrides);

        $wpdb->bookings[$id] = $booking;

        return $id;
    }

    /**
     * 1. Test: Offline payment methods configuration defaults and active list.
     */
    /**
     * 1. Test: Offline payment methods configuration defaults and active list.
     */
    public function test_offline_payment_methods_configuration(): void
    {
        update_option('tourivo_settings', [
            'bank_transfer_enabled'        => true,
            'bank_name'                    => 'Standard Chartered',
            'bank_account_name'            => 'Tourivo Holidays Ltd.',
            'bank_account_number'          => '01-2345678-01',
            'bank_iban'                    => 'SCBL12345678',
            'bank_swift_routing'           => 'SCBLBDDH',
            'bank_branch'                  => 'Principal Branch',
            'bank_instructions'            => 'Deposit at counter.',
            'cash_on_arrival_enabled'      => true,
            'cash_on_arrival_instructions' => 'Pay upon arrival at tour reception desk.',
        ]);

        $methods = $this->paymentService->getOfflinePaymentMethods();
        $details = $methods['bank_transfer']['details'];
        $this->assertSame('Standard Chartered', (string) $details['bank_name']);
        $this->assertSame('01-2345678-01', (string) $details['account_number']);
        $this->assertSame('Pay upon arrival at tour reception desk.', $methods['cash_on_arrival']['instructions']);
    }

    /**
     * 2. Test: Formatted bank details HTML card rendering.
     */
    public function test_bank_details_html_rendering(): void
    {
        update_option('tourivo_settings', array_merge(Config::getDefaults(), [
            'bank_transfer_enabled' => true,
            'bank_name'             => 'City Bank Global',
            'bank_account_name'     => 'Tourivo Agency',
            'bank_account_number'   => '98765432100',
            'bank_iban'             => 'GB29NWBK60161331926819',
            'bank_swift_routing'    => 'CIBLBDDH',
            'bank_branch'           => 'Dhaka Main Branch',
            'bank_instructions'     => 'Please write booking code on deposit slip.',
        ]));

        $html = $this->paymentService->getBankDetailsHtml();

        $this->assertStringContainsString('City Bank Global', $html);
        $this->assertStringContainsString('98765432100', $html);
        $this->assertStringContainsString('GB29NWBK60161331926819', $html);
        $this->assertStringContainsString('CIBLBDDH', $html);
        $this->assertStringContainsString('Dhaka Main Branch', $html);
        $this->assertStringContainsString('Please write booking code on deposit slip.', $html);
    }

    /**
     * 3. Test: Customer submits transaction reference with valid HMAC token.
     */
    public function test_customer_submit_transaction_reference_with_token(): void
    {
        global $wpdb;

        $bookingId = $this->insertMockBooking(['total_amount' => 750.00]);
        $booking = $wpdb->bookings[$bookingId];
        $token   = ThankYouShortcode::generateThankYouToken($bookingId);

        $res = $this->paymentService->submitTransactionReference(
            (string) $booking['booking_code'],
            'TXN-BANK-998877',
            $token
        );

        $this->assertTrue($res['success']);

        $updated = $wpdb->bookings[$bookingId];
        $this->assertSame('TXN-BANK-998877', $updated['transaction_id']);
        $this->assertStringContainsString('TXN-BANK-998877', (string) $updated['admin_notes']);
    }

    /**
     * 4. Test: Customer submits transaction reference with email lookup.
     */
    public function test_customer_submit_transaction_reference_with_email(): void
    {
        global $wpdb;

        $bookingId = $this->insertMockBooking(['customer_email' => 'client@travel.com']);
        $booking   = $wpdb->bookings[$bookingId];

        $res = $this->paymentService->submitTransactionReference(
            (string) $booking['booking_code'],
            'BKASH-77112233',
            null,
            'client@travel.com'
        );

        $this->assertTrue($res['success']);

        $updated = $wpdb->bookings[$bookingId];
        $this->assertSame('BKASH-77112233', $updated['transaction_id']);
    }

    /**
     * 5. Test: Rejection of invalid token or email during reference submission.
     */
    public function test_customer_submit_transaction_reference_invalid_auth_fails(): void
    {
        $bookingId = $this->insertMockBooking();
        global $wpdb;
        $booking = $wpdb->bookings[$bookingId];

        // Invalid token
        $res = $this->paymentService->submitTransactionReference(
            (string) $booking['booking_code'],
            'TXN-BAD',
            'invalid_token_12345'
        );
        $this->assertFalse($res['success']);

        // Wrong email
        $res2 = $this->paymentService->submitTransactionReference(
            (string) $booking['booking_code'],
            'TXN-BAD',
            null,
            'wrong_email@example.com'
        );
        $this->assertFalse($res2['success']);
    }

    /**
     * 6. Test: Admin records full payment (marks paid, zeroes due, auto-confirms).
     */
    public function test_admin_record_payment_full_paid_and_confirm(): void
    {
        global $wpdb;

        $adminUser = wp_set_current_user(1);
        $adminUser->allcaps['manage_tourivo_bookings'] = true;

        $bookingId = $this->insertMockBooking([
            'total_amount'   => 800.00,
            'paid_amount'    => 0.00,
            'due_amount'     => 800.00,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);

        $res = $this->paymentService->recordPayment($bookingId, [
            'amount'         => 800.00,
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
            'transaction_id' => 'BANK-DEPOSIT-554433',
            'auto_confirm'   => true,
            'note'           => 'Verified via Bank statement.',
        ]);

        $this->assertTrue($res['success']);

        $updated = $wpdb->bookings[$bookingId];
        $this->assertEquals(800.00, (float) $updated['paid_amount']);
        $this->assertEquals(0.00, (float) $updated['due_amount']);
        $this->assertSame('paid', $updated['payment_status']);
        $this->assertSame('confirmed', $updated['booking_status']);
        $this->assertSame('BANK-DEPOSIT-554433', $updated['transaction_id']);
        $this->assertSame('bank_transfer', $updated['payment_method']);
    }

    /**
     * 7. Test: Admin records partial payment and calculates remaining due amount.
     */
    public function test_admin_record_payment_partial_amount(): void
    {
        global $wpdb;

        $adminUser = wp_set_current_user(1);
        $adminUser->allcaps['manage_tourivo_bookings'] = true;

        $bookingId = $this->insertMockBooking([
            'total_amount'   => 1200.00,
            'paid_amount'    => 0.00,
            'due_amount'     => 1200.00,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);

        $res = $this->paymentService->recordPayment($bookingId, [
            'amount'         => 500.00,
            'payment_status' => 'partially_paid',
            'payment_method' => 'bank_transfer',
            'transaction_id' => 'PARTIAL-ADVANCE-01',
            'auto_confirm'   => false,
        ]);

        $this->assertTrue($res['success']);

        $updated = $wpdb->bookings[$bookingId];
        $this->assertEquals(500.00, (float) $updated['paid_amount']);
        $this->assertEquals(700.00, (float) $updated['due_amount']); // 1200 - 500
        $this->assertSame('partially_paid', $updated['payment_status']);
        $this->assertSame('pending', $updated['booking_status']);
    }

    /**
     * 8. Test: Unauthorized user is rejected when attempting to record payment.
     */
    public function test_admin_record_payment_unauthorized_fails(): void
    {
        $nonAdmin = wp_set_current_user(99);
        $nonAdmin->allcaps['manage_tourivo_bookings'] = false;

        $bookingId = $this->insertMockBooking();

        $res = $this->paymentService->recordPayment($bookingId, [
            'amount'         => 100.00,
            'payment_status' => 'paid',
        ]);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Unauthorized', $res['message']);
    }

    /**
     * 9. Test: Public booking creation preserves whitelisted offline payment methods.
     */
    public function test_public_booking_supports_offline_payment_methods(): void
    {
        $tourId = $this->createTour(200.0, 10);

        // 9a. Bank Transfer
        $payloadBank = $this->createBookingPayload([
            'item_id'        => $tourId,
            'item_type'      => 'tour',
            'check_in'       => '2026-11-01',
            'check_out'      => '2026-11-01',
            'adults'         => 1,
            'customer_name'  => 'Bank Traveler',
            'customer_email' => 'bank@example.com',
            'payment_method' => 'bank_transfer',
        ]);
        $resBank = $this->bookingService->createBooking($payloadBank, false);
        $this->assertTrue($resBank['success']);

        global $wpdb;
        $bookingBank = $wpdb->bookings[$resBank['booking_id']];
        $this->assertSame('bank_transfer', $bookingBank['payment_method']);

        // 9b. Cash on Arrival
        $payloadCash = $this->createBookingPayload([
            'item_id'        => $tourId,
            'item_type'      => 'tour',
            'check_in'       => '2026-11-02',
            'check_out'      => '2026-11-02',
            'adults'         => 1,
            'customer_name'  => 'Cash Traveler',
            'customer_email' => 'cash@example.com',
            'payment_method' => 'cash_on_arrival',
        ]);
        $resCash = $this->bookingService->createBooking($payloadCash, false);
        $this->assertTrue($resCash['success']);

        $bookingCash = $wpdb->bookings[$resCash['booking_id']];
        $this->assertSame('cash_on_arrival', $bookingCash['payment_method']);
    }
}
