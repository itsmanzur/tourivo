<?php

declare(strict_types=1);

namespace Tourivo\Controllers\Api;

use Tourivo\Common\Abstracts\Controller;
use Tourivo\Common\Container;
use Tourivo\Services\InventoryService;
use Tourivo\Support\ClientIp;
use Tourivo\Support\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AvailabilityController
 *
 * REST API controller for real-time item availability checks, calendar grids and temporary checkout holds.
 *
 * @package Tourivo\Controllers\Api
 */
class AvailabilityController extends Controller
{
    /**
     * Maximum simultaneously active checkout holds per client.
     */
    public const MAX_ACTIVE_HOLDS_PER_CLIENT = 3;

    /**
     * Inventory service.
     *
     * @var InventoryService
     */
    protected InventoryService $inventoryService;

    /**
     * AvailabilityController constructor.
     *
     * @param Container        $container
     * @param InventoryService $inventoryService
     */
    public function __construct(Container $container, InventoryService $inventoryService)
    {
        parent::__construct($container);
        $this->inventoryService = $inventoryService;
    }

    /**
     * Check availability for a specific item and date/range.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function check(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $itemId    = (int) $request->get_param('item_id');
        $itemType  = sanitize_text_field((string) ($request->get_param('item_type') ?: 'tour'));
        $startDate = sanitize_text_field((string) $request->get_param('start_date'));
        $endDate   = sanitize_text_field((string) $request->get_param('end_date'));
        $timeSlot  = InventoryService::normalizeTimeSlot((string) $request->get_param('time_slot'));
        $guests    = max(1, min(50, (int) ($request->get_param('guests') ?: 1)));
        $rooms     = max(1, min(25, (int) ($request->get_param('rooms') ?: 1)));

        if ($itemId <= 0 || empty($startDate) || get_post_status($itemId) !== 'publish') {
            return new WP_Error('invalid_params', __('Valid published Item ID and start_date are required.', 'tourivo'), ['status' => 400]);
        }

        $requestedCount = ($itemType === 'hotel_room' || $itemType === 'room') ? $rooms : $guests;

        $result = $this->inventoryService->checkAvailability(
            $itemId,
            $itemType,
            $startDate,
            !empty($endDate) ? $endDate : null,
            $timeSlot,
            $requestedCount
        );

        return new WP_REST_Response($result, 200);
    }

    /**
     * Get whole month calendar availability grid.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function calendar(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $itemId   = (int) $request->get_param('item_id');
        $itemType = sanitize_text_field((string) ($request->get_param('item_type') ?: 'tour'));
        $currYear = (int) wp_date('Y');
        $year     = max($currYear - 1, min($currYear + 5, (int) ($request->get_param('year') ?: $currYear)));
        $month    = max(1, min(12, (int) ($request->get_param('month') ?: (int) wp_date('n'))));
        $timeSlot = InventoryService::normalizeTimeSlot((string) $request->get_param('time_slot'));

        if ($itemId <= 0 || get_post_status($itemId) !== 'publish') {
            return new WP_Error('invalid_params', __('Invalid parameters for calendar query.', 'tourivo'), ['status' => 400]);
        }

        $calendar = $this->inventoryService->getCalendarAvailability($itemId, $itemType, $year, $month, $timeSlot);

        return new WP_REST_Response([
            'year'     => $year,
            'month'    => $month,
            'calendar' => $calendar,
        ], 200);
    }

    /**
     * Reserve temporary hold during checkout with rate limit protections.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function hold(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Rate Limiting (Max 10 holds per 10 minutes per IP)
        $ip = ClientIp::get();
        if (!RateLimiter::hit('trv_rl_hold_' . md5($ip), 10, 600)) {
            return new WP_Error(
                'too_many_holds',
                __('Too many hold requests initiated. Please complete your existing reservation.', 'tourivo'),
                ['status' => 429]
            );
        }

        // Cap concurrently active holds per client so one visitor cannot lock the whole calendar.
        $ownerKey  = md5($ip);
        $activeKey = 'trv_hold_active_' . $ownerKey;
        $stored    = get_transient($activeKey);
        $active    = array_values(array_filter(
            is_array($stored) ? $stored : [],
            fn ($token): bool => $this->inventoryService->getHold((string) $token) !== null
        ));

        if (count($active) >= self::MAX_ACTIVE_HOLDS_PER_CLIENT) {
            return new WP_Error(
                'too_many_holds',
                __('Too many hold requests initiated. Please complete your existing reservation.', 'tourivo'),
                ['status' => 429]
            );
        }

        $itemId    = (int) $request->get_param('item_id');
        $itemType  = sanitize_text_field((string) ($request->get_param('item_type') ?: 'tour'));
        $startDate = sanitize_text_field((string) $request->get_param('start_date'));
        $endDate   = sanitize_text_field((string) $request->get_param('end_date'));
        $timeSlot  = InventoryService::normalizeTimeSlot((string) $request->get_param('time_slot'));
        $count     = max(1, min(50, (int) ($request->get_param('count') ?: 1)));

        if ($itemId <= 0 || empty($startDate) || get_post_status($itemId) !== 'publish') {
            return new WP_Error('invalid_params', __('Valid published Item ID and start_date are required.', 'tourivo'), ['status' => 400]);
        }

        $holdToken = $this->inventoryService->holdInventory(
            $itemId,
            $itemType,
            $startDate,
            !empty($endDate) ? $endDate : null,
            $timeSlot,
            $count,
            InventoryService::HOLD_TTL_MINUTES,
            true,
            $ownerKey
        );

        if (!$holdToken) {
            return new WP_Error(
                'hold_failed',
                __('Could not reserve selected dates or quantity. Selected spots may already be booked.', 'tourivo'),
                ['status' => 409]
            );
        }

        $active[] = $holdToken;
        set_transient($activeKey, $active, (InventoryService::HOLD_TTL_MINUTES + 1) * 60);

        return new WP_REST_Response([
            'success'    => true,
            'hold_token' => $holdToken,
            'expires_in' => InventoryService::HOLD_TTL_MINUTES * 60,
        ], 200);
    }
}
