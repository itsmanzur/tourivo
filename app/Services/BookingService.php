<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Exception;
use Tourivo\Config\Config;
use Tourivo\Models\Room;
use Tourivo\Models\Tour;
use Tourivo\Support\ClientIp;
use Tourivo\Support\Money;
use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookingService
 *
 * Central service for creating, managing and processing tour & accommodation bookings.
 *
 * @package Tourivo\Services
 */
class BookingService
{
    /**
     * Database instance.
     *
     * @var wpdb|null
     */
    protected ?wpdb $db = null;

    /**
     * Inventory service.
     *
     * @var InventoryService
     */
    protected InventoryService $inventoryService;

    /**
     * Email service.
     *
     * @var EmailService
     */
    protected EmailService $emailService;

    /**
     * BookingService constructor.
     *
     * @param InventoryService $inventoryService
     * @param EmailService     $emailService
     */
    public function __construct(InventoryService $inventoryService, EmailService $emailService)
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->inventoryService = $inventoryService;
        $this->emailService = $emailService;
    }

    /**
     * Create a direct booking with inventory commit, audit logging, and validation.
     *
     * @param array<string, mixed> $data
     * @param bool                 $trusted When false (public requests), privileged fields are stripped.
     * @return array{success: bool, booking_id?: int, booking_code?: string, message: string}
     */
    public function createBooking(array $data, bool $trusted = false): array
    {
        if (!$this->db) {
            return ['success' => false, 'message' => __('Database connection not available.', 'tourivo')];
        }

        // 1. Validate Item existence and Post Type
        $itemId = (int) ($data['item_id'] ?? 0);
        if ($itemId <= 0 || get_post_status($itemId) !== 'publish') {
            return ['success' => false, 'message' => __('Selected item is invalid or no longer available.', 'tourivo')];
        }

        $postType = get_post_type($itemId);

        if ($postType === 'tourivo_tour') {
            $itemType = 'tour';
        } elseif ($postType === 'tourivo_room') {
            $itemType = 'room';
        } else {
            return ['success' => false, 'message' => __('Invalid booking item type.', 'tourivo')];
        }

        // 2. Extract & Sanitize Input Parameters
        $checkIn       = sanitize_text_field((string) ($data['check_in'] ?? ''));
        $checkOut      = !empty($data['check_out']) ? sanitize_text_field((string) $data['check_out']) : null;
        $timeSlot      = InventoryService::normalizeTimeSlot((string) ($data['time_slot'] ?? 'all_day'));

        $adultsCount   = max(1, (int) ($data['adults'] ?? 1));
        $childrenCount = max(0, (int) ($data['children'] ?? 0));
        $totalGuests   = $adultsCount + $childrenCount;
        $roomsCount    = max(1, (int) ($data['rooms'] ?? 1));
        $holdToken     = !empty($data['hold_token']) ? sanitize_text_field((string) $data['hold_token']) : null;

        $customerName  = sanitize_text_field((string) ($data['customer_name'] ?? ''));
        $customerEmail = sanitize_email((string) ($data['customer_email'] ?? ''));
        $customerPhone = sanitize_text_field((string) ($data['customer_phone'] ?? ''));
        $billingAddr   = sanitize_textarea_field((string) ($data['billing_address'] ?? ''));
        $notes         = sanitize_textarea_field((string) ($data['customer_notes'] ?? ''));

        if (empty($checkIn) || empty($customerName) || empty($customerEmail) || !is_email($customerEmail)) {
            return ['success' => false, 'message' => __('Please provide all required traveler details (Name, Valid Email, Date).', 'tourivo')];
        }

        // 3. Item-Specific Occupancy & Pricing Checks
        $itemTitle = '';
        $unitPrice = 0.0;
        $baseTotal = 0.0;
        $nightsCount = 1;

        if ($itemType === 'tour') {
            $tour = new Tour($itemId);
            $itemTitle = $tour->getTitle();
            $maxGuests = $tour->getMaxGuests();
            if ($maxGuests > 0 && $totalGuests > $maxGuests) {
                return ['success' => false, 'message' => sprintf(__('This tour allows a maximum of %d guests per booking.', 'tourivo'), $maxGuests)];
            }

            $unitPrice = $tour->getActivePrice();
            $baseTotal = $unitPrice * $totalGuests;
            $inventoryQuantity = $totalGuests;
        } else {
            // Room
            if (empty($checkOut)) {
                return ['success' => false, 'message' => __('Please select a valid check-out date for room reservations.', 'tourivo')];
            }

            $room = new Room($itemId);
            $itemTitle = $room->getTitle();
            $hotel = $room->getHotel();
            if ($hotel) {
                $itemTitle = $hotel->getTitle() . ' - ' . $itemTitle;
            }

            // Occupancy validation against total room units booked
            $maxAdultsAllowed   = $room->getMaxAdults() * $roomsCount;
            $maxChildrenAllowed = $room->getMaxChildren() * $roomsCount;
            $maxGuestsAllowed   = $room->getMaxGuests() * $roomsCount;

            if ($maxAdultsAllowed > 0 && $adultsCount > $maxAdultsAllowed) {
                return ['success' => false, 'message' => sprintf(__('Selected %d room(s) can accommodate up to %d adults.', 'tourivo'), $roomsCount, $maxAdultsAllowed)];
            }
            if ($maxChildrenAllowed > 0 && $childrenCount > $maxChildrenAllowed) {
                return ['success' => false, 'message' => sprintf(__('Selected %d room(s) can accommodate up to %d children.', 'tourivo'), $roomsCount, $maxChildrenAllowed)];
            }
            if ($maxGuestsAllowed > 0 && $totalGuests > $maxGuestsAllowed) {
                return ['success' => false, 'message' => sprintf(__('Selected %d room(s) can accommodate up to %d total guests.', 'tourivo'), $roomsCount, $maxGuestsAllowed)];
            }

            $dates = $this->inventoryService->generateDateList($checkIn, $checkOut, false);
            $nightsCount = max(1, count($dates));
            $unitPrice   = $room->getNightlyPrice();
            $baseTotal   = $unitPrice * $roomsCount * $nightsCount;
            $inventoryQuantity = $roomsCount;
        }

        // 4. Check Inventory Availability
        $avail = $this->inventoryService->checkAvailability($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity);
        if (!$avail['available']) {
            return ['success' => false, 'message' => $avail['message']];
        }

        // 5. Pricing Pipeline Filter (Enables Pro Pricing Tiers, Seasonality, Extra Add-ons)
        $defaultCurrency = (string) apply_filters('tourivo/currency_code', Config::get('currency', 'USD'));
        $pricingData = apply_filters('tourivo/booking_price', [
            'unit_price'  => $unitPrice,
            'total_price' => $baseTotal,
            'base_price'  => $baseTotal,
            'currency'    => $defaultCurrency,
        ], [
            'item_id'   => $itemId,
            'item_type' => $itemType,
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
            'adults'    => $adultsCount,
            'children'  => $childrenCount,
            'guests'    => $totalGuests,
            'rooms'     => $roomsCount,
            'nights'    => $nightsCount,
            'data'      => $data,
        ]);

        $totalPrice = (float) ($pricingData['total_price'] ?? $baseTotal);

        // Allow explicit manual booking total override ONLY for trusted callers or authorized admins
        if ($trusted || current_user_can('manage_tourivo_bookings')) {
            if (isset($data['total_amount']) && is_numeric($data['total_amount']) && (float) $data['total_amount'] >= 0) {
                $totalPrice = (float) $data['total_amount'];
            }
        }

        if ($totalPrice <= 0) {
            return ['success' => false, 'message' => __('Invalid total price calculated for this booking.', 'tourivo')];
        }

        // 6. Resolve Customer ID and Statuses
        $customerId = 0;
        if ($trusted && isset($data['customer_id'])) {
            $customerId = (int) $data['customer_id'];
        }

        if ($customerId <= 0) {
            $foundUser = get_user_by('email', $customerEmail);
            if ($foundUser) {
                $customerId = (int) $foundUser->ID;
            } elseif (is_user_logged_in() && !current_user_can('manage_tourivo_bookings')) {
                $customerId = get_current_user_id();
            }
        }

        if ($trusted) {
            $bookingStatus = in_array($data['booking_status'] ?? '', ['pending', 'confirmed', 'completed', 'cancelled', 'on_hold'], true)
                ? (string) $data['booking_status']
                : (string) apply_filters('tourivo/default_booking_status', 'pending');

            $paymentStatus = in_array($data['payment_status'] ?? '', ['pending', 'paid', 'partially_paid', 'refunded', 'failed'], true)
                ? (string) $data['payment_status']
                : 'pending';

            $paymentMethod = sanitize_text_field((string) ($data['payment_method'] ?? 'offline'));
        } else {
            $bookingStatus = (string) apply_filters('tourivo/default_booking_status', 'pending');
            $paymentStatus = 'pending';
            $paymentMethod = sanitize_text_field((string) ($data['payment_method'] ?? 'offline'));
        }

        // 7. Generate unique booking code
        $bookingCode = 'TRV-' . gmdate('Y') . '-' . strtoupper(wp_generate_password(6, false));

        $bookingsTable = $this->db->prefix . 'tourivo_bookings';
        $itemsTable    = $this->db->prefix . 'tourivo_booking_items';
        $logsTable     = $this->db->prefix . 'tourivo_logs';
        $ipAddress     = ClientIp::get();
        $nowGmt        = gmdate('Y-m-d H:i:s');

        // 8. Step 1: Commit Inventory First (atomic repository transaction)
        $committed = $this->inventoryService->commitBooking($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity, $holdToken);
        if (!$committed) {
            return ['success' => false, 'message' => __('Failed to commit inventory. Selected slots are no longer available.', 'tourivo')];
        }

        // 8. Step 2: Insert Booking Record
        try {
            $inserted = $this->db->insert($bookingsTable, [
                'booking_code'    => $bookingCode,
                'customer_id'     => $customerId,
                'customer_name'   => $customerName,
                'customer_email'  => $customerEmail,
                'customer_phone'  => $customerPhone,
                'billing_address' => $billingAddr,
                'total_amount'    => $totalPrice,
                'tax_amount'      => 0.00,
                'discount_amount' => 0.00,
                'paid_amount'     => ($paymentStatus === 'paid') ? $totalPrice : 0.00,
                'due_amount'      => ($paymentStatus === 'paid') ? 0.00 : $totalPrice,
                'currency'        => $defaultCurrency,
                'payment_method'  => $paymentMethod,
                'payment_status'  => $paymentStatus,
                'booking_status'  => $bookingStatus,
                'customer_notes'  => $notes,
                'ip_address'      => $ipAddress,
                'created_at'      => $nowGmt,
                'updated_at'      => $nowGmt,
            ], [
                '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]);

            if (!$inserted) {
                // Rollback inventory capacity
                $this->inventoryService->releaseBookingInventory($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity);
                return ['success' => false, 'message' => __('Could not save booking record to database.', 'tourivo')];
            }

            $bookingId = (int) $this->db->insert_id;

            // 8. Step 3: Insert Line Item (without wp_slash on wpdb->insert)
            $itemInserted = $this->db->insert($itemsTable, [
                'booking_id'        => $bookingId,
                'item_id'           => $itemId,
                'item_type'         => $itemType,
                'item_title'        => $itemTitle,
                'check_in'          => $checkIn . ' 00:00:00',
                'check_out'         => !empty($checkOut) ? ($checkOut . ' 00:00:00') : null,
                'time_slot'         => $timeSlot,
                'adults_count'      => $adultsCount,
                'children_count'    => $childrenCount,
                'quantity'          => ($itemType === 'room') ? $roomsCount : $totalGuests,
                'unit_price'        => $unitPrice,
                'total_price'       => $totalPrice,
                'pricing_breakdown' => wp_json_encode($avail, JSON_UNESCAPED_UNICODE),
            ], [
                '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%s'
            ]);

            if (!$itemInserted) {
                // Rollback booking and inventory
                $this->db->delete($bookingsTable, ['id' => $bookingId], ['%d']);
                $this->inventoryService->releaseBookingInventory($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity);
                return ['success' => false, 'message' => __('Could not save booking item line to database.', 'tourivo')];
            }

            // 8. Step 4: Write to Audit Logs (without wp_slash on wpdb->insert)
            $this->db->insert($logsTable, [
                'booking_id' => $bookingId,
                'action'     => 'booking_created',
                'user_id'    => get_current_user_id() ?: $customerId,
                'details'    => wp_json_encode([
                    'method' => $paymentMethod,
                    'amount' => $totalPrice,
                    'items'  => [$itemTitle],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $nowGmt,
            ], ['%d', '%s', '%d', '%s', '%s']);
        } catch (Exception $e) {
            $this->inventoryService->releaseBookingInventory($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity);
            return ['success' => false, 'message' => __('An error occurred while creating booking: ', 'tourivo') . $e->getMessage()];
        }

        // 9. Fire Booking Created Action & Trigger Confirmation Notification
        $bookingData = [
            'id'              => $bookingId,
            'booking_code'    => $bookingCode,
            'customer_name'   => $customerName,
            'customer_email'  => $customerEmail,
            'customer_phone'  => $customerPhone,
            'item_id'         => $itemId,
            'item_type'       => $itemType,
            'item_title'      => $itemTitle,
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'adults'          => $adultsCount,
            'children'        => $childrenCount,
            'rooms'           => $roomsCount,
            'total_amount'    => Money::format($totalPrice),
            'raw_total'       => $totalPrice,
            'currency'        => $defaultCurrency,
            'payment_status'  => $paymentStatus,
            'booking_status'  => $bookingStatus,
        ];

        do_action('tourivo/booking_created', $bookingId, $bookingData);
        $this->emailService->sendBookingConfirmation($bookingData);

        return [
            'success'      => true,
            'booking_id'   => $bookingId,
            'booking_code' => $bookingCode,
            'message'      => sprintf(__('Booking created successfully! Your booking code is #%s.', 'tourivo'), $bookingCode),
        ];
    }
}
