<?php
/**
 * REST API Security, Whitelisting, and Defense Tests (D1 - D3)
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Controllers\Api\BookingController;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Support\ClientIp;
use Tourivo\Tests\TestCase;
use WP_REST_Request;

class RestSecurityTest extends TestCase
{
    protected BookingController $controller;
    protected BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $invRepo = new InventoryRepository();
        $invService = new InventoryService($invRepo);
        $emailService = new EmailService();
        $this->bookingService = new BookingService($invService, $emailService);

        $container = Container::getInstance();
        $this->controller = new BookingController($container, $this->bookingService);
    }

    /**
     * D1. Direct checkout strips malicious privileged parameters.
     */
    public function test_rest_direct_checkout_whitelists_parameters(): void
    {
        $tourId = $this->createTour(200.0, 10);
        $date = gmdate('Y-m-d', strtotime('+35 days'));

        $request = new WP_REST_Request('POST', '/tourivo/v1/bookings/direct');
        $request->set_body_params([
            'item_id'        => $tourId,
            'item_type'      => 'tour',
            'check_in'       => $date,
            'adults'         => 1,
            'customer_name'  => 'Alice Traveler',
            'customer_email' => 'alice@example.com',
            'booking_status' => 'confirmed', // Should be ignored
            'payment_status' => 'paid',      // Should be ignored
            'total_amount'   => 0.00,        // Should be ignored
        ]);

        $response = $this->controller->directCheckout($request);
        $this->assertFalse(is_wp_error($response));

        global $wpdb;
        if ($wpdb) {
            $data = $response->get_data();
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tourivo_bookings WHERE id = %d",
                $data['booking_id']
            ));
            $this->assertEquals('pending', $booking->booking_status);
            $this->assertEquals('pending', $booking->payment_status);
            $this->assertEquals(200.00, (float) $booking->total_amount);
        }
    }

    /**
     * D2. Honeypot check triggers instant silent block.
     */
    public function test_honeypot_triggers_block(): void
    {
        $request = new WP_REST_Request('POST', '/tourivo/v1/bookings/direct');
        $request->set_body_params([
            'tourivo_hp_check' => 'I am a bot',
            'customer_name'    => 'Bot user',
        ]);

        $response = $this->controller->directCheckout($request);
        $this->assertTrue(is_wp_error($response));
        $this->assertEquals('spam_detected', $response->get_error_code());
    }

    /**
     * D2. IP Rate limiting blocks on > 5 requests per 10 minutes.
     */
    public function test_rate_limiting_triggers_429(): void
    {
        $tourId = $this->createTour(100.0, 20);
        $date = gmdate('Y-m-d', strtotime('+50 days'));

        $ip = ClientIp::get();
        $rateLimitKey = 'trv_rl_booking_' . md5($ip);
        set_transient($rateLimitKey, 5, 600); // Pre-fill 5 attempts

        $request = new WP_REST_Request('POST', '/tourivo/v1/bookings/direct');
        $request->set_body_params([
            'item_id'        => $tourId,
            'check_in'       => $date,
            'customer_name'  => 'Rate Limit Test',
            'customer_email' => 'ratelimit@example.com',
        ]);

        $response = $this->controller->directCheckout($request);
        $this->assertTrue(is_wp_error($response));
        $this->assertEquals('too_many_requests', $response->get_error_code());
        $this->assertEquals(429, $response->get_error_data()['status']);
    }

    /**
     * D3. ClientIp permutations across proxy modes.
     */
    public function test_client_ip_matrix_security(): void
    {
        // 1. Spoofed CF header from non-CF remote IP
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '5.6.7.8';
        update_option('tourivo_settings', ['proxy_mode' => 'cloudflare']);
        $this->assertEquals('1.2.3.4', ClientIp::get());

        // 2. Spoofed XFF header from untrusted proxy
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
        update_option('tourivo_settings', ['proxy_mode' => 'reverse_proxy', 'trusted_proxies' => '10.0.0.0/8']);
        $this->assertEquals('1.2.3.4', ClientIp::get());
    }
}
