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
     * Register lifecycle event hooks and delivery action.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('tourivo/booking_created', [$this, 'onBookingCreated'], 20, 2);
        add_action('tourivo/booking_status_changed', [$this, 'onBookingStatusChanged'], 20, 3);
        add_action('tourivo/inquiry_created', [$this, 'onInquiryCreated'], 20, 2);
        add_action('tourivo_process_webhook_delivery', [$this, 'dispatch'], 10, 3);
    }

    /**
     * Validate webhook URL to ensure HTTPS scheme (HTTP allowed only via filter).
     *
     * @param string $url
     * @return bool
     */
    public function isValidUrl(string $url): bool
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = (string) wp_parse_url($url, PHP_URL_SCHEME);
        $allowHttp = (bool) apply_filters('tourivo/webhook_allow_http', false, $url);

        if ($scheme === 'https' || ($scheme === 'http' && $allowHttp)) {
            return true;
        }

        return false;
    }

    /**
     * Enqueue asynchronous webhook delivery via Action Scheduler or WP-Cron.
     *
     * Guarantees non-blocking execution so checkout / booking requests return within <500ms
     * even if external webhook endpoints (Zapier, n8n, Make) are slow or offline.
     *
     * @param string               $event   Event name (e.g. 'booking.created').
     * @param array<string, mixed> $data    Event payload data.
     * @param int                  $attempt Delivery attempt count.
     * @return void
     */
    public function enqueue(string $event, array $data, int $attempt = 1): void
    {
        $webhookUrl = trim((string) Config::get('webhook_url', ''));
        if (!$this->isValidUrl($webhookUrl)) {
            return;
        }

        $enabledEvents = (array) Config::get('webhook_events', [
            'booking.created',
            'booking.status_changed',
            'inquiry.created',
        ]);

        if (!in_array($event, $enabledEvents, true)) {
            return;
        }

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('tourivo_process_webhook_delivery', [$event, $data, $attempt], 'tourivo-webhooks');
        } else {
            wp_schedule_single_event(time(), 'tourivo_process_webhook_delivery', [$event, $data, $attempt]);
            if (function_exists('spawn_cron')) {
                spawn_cron();
            }
        }
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
        $includePhone = (bool) Config::get('webhook_include_phone', true);
        $customer = [
            'name'  => $bookingData['customer_name'] ?? '',
            'email' => $bookingData['customer_email'] ?? '',
        ];
        if ($includePhone) {
            $customer['phone'] = $bookingData['customer_phone'] ?? '';
        }

        $this->enqueue('booking.created', [
            'booking_id'   => $bookingId,
            'booking_code' => $bookingData['booking_code'] ?? '',
            'customer'     => $customer,
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

        $this->enqueue('booking.status_changed', [
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
        $includePhone   = (bool) Config::get('webhook_include_phone', true);
        $includeMessage = (bool) Config::get('webhook_include_message', true);

        $payloadData = [
            'inquiry_id' => $inquiryId,
            'name'       => $inquiryData['name'] ?? '',
            'email'      => $inquiryData['email'] ?? '',
            'item_id'    => $inquiryData['item_id'] ?? 0,
        ];

        if ($includePhone) {
            $payloadData['phone'] = $inquiryData['phone'] ?? '';
        }

        if ($includeMessage) {
            $payloadData['message'] = $inquiryData['message'] ?? '';
        }

        $this->enqueue('inquiry.created', $payloadData);
    }

    /**
     * Dispatch an outbound webhook event worker with logging and exponential retry.
     *
     * @param string               $event   Event name (e.g. 'booking.created').
     * @param array<string, mixed> $data    Event payload data.
     * @param int                  $attempt Current delivery attempt counter (1-3).
     * @return bool True if successfully delivered, false otherwise.
     */
    public function dispatch(string $event, array $data, int $attempt = 1): bool
    {
        $webhookUrl = trim((string) Config::get('webhook_url', ''));
        if (!$this->isValidUrl($webhookUrl)) {
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
            'Content-Type'    => 'application/json; charset=utf-8',
            'X-Tourivo-Event' => $event,
            'User-Agent'      => 'Tourivo-Webhook/' . TOURIVO_VERSION,
        ];

        if (!empty($signature)) {
            $headers['X-Tourivo-Signature'] = $signature;
        }

        $bookingId = (int) ($data['booking_id'] ?? 0);

        // Safe remote post for security
        $response = wp_safe_remote_post($webhookUrl, [
            'timeout'     => 10,
            'blocking'    => true,
            'headers'     => $headers,
            'body'        => $jsonPayload,
            'data_format' => 'body',
        ]);

        $isSuccess = false;
        $statusCode = 0;
        $errorMsg   = '';

        if (is_wp_error($response)) {
            $errorMsg = $response->get_error_message();
        } else {
            $statusCode = (int) wp_remote_retrieve_response_code($response);
            $isSuccess  = ($statusCode >= 200 && $statusCode < 300);
            if (!$isSuccess) {
                $errorMsg = 'HTTP ' . $statusCode;
            }
        }

        // Log dispatch outcome in LogService
        if ($bookingId > 0) {
            if ($isSuccess) {
                LogService::log(
                    $bookingId,
                    'webhook_dispatched',
                    sprintf(
                        /* translators: 1: Webhook event name, 2: HTTP status code, 3: Delivery attempt number */
                        __('Webhook [%1$s] delivered successfully (HTTP %2$d, attempt %3$d).', 'tourivo'),
                        $event,
                        $statusCode,
                        $attempt
                    )
                );
            } else {
                LogService::log(
                    $bookingId,
                    'webhook_failed',
                    sprintf(
                        /* translators: 1: Webhook event name, 2: Error message, 3: Delivery attempt number */
                        __('Webhook [%1$s] delivery failed (Reason: %2$s, attempt %3$d).', 'tourivo'),
                        $event,
                        $errorMsg,
                        $attempt
                    )
                );
            }
        }

        // Handle exponential retry up to 3 attempts (Positional array across AS & WP-Cron)
        if (!$isSuccess && $attempt < 3) {
            $nextAttempt = $attempt + 1;
            // Delay: 120s for attempt 2, 300s for attempt 3
            $delay = ($nextAttempt === 2) ? 120 : 300;

            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + $delay, 'tourivo_process_webhook_delivery', [$event, $data, $nextAttempt], 'tourivo-webhooks');
            } else {
                wp_schedule_single_event(time() + $delay, 'tourivo_process_webhook_delivery', [
                    $event,
                    $data,
                    $nextAttempt,
                ]);
            }
        }

        return $isSuccess;
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
        if (!$this->isValidUrl($url)) {
            return ['success' => false, 'message' => __('Invalid webhook URL format. HTTPS is required.', 'tourivo')];
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

        $response = wp_safe_remote_post($url, [
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

