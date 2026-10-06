<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Config\Config;
use Tourivo\Models\Room;
use Tourivo\Models\Tour;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PricingService
 *
 * Central pricing engine for Tourivo. Calculates date-based prices, child/infant rates,
 * Pro discounts/filters, taxes (inclusive/exclusive), and formatted quote breakdowns.
 *
 * @package Tourivo\Services
 */
class PricingService
{
    /**
     * Inventory service.
     *
     * @var InventoryService
     */
    protected InventoryService $inventoryService;

    /**
     * PricingService constructor.
     *
     * @param InventoryService $inventoryService
     */
    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Calculate an authoritative price quote for a tour or room booking.
     *
     * @param array<string, mixed> $input
     * @return array{
     *     success: bool,
     *     message?: string,
     *     item_id?: int,
     *     item_type?: string,
     *     subtotal?: float,
     *     discount?: float,
     *     tax?: float,
     *     tax_rate?: float,
     *     tax_label?: string,
     *     tax_mode?: string,
     *     total?: float,
     *     currency?: string,
     *     currency_symbol?: string,
     *     nights?: int,
     *     unit_price?: float,
     *     lines?: array<int, array{title: string, quantity: int, rate: float, total: float}>,
     *     breakdown?: array<string, mixed>
     * }
     */
    public function quote(array $input): array
    {
        $itemId   = (int) ($input['item_id'] ?? 0);
        $rawType  = sanitize_text_field((string) ($input['item_type'] ?? 'tour'));
        $itemType = ($rawType === 'hotel_room' || $rawType === 'room') ? 'room' : 'tour';
        $checkIn  = sanitize_text_field((string) ($input['check_in'] ?? ''));
        $checkOut = sanitize_text_field((string) ($input['check_out'] ?? ''));
        $timeSlot = InventoryService::normalizeTimeSlot((string) ($input['time_slot'] ?? 'all_day'));

        $adults   = max(0, (int) ($input['adults'] ?? 1));
        $children = max(0, (int) ($input['children'] ?? 0));
        $infants  = max(0, (int) ($input['infants'] ?? 0));
        $rooms    = max(1, (int) ($input['rooms'] ?? 1));

        if ($itemId <= 0 || empty($checkIn)) {
            return [
                'success' => false,
                'message' => __('Please provide a valid item ID and check-in date.', 'tourivo'),
            ];
        }

        $decimals       = (int) Config::get('number_of_decimals', 2);
        $currency       = (string) apply_filters('tourivo/currency_code', Config::get('currency', 'USD'));
        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', Config::get('currency_symbol', '$'));

        $rawSubtotal = 0.0;
        $nights      = 1;
        $lines       = [];
        $avgUnitPrice = 0.0;
        $totalGuests = $adults + $children + $infants;

        if ($itemType === 'tour') {
            $tour = new Tour($itemId);
            if (!$tour->getId()) {
                return [
                    'success' => false,
                    'message' => __('Selected tour could not be found.', 'tourivo'),
                ];
            }

            // Validate min guests
            $minGuests = $tour->getMinGuests();
            $payingGuests = $adults + $children;
            if ($minGuests > 1 && $payingGuests < $minGuests) {
                return [
                    'success' => false,
                    /* translators: %d: Minimum guests */
                    'message' => sprintf(__('This tour requires a minimum of %d guests.', 'tourivo'), $minGuests),
                ];
            }

            // Validate max guests
            $maxGuests = $tour->getMaxGuests();
            $infantsUseCapacity = (bool) apply_filters('tourivo/infants_use_capacity', false, $itemId, $itemType, $input);
            $capacityGuests = $payingGuests + ($infantsUseCapacity ? $infants : 0);

            if ($maxGuests > 0 && $capacityGuests > $maxGuests) {
                return [
                    'success' => false,
                    /* translators: %d: Maximum guests */
                    'message' => sprintf(__('This tour allows a maximum of %d guests per booking.', 'tourivo'), $maxGuests),
                ];
            }

            // Daily adult rate (checks price override first, then active catalog price)
            $adultDailyRate = $this->inventoryService->getUnitPrice($itemId, 'tour', $checkIn, $timeSlot);
            if ($adultDailyRate <= 0) {
                $adultDailyRate = $tour->getActivePrice();
            }

            // Adult subtotal
            $adultSubtotal = $adultDailyRate * $adults;
            if ($adults > 0) {
                $lines[] = [
                    'title'    => __('Adults', 'tourivo'),
                    'quantity' => $adults,
                    'rate'     => $adultDailyRate,
                    'total'    => $adultSubtotal,
                ];
            }

            // Child pricing
            $childPriceType  = $tour->getChildPriceType();
            $childPriceValue = $tour->getChildPriceValue();
            $childDailyRate  = match ($childPriceType) {
                'free'    => 0.0,
                'percent' => $adultDailyRate * ($childPriceValue / 100.0),
                'fixed'   => $childPriceValue,
                default   => $adultDailyRate, // 'full'
            };
            $childSubtotal = $childDailyRate * $children;

            if ($children > 0) {
                $childLabel = $tour->getChildAgeLabel();
                $lines[] = [
                    'title'    => !empty($childLabel) ? sprintf(__('Children (%s)', 'tourivo'), $childLabel) : __('Children', 'tourivo'),
                    'quantity' => $children,
                    'rate'     => $childDailyRate,
                    'total'    => $childSubtotal,
                ];
            }

            // Infant pricing (default free)
            $infantsFree     = $tour->isInfantsFree();
            $infantDailyRate = $infantsFree ? 0.0 : $adultDailyRate;
            $infantSubtotal  = $infantDailyRate * $infants;

            if ($infants > 0) {
                $lines[] = [
                    'title'    => __('Infants', 'tourivo'),
                    'quantity' => $infants,
                    'rate'     => $infantDailyRate,
                    'total'    => $infantSubtotal,
                ];
            }

            $rawSubtotal = $adultSubtotal + $childSubtotal + $infantSubtotal;
            $avgUnitPrice = $payingGuests > 0 ? ($rawSubtotal / $payingGuests) : $adultDailyRate;
        } else {
            // Room
            if (empty($checkOut)) {
                return [
                    'success' => false,
                    'message' => __('Please select a valid check-out date for room reservations.', 'tourivo'),
                ];
            }

            $room = new Room($itemId);
            if (!$room->getId()) {
                return [
                    'success' => false,
                    'message' => __('Selected room could not be found.', 'tourivo'),
                ];
            }

            $dates = $this->inventoryService->generateDateList($checkIn, $checkOut, false);
            $nights = max(1, count($dates));

            // Sum daily rates across date range
            $sumDailyRates = 0.0;
            $nightlyRates = [];
            foreach ($dates as $date) {
                $rate = $this->inventoryService->getUnitPrice($itemId, 'room', $date, $timeSlot);
                if ($rate <= 0) {
                    $rate = $room->getNightlyPrice();
                }
                $nightlyRates[$date] = $rate;
                $sumDailyRates += $rate;
            }

            $rawSubtotal  = $sumDailyRates * $rooms;
            $avgUnitPrice = $nights > 0 ? ($sumDailyRates / $nights) : $room->getNightlyPrice();

            $lines[] = [
                'title'    => sprintf(
                    /* translators: 1: Number of rooms, 2: Number of nights */
                    __('%1$d Room(s) × %2$d Night(s)', 'tourivo'),
                    $rooms,
                    $nights
                ),
                'quantity' => $rooms * $nights,
                'rate'     => $avgUnitPrice,
                'total'    => $rawSubtotal,
            ];
        }

        // Apply Pro / 3rd-party Pricing Pipeline Filter
        $pricingData = apply_filters('tourivo/booking_price', [
            'unit_price'  => $avgUnitPrice,
            'total_price' => $rawSubtotal,
            'base_price'  => $rawSubtotal,
            'currency'    => $currency,
        ], [
            'item_id'   => $itemId,
            'item_type' => $itemType,
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
            'adults'    => $adults,
            'children'  => $children,
            'infants'   => $infants,
            'guests'    => $totalGuests,
            'rooms'     => $rooms,
            'nights'    => $nights,
            'data'      => $input,
        ]);

        $subtotal = (float) ($pricingData['total_price'] ?? $rawSubtotal);
        $discount = max(0.0, $rawSubtotal - $subtotal);

        // Tax Calculations
        $taxEnabled   = (bool) Config::get('tax_enabled', false);
        $taxLabel     = (string) Config::get('tax_label', __('Tax', 'tourivo'));
        $taxRate      = max(0.0, (float) Config::get('tax_rate', 0.0));
        $taxMode      = (string) Config::get('tax_mode', 'exclusive');
        $taxAppliesTo = (string) Config::get('tax_applies_to', 'all');

        $taxApplies = false;
        if ($taxEnabled && $taxRate > 0) {
            if ($taxAppliesTo === 'all') {
                $taxApplies = true;
            } elseif ($taxAppliesTo === 'tours' && $itemType === 'tour') {
                $taxApplies = true;
            } elseif ($taxAppliesTo === 'rooms' && $itemType === 'room') {
                $taxApplies = true;
            }
        }

        $taxAmount = 0.0;
        $total     = $subtotal;

        if ($taxApplies) {
            if ($taxMode === 'inclusive') {
                // Inclusive: tax is already inside subtotal
                $taxAmount = $subtotal - ($subtotal / (1.0 + ($taxRate / 100.0)));
                $total     = $subtotal;
            } else {
                // Exclusive: tax added on top of subtotal
                $taxAmount = $subtotal * ($taxRate / 100.0);
                $total     = $subtotal + $taxAmount;
            }
        }

        // Precision Rounding
        $subtotal     = round($subtotal, $decimals);
        $discount     = round($discount, $decimals);
        $taxAmount    = round($taxAmount, $decimals);
        // Exclusive tax: the grand total is the sum of the two rounded lines the customer sees, so
        // "subtotal + tax = total" always holds to the cent.
        $total        = ($taxApplies && $taxMode !== 'inclusive')
            ? round($subtotal + $taxAmount, $decimals)
            : round($total, $decimals);
        $avgUnitPrice = round($avgUnitPrice, $decimals);

        return [
            'success'         => true,
            'item_id'         => $itemId,
            'item_type'       => $itemType,
            'subtotal'        => $subtotal,
            'discount'        => $discount,
            'tax'             => $taxAmount,
            'tax_rate'        => $taxRate,
            'tax_label'       => $taxLabel,
            'tax_mode'        => $taxMode,
            'total'           => $total,
            'currency'        => $currency,
            'currency_symbol' => $currencySymbol,
            'nights'          => $nights,
            'unit_price'      => $avgUnitPrice,
            'lines'           => $lines,
            'breakdown'       => [
                'raw_subtotal' => $rawSubtotal,
                'subtotal'     => $subtotal,
                'discount'     => $discount,
                'tax'          => $taxAmount,
                'tax_rate'     => $taxRate,
                'tax_label'    => $taxLabel,
                'tax_mode'     => $taxMode,
                'total'        => $total,
                'lines'        => $lines,
                'nights'       => $nights,
            ],
        ];
    }
}
