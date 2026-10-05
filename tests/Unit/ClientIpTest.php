<?php
/**
 * Client IP Anti-Spoofing Unit Tests
 */

declare(strict_types=1);

namespace Tourivo\Tests\Unit;

use Tourivo\Support\ClientIp;
use Tourivo\Tests\TestCase;

class ClientIpTest extends TestCase
{
    /**
     * Test disabled mode returns REMOTE_ADDR directly.
     */
    public function test_client_ip_disabled_mode_returns_remote_addr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.195';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.22';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.33';

        update_option('tourivo_settings', ['proxy_mode' => 'disabled']);

        $ip = ClientIp::get();
        $this->assertEquals('203.0.113.195', $ip);
    }

    /**
     * Test Cloudflare mode rejects spoofed header from unverified edge IP.
     */
    public function test_client_ip_cloudflare_mode_rejects_spoofed_header(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.5'; // Not in CF edge range
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.1.1.1';

        update_option('tourivo_settings', ['proxy_mode' => 'cloudflare']);

        $ip = ClientIp::get();
        $this->assertEquals('198.51.100.5', $ip);
    }

    /**
     * Test Cloudflare mode accepts header from verified edge IP.
     */
    public function test_client_ip_cloudflare_mode_accepts_verified_edge_ip(): void
    {
        $_SERVER['REMOTE_ADDR'] = '173.245.48.50'; // In Cloudflare CIDR 173.245.48.0/20
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.88';

        update_option('tourivo_settings', ['proxy_mode' => 'cloudflare']);

        $ip = ClientIp::get();
        $this->assertEquals('203.0.113.88', $ip);
    }

    /**
     * Test reverse proxy mode parses X-Forwarded-For right-to-left.
     */
    public function test_client_ip_reverse_proxy_parses_xff_right_to_left(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1'; // Trusted proxy
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99, 10.0.0.2';

        update_option('tourivo_settings', [
            'proxy_mode'      => 'reverse_proxy',
            'trusted_proxies' => '10.0.0.0/8',
        ]);

        $ip = ClientIp::get();
        $this->assertEquals('203.0.113.99', $ip);
    }
}
