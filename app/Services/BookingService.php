<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Exception;
use Tourivo\Config\Config;
use Tourivo\Models\Room;
use Tourivo\Models\Tour;
use Tourivo\Shortcodes\ThankYouShortcode;
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
     * Pricing service.
     *
     * @var PricingService
     */
    protected PricingService $pricingService;

    /**
     * BookingService constructor.
     *
     * @param InventoryService    $inventoryService
     * @param EmailService        $emailService
     * @param PricingService|null $pricingService
     */
    public function __construct(
        InventoryService $inventoryService,
        EmailService $emailService,
        ?PricingService $pricingService = null
    ) {
        global $wpdb;
        $this->db = $wpdb;
        $this->inventoryService = $inventoryService;
        $this->emailService = $emailService;
        $this->pricingService = $pricingService ?? new PricingService($inventoryService);
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
        $infantsCount  = max(0, (int) ($data['infants'] ?? 0));
        $totalGuests   = $adultsCount + $childrenCount + $infantsCount;
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

        // Consent verification for non-admin public bookings
        $requireConsent = (bool) Config::get('require_consent', false);
        if (!$trusted && $requireConsent && empty($data['consent'])) {
            return ['success' => false, 'message' => __('You must agree to the terms and privacy policy before completing this booking.', 'tourivo')];
        }

        // 3. Item-Specific Occupancy Validation & Title Resolution
        $itemTitle = '';
        $inventoryQuantity = 1;

        if ($itemType === 'tour') {
            $tour = new Tour($itemId);
            $itemTitle = $tour->getTitle();
            $maxGuests = $tour->getMaxGuests();
            $infantsUseCapacity = (bool) apply_filters('tourivo/infants_use_capacity', false, $itemId, $itemType, $data);
            $capacityGuests = $adultsCount + $childrenCount + ($infantsUseCapacity ? $infantsCount : 0);

            if ($maxGuests > 0 && $capacityGuests > $maxGuests) {
                return [
                    'success' => false,
                    /* translators: %d: Maximum allowed guests */
                    'message' => sprintf(__('This tour allows a maximum of %d guests per booking.', 'tourivo'), $maxGuests),
                ];
            }

            $inventoryQuantity = $capacityGuests;
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
                return [
                    'success' => false,
                    /* translators: 1: Room count, 2: Maximum adults */
                    'message' => sprintf(__('Selected %1$d room(s) can accommodate up to %2$d adults.', 'tourivo'), $roomsCount, $maxAdultsAllowed),
                ];
            }
            if ($maxChildrenAllowed > 0 && $childrenCount > $maxChildrenAllowed) {
                return [
                    'success' => false,
                    /* translators: 1: Room count, 2: Maximum children */
                    'message' => sprintf(__('Selected %1$d room(s) can accommodate up to %2$d children.', 'tourivo'), $roomsCount, $maxChildrenAllowed),
                ];
            }
            if ($maxGuestsAllowed > 0 && $totalGuests > $maxGuestsAllowed) {
                return [
                    'success' => false,
                    /* translators: 1: Room count, 2: Maximum total guests */
                    'message' => sprintf(__('Selected %1$d room(s) can accommodate up to %2$d total guests.', 'tourivo'), $roomsCount, $maxGuestsAllowed),
                ];
            }

            $inventoryQuantity = $roomsCount;
        }

        // 4. Calculate Authoritative Price Quote from Pricing Engine
        $quote = $this->pricingService->quote([
            'item_id'   => $itemId,
            'item_type' => $itemType,
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
            'time_slot' => $timeSlot,
            'adults'    => $adultsCount,
            'children'  => $childrenCount,
            'infants'   => $infantsCount,
            'rooms'     => $roomsCount,
        ]);

        if (!$quote['success']) {
            return ['success' => false, 'message' => $quote['message'] ?? __('Failed to calculate price quote.', 'tourivo')];
        }

        // 5. Check Inventory Availability
        $avail = $this->inventoryService->checkAvailability($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity, !$trusted);
        if (!$avail['available']) {
            return ['success' => false, 'message' => $avail['message']];
        }

        $unitPrice       = (float) $quote['unit_price'];
        $subtotalPrice   = (float) $quote['subtotal'];
        $taxAmount       = (float) $quote['tax'];
        $discountAmount  = (float) $quote['discount'];
        $totalPrice      = (float) $quote['total'];
        $defaultCurrency = (string) ($quote['currency'] ?? Config::get('currency', 'USD'));

        // Allow explicit manual booking total override ONLY for trusted callers
        if ($trusted) {
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
            $paymentMethod = 'offline';
        }

        // 7. Generate unique booking code
        $bookingCode = 'TRV-' . gmdate('Y') . '-' . strtoupper(wp_generate_password(6, false));

        $bookingsTable  = $this->db->prefix . 'tourivo_bookings';
        $itemsTable     = $this->db->prefix . 'tourivo_booking_items';
        $logsTable      = $this->db->prefix . 'tourivo_logs';
        $storeIp        = (bool) Config::get('store_ip', true);
        $ipAddress      = $storeIp ? ClientIp::get() : '';
        $nowGmt         = gmdate('Y-m-d H:i:s');
        $hasConsent     = !empty($data['consent']);
        $consentAt      = $hasConsent ? $nowGmt : null;
        $consentVersion = $hasConsent ? \Tourivo\Support\Privacy::getConsentVersion() : null;

        // 8. Step 1: Commit Inventory First (atomic repository transaction)
        $committed = $this->inventoryService->commitBooking($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity, $holdToken, !$trusted);
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
                'tax_amount'      => $taxAmount,
                'discount_amount' => $discountAmount,
                'paid_amount'     => ($paymentStatus === 'paid') ? $totalPrice : 0.00,
                'due_amount'      => ($paymentStatus === 'paid') ? 0.00 : $totalPrice,
                'currency'        => $defaultCurrency,
                'payment_method'  => $paymentMethod,
                'payment_status'  => $paymentStatus,
                'booking_status'  => $bookingStatus,
                'customer_notes'  => $notes,
                'consent_at'      => $consentAt,
                'consent_version' => $consentVersion,
                'ip_address'      => $ipAddress,
                'created_at'      => ($trusted && !empty($data['created_at'])) ? sanitize_text_field((string) $data['created_at']) : $nowGmt,
                'updated_at'      => $nowGmt,
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
                'infants_count'     => $infantsCount,
                'quantity'          => ($itemType === 'room') ? $roomsCount : $totalGuests,
                'unit_price'        => $unitPrice,
                'total_price'       => $totalPrice,
                'pricing_breakdown' => wp_json_encode($quote, JSON_UNESCAPED_UNICODE),
            ], [
                '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%s'
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
            'infants'         => $infantsCount,
            'rooms'           => $roomsCount,
            'subtotal'        => Money::format($subtotalPrice),
            'raw_subtotal'    => $subtotalPrice,
            'tax_amount'      => Money::format($taxAmount),
            'raw_tax'         => $taxAmount,
            'discount_amount' => Money::format($discountAmount),
            'raw_discount'    => $discountAmount,
            'total_amount'    => Money::format($totalPrice),
            'raw_total'       => $totalPrice,
            'currency'            => $defaultCurrency,
            'payment_status'      => $paymentStatus,
            'booking_status'      => $bookingStatus,
            'send_customer_email' => $data['send_customer_email'] ?? true,
        ];

        do_action('tourivo/booking_created', $bookingId, $bookingData);

        $thankyouToken  = ThankYouShortcode::generateThankYouToken($bookingId);
        $thankyouPageId = (int) Config::get('thankyou_page_id', 0);
        $redirectMode   = (string) Config::get('redirect_after_booking', 'inline');

        $thankyouBaseUrl = $thankyouPageId > 0 ? get_permalink($thankyouPageId) : home_url('/');
        $redirectUrl     = add_query_arg([
            'code'  => $bookingCode,
            'token' => $thankyouToken,
        ], $thankyouBaseUrl);

        return [
            'success'        => true,
            'booking_id'     => $bookingId,
            'booking_code'   => $bookingCode,
            'thankyou_token' => $thankyouToken,
            'redirect_url'   => esc_url_raw($redirectUrl),
            'redirect_mode'  => $redirectMode,
            /* translators: %s: Booking reference code */
            'message'        => sprintf(__('Booking created successfully! Your booking code is #%s.', 'tourivo'), $bookingCode),
        ];
    }

    /**
     * Atomically update booking status with concurrency protection and inventory synchronization.
     *
     * Uses conditional SQL update (WHERE id = %d AND booking_status = %s) to prevent race conditions
     * during concurrent admin/CLI/AJAX actions. Releases or commits inventory only when the database
     * row was genuinely updated (rows_affected === 1).
     *
     * @param int                  $bookingId
     * @param string               $newStatus
     * @param array<string, mixed> $context
     * @return array{success: bool, message: string}
     */
    public function changeStatus(int $bookingId, string $newStatus, array $context = []): array
    {
        if ($bookingId <= 0) {
            return ['success' => false, 'message' => __('Invalid booking ID.', 'tourivo')];
        }

        $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'on_hold'];
        if (!in_array($newStatus, $validStatuses, true)) {
            /* translators: 1: Invalid status string, 2: Comma-separated list of valid statuses */
            return ['success' => false, 'message' => sprintf(__('Invalid status "%1$s". Valid statuses are: %2$s', 'tourivo'), $newStatus, implode(', ', $validStatuses))];
        }

        if (!$this->db) {
            return ['success' => false, 'message' => __('Database connection not available.', 'tourivo')];
        }

        $bookingsTable = $this->db->prefix . 'tourivo_bookings';
        $itemsTable    = $this->db->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $this->db->get_row($this->db->prepare("SELECT * FROM {$bookingsTable} WHERE id = %d", $bookingId));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$booking) {
            return ['success' => false, 'message' => __('Booking not found.', 'tourivo')];
        }

        $oldStatus = (string) $booking->booking_status;

        // If old and new status are the same, return early (no-op)
        if ($oldStatus === $newStatus) {
            return ['success' => true, 'message' => __('Booking status unchanged.', 'tourivo')];
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lineItems = (array) $this->db->get_results($this->db->prepare("SELECT * FROM {$itemsTable} WHERE booking_id = %d", $bookingId));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $committedItems = [];

        // Case 1: Reactivating from cancelled -> Pre-commit inventory before state change
        if ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
            $allCommitted = true;

            if (!empty($lineItems)) {
                foreach ($lineItems as $item) {
                    $committed = $this->inventoryService->commitBooking(
                        (int) $item->item_id,
                        (string) $item->item_type,
                        substr((string) $item->check_in, 0, 10),
                        !empty($item->check_out) ? substr((string) $item->check_out, 0, 10) : null,
                        (string) ($item->time_slot ?: 'all_day'),
                        (int) $item->quantity
                    );

                    if (!$committed) {
                        $allCommitted = false;
                        break;
                    }
                    $committedItems[] = $item;
                }
            }

            if (!$allCommitted) {
                // Rollback any successfully committed items in this loop
                foreach ($committedItems as $cItem) {
                    $this->inventoryService->releaseBookingInventory(
                        (int) $cItem->item_id,
                        (string) $cItem->item_type,
                        substr((string) $cItem->check_in, 0, 10),
                        !empty($cItem->check_out) ? substr((string) $cItem->check_out, 0, 10) : null,
                        (string) ($cItem->time_slot ?: 'all_day'),
                        (int) $cItem->quantity
                    );
                }
                return ['success' => false, 'message' => __('Cannot reactivate booking. Required inventory spots are no longer available.', 'tourivo')];
            }
        }

        // Conditional atomic update matching $oldStatus to eliminate race condition collisions
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rowsAffected = $this->db->query(
            $this->db->prepare(
                "UPDATE {$bookingsTable} SET booking_status = %s, updated_at = %s WHERE id = %d AND booking_status = %s",
                $newStatus,
                gmdate('Y-m-d H:i:s'),
                $bookingId,
                $oldStatus
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ($rowsAffected !== 1) {
            // Rollback pre-committed inventory if conditional update failed due to concurrent modification
            if (!empty($committedItems)) {
                foreach ($committedItems as $cItem) {
                    $this->inventoryService->releaseBookingInventory(
                        (int) $cItem->item_id,
                        (string) $cItem->item_type,
                        substr((string) $cItem->check_in, 0, 10),
                        !empty($cItem->check_out) ? substr((string) $cItem->check_out, 0, 10) : null,
                        (string) ($cItem->time_slot ?: 'all_day'),
                        (int) $cItem->quantity
                    );
                }
            }

            return ['success' => false, 'message' => __('Booking status has already changed or could not be updated.', 'tourivo')];
        }

        // If cancelling from an active state, release inventory once conditional update succeeded
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            if (!empty($lineItems)) {
                foreach ($lineItems as $item) {
                    $this->inventoryService->releaseBookingInventory(
                        (int) $item->item_id,
                        (string) $item->item_type,
                        substr((string) $item->check_in, 0, 10),
                        !empty($item->check_out) ? substr((string) $item->check_out, 0, 10) : null,
                        (string) ($item->time_slot ?: 'all_day'),
                        (int) $item->quantity
                    );
                }
            }
        }

        // Invalidate cache
        wp_cache_delete('booking_' . $bookingId, 'tourivo');
        if (!empty($booking->booking_code)) {
            wp_cache_delete('booking_code_' . $booking->booking_code, 'tourivo');
        }

        LogService::log(
            $bookingId,
            'status_changed',
            sprintf(
                /* translators: 1: Old status, 2: New status */
                __('Booking status changed from %1$s to %2$s', 'tourivo'),
                ucfirst($oldStatus),
                ucfirst($newStatus)
            )
        );

        do_action('tourivo/booking_status_changed', $bookingId, $oldStatus, $newStatus);

        return [
            'success' => true,
            /* translators: 1: Old status, 2: New status */
            'message' => sprintf(__('Booking status changed from %1$s to %2$s successfully.', 'tourivo'), ucfirst($oldStatus), ucfirst($newStatus)),
        ];
    }
}
