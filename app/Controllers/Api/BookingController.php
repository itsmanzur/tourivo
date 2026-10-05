<?php

declare(strict_types=1);

namespace Tourivo\Controllers\Api;

use Tourivo\Common\Abstracts\Controller;
use Tourivo\Common\Container;
use Tourivo\Services\BookingService;
use Tourivo\Support\ClientIp;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookingController
 *
 * REST API controller for processing direct bookings and AJAX submissions.
 *
 * @package Tourivo\Controllers\Api
 */
class BookingController extends Controller
{
    /**
     * Booking Service.
     *
     * @var BookingService
     */
    protected BookingService $bookingService;

    /**
     * BookingController constructor.
     *
     * @param Container      $container
     * @param BookingService $bookingService
     */
    public function __construct(Container $container, BookingService $bookingService)
    {
        parent::__construct($container);
        $this->bookingService = $bookingService;
    }

    /**
     * Handle direct checkout submission with anti-spam & rate limiting defenses.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function directCheckout(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rawParams = $request->get_json_params() ?: $request->get_params();

        // 1. Honeypot check
        if (!empty($rawParams['tourivo_hp_check'])) {
            return new WP_Error('spam_detected', __('Submission blocked.', 'tourivo'), ['status' => 400]);
        }

        // 2. IP-based Rate Limiting (5 requests per 10 minutes)
        $ip = ClientIp::get();
        $rateLimitKey = 'trv_rl_booking_' . md5($ip);
        $attempts = (int) get_transient($rateLimitKey);

        if ($attempts >= 5) {
            return new WP_Error(
                'too_many_requests',
                __('Too many booking requests submitted. Please wait a few minutes before trying again.', 'tourivo'),
                ['status' => 429]
            );
        }

        set_transient($rateLimitKey, $attempts + 1, 600); // 10 minutes

        // 3. Strict Whitelist of incoming public parameters (strip any injected privileged fields)
        $adults   = max(1, (int) ($rawParams['adults'] ?? 1));
        $children = max(0, (int) ($rawParams['children'] ?? 0));
        $rooms    = max(1, min(25, (int) ($rawParams['rooms'] ?? 1)));

        if (($adults + $children) > 50) {
            return new WP_Error('invalid_guest_count', __('For group reservations of more than 50 travelers, please contact our support.', 'tourivo'), ['status' => 400]);
        }

        $cleanParams = [
            'item_id'         => isset($rawParams['item_id']) ? (int) $rawParams['item_id'] : 0,
            'item_type'       => isset($rawParams['item_type']) ? sanitize_text_field((string) $rawParams['item_type']) : 'tour',
            'check_in'        => isset($rawParams['check_in']) ? sanitize_text_field((string) $rawParams['check_in']) : '',
            'check_out'       => !empty($rawParams['check_out']) ? sanitize_text_field((string) $rawParams['check_out']) : null,
            'rooms'           => $rooms,
            'adults'          => $adults,
            'children'        => $children,
            'infants'         => max(0, (int) ($rawParams['infants'] ?? 0)),
            'customer_name'   => isset($rawParams['customer_name']) ? sanitize_text_field((string) $rawParams['customer_name']) : '',
            'customer_email'  => isset($rawParams['customer_email']) ? sanitize_email((string) $rawParams['customer_email']) : '',
            'customer_phone'  => isset($rawParams['customer_phone']) ? sanitize_text_field((string) $rawParams['customer_phone']) : '',
            'customer_notes'  => isset($rawParams['customer_notes']) ? sanitize_textarea_field((string) $rawParams['customer_notes']) : '',
            'billing_address' => isset($rawParams['billing_address']) ? sanitize_textarea_field((string) $rawParams['billing_address']) : '',
            'time_slot'       => !empty($rawParams['time_slot']) ? sanitize_text_field((string) $rawParams['time_slot']) : 'all_day',
            'hold_token'      => !empty($rawParams['hold_token']) ? sanitize_text_field((string) $rawParams['hold_token']) : null,
            'consent'         => !empty($rawParams['consent']) ? 1 : 0,
        ];

        // Public route: $trusted is false
        $result = $this->bookingService->createBooking($cleanParams, false);

        if (!$result['success']) {
            return new WP_Error('booking_failed', $result['message'], ['status' => 400]);
        }

        return new WP_REST_Response($result, 200);
    }
}
