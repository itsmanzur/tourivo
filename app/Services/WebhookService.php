<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Config\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WebhookService
 *
 * Dispatches outbound real-time webhook notifications to external endpoints
 * (Zapier, Make.com, n8n, custom CRMs, Slack, etc.) on key booking lifecycle events.
 *
 * @package Tourivo\Services
 */
class WebhookService
{
    /**
     * WebhookService constructor.
     */
    public function __construct()
    {
        // Hook into core lifecycle events
        add_action('tourivo/booking_created', [$this, 'onBookingCreated'], 20, 2);
        add_action('tourivo/booking_status_changed', [$this, 'onBookingStatusChanged'], 20, 3);
        add_action('tourivo/inquiry_created', [$this, 'onInquiryCreated'], 20, 2);
    }

    /**
     * Triggered when a new booking is created.
     *
     * @param int                  $bookingId
     * @param array<string, mixed> $bookingData
     * @return void
     */
    public function onBookingCreated(int $bookingId, array $bookingData): void
    {
        $this->dispatch('booking.created', [
            'booking_id'   => $bookingId,
            'booking_code' => $bookingData['booking_code'] ?? '',
            'customer'     => [
                'name'  => $bookingData['customer_name'] ?? '',
                'email' => $bookingData['customer_email'] ?? '',
                'phone' => $bookingData['customer_phone'] ?? '',
            ],
            'item'         => [
                'id'    => $bookingData['item_id'] ?? 0,
                'type'  => $bookingData['item_type'] ?? 'tour',
                'title' => $bookingData['item_title'] ?? '',
            ],
            'dates'        => [
                'check_in'  => $bookingData['check_in'] ?? '',
                'check_out' => $bookingData['check_out'] ?? null,
            ],
            'financials'   => [
                'total_amount'   => $bookingData['raw_total'] ?? 0.0,
                'currency'       => $bookingData['currency'] ?? 'USD',
                'payment_status' => $bookingData['payment_status'] ?? 'pending',
                'booking_status' => $bookingData['booking_status'] ?? 'pending',
            ],
        ]);
    }

    /**
     * Triggered when a booking status changes.
     *
     * @param int    $bookingId
     * @param string $oldStatus
     * @param string $newStatus
     * @return void
     */
    public function onBookingStatusChanged(int $bookingId, string $oldStatus, string $newStatus): void
    {
        $booking = function_exists('tourivo_get_booking') ? tourivo_get_booking($bookingId) : null;

        $this->dispatch('booking.status_changed', [
            'booking_id'   => $bookingId,
            'booking_code' => $booking ? $booking->booking_code : '',
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'customer'     => [
                'name'  => $booking ? $booking->customer_name : '',
                'email' => $booking ? $booking->customer_email : '',
            ],
            'total_amount' => $booking ? (float) $booking->total_amount : 0.0,
            'currency'     => $booking ? $booking->currency : 'USD',
        ]);
    }

    /**
     * Triggered when a customer inquiry is received.
     *
     * @param int                  $inquiryId
     * @param array<string, mixed> $inquiryData
     * @return void
     */
    public function onInquiryCreated(int $inquiryId, array $inquiryData): void
    {
        $this->dispatch('inquiry.created', [
            'inquiry_id' => $inquiryId,
            'name'       => $inquiryData['name'] ?? '',
            'email'      => $inquiryData['email'] ?? '',
            'phone'      => $inquiryData['phone'] ?? '',
            'item_id'    => $inquiryData['item_id'] ?? 0,
            'message'    => $inquiryData['message'] ?? '',
        ]);
    }

    /**
     * Dispatch an outbound webhook event.
     *
     * @param string               $event Event name (e.g. 'booking.created').
     * @param array<string, mixed> $data  Event payload data.
     * @return bool True if dispatched or false if skipped/disabled.
     */
    public function dispatch(string $event, array $data): bool
    {
        $webhookUrl = trim((string) Config::get('webhook_url', ''));
        if (empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        $enabledEvents = (array) Config::get('webhook_events', [
            'booking.created',
            'booking.status_changed',
            'inquiry.created',
        ]);

        if (!in_array($event, $enabledEvents, true)) {
            return false;
        }

        $payload = [
            'event'     => $event,
            'timestamp' => gmdate('c'),
            'source'    => esc_url_raw(home_url()),
            'version'   => TOURIVO_VERSION,
            'data'      => $data,
        ];

        $payload = (array) apply_filters('tourivo/webhook_payload', $payload, $event);

        $jsonPayload = wp_json_encode($payload);
        if ($jsonPayload === false) {
            return false;
        }

        $secret = trim((string) Config::get('webhook_secret', ''));
        $signature = !empty($secret) ? hash_hmac('sha256', $jsonPayload, $secret) : '';

        $headers = [
            'Content-Type'        => 'application/json; charset=utf-8',
            'X-Tourivo-Event'     => $event,
            'User-Agent'          => 'Tourivo-Webhook/' . TOURIVO_VERSION,
        ];

        if (!empty($signature)) {
            $headers['X-Tourivo-Signature'] = $signature;
        }

        // Non-blocking async POST for seamless traveler checkout speed
        wp_remote_post($webhookUrl, [
            'timeout'     => 5,
            'blocking'    => false,
            'headers'     => $headers,
            'body'        => $jsonPayload,
            'data_format' => 'body',
        ]);

        return true;
    }

    /**
     * Send a synchronous test webhook for validation in admin settings.
     *
     * @param string $url
     * @param string $secret
     * @return array{success: bool, status_code?: int, message: string}
     */
    public function sendTestWebhook(string $url, string $secret = ''): array
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => __('Invalid webhook URL format.', 'tourivo')];
        }

        $testPayload = [
            'event'     => 'test.ping',
            'timestamp' => gmdate('c'),
            'source'    => esc_url_raw(home_url()),
            'version'   => TOURIVO_VERSION,
            'data'      => [
                'message' => __('This is a test webhook payload from Tourivo.', 'tourivo'),
                'site'    => get_bloginfo('name'),
            ],
        ];

        $json = wp_json_encode($testPayload);
        $signature = !empty($secret) ? hash_hmac('sha256', (string) $json, $secret) : '';

        $headers = [
            'Content-Type'    => 'application/json; charset=utf-8',
            'X-Tourivo-Event' => 'test.ping',
            'User-Agent'      => 'Tourivo-Webhook/' . TOURIVO_VERSION,
        ];

        if (!empty($signature)) {
            $headers['X-Tourivo-Signature'] = $signature;
        }

        $response = wp_remote_post($url, [
            'timeout'     => 10,
            'blocking'    => true,
            'headers'     => $headers,
            'body'        => $json,
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return [
            'success'     => $code >= 200 && $code < 300,
            'status_code' => $code,
            /* translators: %d: HTTP Status Code */
            'message'     => sprintf(__('Endpoint responded with HTTP status %d.', 'tourivo'), $code),
        ];
    }
}
