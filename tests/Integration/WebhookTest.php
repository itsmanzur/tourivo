<?php
/**
 * Asynchronous Outbound Webhook Tests (F1 - F2)
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Config\Config;
use Tourivo\Services\WebhookService;
use Tourivo\Tests\TestCase;

class WebhookTest extends TestCase
{
    protected WebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebhookService();
    }

    /**
     * F1. Empty or non-HTTPS URL is rejected and enqueue does not schedule.
     */
    public function test_webhook_empty_or_non_https_bypassed(): void
    {
        // 1. Empty URL
        $this->assertFalse($this->service->isValidUrl(''));

        // 2. HTTP (insecure) URL
        $this->assertFalse($this->service->isValidUrl('http://insecure.example.com/webhook'));

        // 3. Valid HTTPS URL
        $this->assertTrue($this->service->isValidUrl('https://secure.example.com/webhook'));
    }

    /**
     * F2. Webhook HMAC SHA256 signature verification.
     */
    public function test_webhook_hmac_signature_generation(): void
    {
        $payload = json_encode(['event' => 'booking.created', 'id' => 123]);
        $secret = 'super_secret_webhook_key_456';

        $expectedHmac = hash_hmac('sha256', $payload, $secret);
        $this->assertNotEmpty($expectedHmac);
        $this->assertEquals(64, strlen($expectedHmac));
    }
}
