<?php

declare(strict_types=1);

namespace Tourivo\Controllers\Api;

use Tourivo\Common\Abstracts\Controller;
use Tourivo\Common\Container;
use Tourivo\Services\PricingService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PricingController
 *
 * REST API controller for dynamic quote calculations, child pricing and tax computations.
 *
 * @package Tourivo\Controllers\Api
 */
class PricingController extends Controller
{
    /**
     * Pricing service.
     *
     * @var PricingService
     */
    protected PricingService $pricingService;

    /**
     * PricingController constructor.
     *
     * @param Container      $container
     * @param PricingService $pricingService
     */
    public function __construct(Container $container, PricingService $pricingService)
    {
        parent::__construct($container);
        $this->pricingService = $pricingService;
    }

    /**
     * Get real-time price quote.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function quote(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $itemId   = (int) $request->get_param('item_id');
        $itemType = sanitize_text_field((string) ($request->get_param('item_type') ?: 'tour'));
        $checkIn  = sanitize_text_field((string) $request->get_param('check_in'));
        $checkOut = sanitize_text_field((string) $request->get_param('check_out'));
        $timeSlot = sanitize_text_field((string) ($request->get_param('time_slot') ?: 'all_day'));
        $adults   = max(0, min(100, (int) ($request->get_param('adults') ?: 1)));
        $children = max(0, min(100, (int) ($request->get_param('children') ?: 0)));
        $infants  = max(0, min(100, (int) ($request->get_param('infants') ?: 0)));
        $rooms    = max(1, min(50, (int) ($request->get_param('rooms') ?: 1)));

        if ($itemId <= 0 || empty($checkIn) || get_post_status($itemId) !== 'publish') {
            return new WP_Error('invalid_params', __('Valid published Item ID and check_in date are required.', 'tourivo'), ['status' => 400]);
        }

        $result = $this->pricingService->quote([
            'item_id'   => $itemId,
            'item_type' => $itemType,
            'check_in'  => $checkIn,
            'check_out' => !empty($checkOut) ? $checkOut : null,
            'time_slot' => $timeSlot,
            'adults'    => $adults,
            'children'  => $children,
            'infants'   => $infants,
            'rooms'     => $rooms,
        ]);

        if (!$result['success']) {
            return new WP_Error('quote_error', $result['message'] ?? __('Failed to compute pricing quote.', 'tourivo'), ['status' => 400]);
        }

        return new WP_REST_Response($result, 200);
    }
}
