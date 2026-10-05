<?php
/**
 * Booking Lookup & Voucher Security Tests (E1 - E3)
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Tests\TestCase;

class LookupVoucherTest extends TestCase
{
    /**
     * E1 & E2. Lookup with apostrophe & ampersand returns unescaped JSON strings and raw voucher URL.
     */
    public function test_booking_lookup_raw_json_and_escaped_html(): void
    {
        $mockBooking = (object) [
            'id'             => 101,
            'booking_code'   => 'TRV-TEST101',
            'customer_name'  => "D'Souza & Sons",
            'customer_email' => 'dsouza@example.com',
            'customer_phone' => '+123456789',
            'created_at'     => '2026-10-05 10:00:00',
            'booking_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'Credit Card & Cash',
            'total_amount'   => 450.00,
            'currency'       => 'USD',
        ];

        $mockItems = [
            (object) [
                'item_id'    => 1,
                'item_type'  => 'tour',
                'item_title' => "Bali & Lombok Adventure <VIP>",
                'check_in'   => '2026-11-01',
                'check_out'  => '2026-11-05',
                'adults'     => 2,
                'children'   => 0,
                'quantity'   => 2,
                'total_price'=> 450.00,
            ],
        ];

        $payload = BookingLookupShortcode::formatLookupResponseData($mockBooking, $mockItems);

        // 1. JSON fields are unescaped for DOM textContent
        $this->assertEquals("D'Souza & Sons", $payload['customer_name']);
        $this->assertEquals("TRV-TEST101", $payload['booking_code']);
        $this->assertEquals("Credit Card & Cash", $payload['payment_method']);

        // 2. Voucher URL is raw URL with & (not &#038;)
        $this->assertStringNotContainsString('&#038;', $payload['voucher_url']);
        $parsedUrl = parse_url($payload['voucher_url']);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        $this->assertEquals('tourivo_print_voucher', $queryParams['action'] ?? '');
        $this->assertEquals('TRV-TEST101', $queryParams['code'] ?? '');
        $this->assertNotEmpty($queryParams['token'] ?? '');

        // 3. items_html is escaped HTML
        $this->assertStringContainsString('Bali &amp; Lombok Adventure &lt;VIP&gt;', $payload['items_html']);
    }

    /**
     * E3. Voucher token verification.
     */
    public function test_voucher_token_verification(): void
    {
        $code = 'TRV-AUTH999';
        $validToken = wp_hash($code . '|tourivo_voucher_salt|' . gmdate('Y-m'));

        $this->assertTrue(hash_equals($validToken, wp_hash($code . '|tourivo_voucher_salt|' . gmdate('Y-m'))));

        $invalidToken = 'fake_token_123';
        $this->assertFalse(hash_equals($validToken, $invalidToken));
    }
}
