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
use Tourivo\Support\RateLimiter;
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

        if (!self::isValidDate($checkIn) || ($checkOut !== null && !self::isValidDate($checkOut))) {
            return ['success' => false, 'message' => __('Please provide valid dates in YYYY-MM-DD format.', 'tourivo')];
        }
        if ($checkOut !== null && $checkOut < $checkIn) {
            return ['success' => false, 'message' => __('The check-out date cannot be earlier than the check-in date.', 'tourivo')];
        }

        // Consent verification for non-admin public bookings
        $requireConsent = (bool) Config::get('require_consent', false);
        if (!$trusted && $requireConsent && empty($data['consent'])) {
            return ['success' => false, 'message' => __('You must agree to the terms and privacy policy before completing this booking.', 'tourivo')];
        }

        // Per-address throttle: stops one inbox being flooded with booking mail from rotating IPs.
        if (!$trusted) {
            $emailLimit = max(1, (int) apply_filters('tourivo/booking_email_rate_limit', 5));
            if (!RateLimiter::hit('trv_rl_email_' . md5(strtolower($customerEmail)), $emailLimit, 3600)) {
                return ['success' => false, 'message' => __('Too many booking requests were made for this email address. Please try again later.', 'tourivo')];
            }
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
            if ($checkOut <= $checkIn) {
                return ['success' => false, 'message' => __('Check-out must be at least one night after check-in.', 'tourivo')];
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
        $avail = $this->inventoryService->checkAvailability($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity, !$trusted, $holdToken);
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

        // A zero total is only legitimate for items explicitly marked free; otherwise it means pricing failed
        // (missing price meta, add-on bug) and must never turn into a free booking.
        if ($totalPrice < 0 || ($totalPrice <= 0.0 && !$this->allowsFreeBooking($itemId))) {
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
                : $this->defaultBookingStatus();

            $paymentStatus = in_array($data['payment_status'] ?? '', ['pending', 'paid', 'partially_paid', 'refunded', 'failed'], true)
                ? (string) $data['payment_status']
                : 'pending';

            $paymentMethod = sanitize_text_field((string) ($data['payment_method'] ?? 'offline'));
        } else {
            $bookingStatus = $this->defaultBookingStatus();
            $paymentStatus = 'pending';
            $paymentMethod = 'offline';
        }

        // Nothing to collect on a free booking: it is settled by definition (and so exempt from unpaid-expiry).
        if ($totalPrice <= 0.0) {
            $paymentStatus = 'paid';
        }

        // 7. Generate unique booking code
        $bookingCode = self::generateBookingCode();

        $bookingsTable  = $this->db->prefix . 'tourivo_bookings';
        $itemsTable     = $this->db->prefix . 'tourivo_booking_items';
        $logsTable      = $this->db->prefix . 'tourivo_logs';
        $storeIp        = (bool) Config::get('store_ip', true);
        $ipAddress      = $storeIp ? ClientIp::get() : '';
        $nowGmt         = gmdate('Y-m-d H:i:s');
        $hasConsent     = !empty($data['consent']);
        $consentAt      = $hasConsent ? $nowGmt : null;
        $consentVersion = $hasConsent ? \Tourivo\Support\Privacy::getConsentVersion() : null;
        // Public bookings must prove ownership of the address before staff/customers are notified or the hold persists.
        $requireVerification = !$trusted && (bool) Config::get('require_email_verification', false);

        // 8. Step 1: Commit Inventory First (atomic repository transaction)
        $committed = $this->inventoryService->commitBooking($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity, $holdToken, !$trusted);
        if (!$committed) {
            return ['success' => false, 'message' => __('Failed to commit inventory. Selected slots are no longer available.', 'tourivo')];
        }

        // 8. Step 2: Insert Booking Record
        // Booking row, line item and audit entry are written atomically; inventory was committed (and is
        // released on any failure below) in its own row-locked transactions beforehand.
        $this->db->query('START TRANSACTION');
        try {
            $bookingRow = [
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
                'email_verified_at' => $requireVerification ? null : $nowGmt,
                'access_key'      => bin2hex(random_bytes(16)),
                'consent_at'      => $consentAt,
                'consent_version' => $consentVersion,
                'ip_address'      => $ipAddress,
                'created_at'      => ($trusted && !empty($data['created_at'])) ? sanitize_text_field((string) $data['created_at']) : $nowGmt,
                'updated_at'      => $nowGmt,
            ];

            // The code is random; if it collides with an existing one (unique key) draw a new one.
            $inserted = false;
            for ($attempt = 0; $attempt < 3 && !$inserted; $attempt++) {
                if ($attempt > 0) {
                    $bookingCode = self::generateBookingCode();
                }
                $inserted = $this->db->insert($bookingsTable, ['booking_code' => $bookingCode] + $bookingRow);
            }

            if (!$inserted) {
                $this->db->query('ROLLBACK');
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
                $this->db->query('ROLLBACK');
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

            $this->db->query('COMMIT');
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            $this->inventoryService->releaseBookingInventory($itemId, $itemType, $checkIn, $checkOut, $timeSlot, $inventoryQuantity);
            do_action('tourivo/booking_error', $e);

            // Internal details stay out of the customer-facing response.
            return ['success' => false, 'message' => __('An unexpected error occurred while creating your booking. Please try again.', 'tourivo')];
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
            'email_verified'      => !$requireVerification,
            'verify_url'          => $requireVerification ? self::buildVerificationUrl($bookingCode, $bookingId, $customerEmail) : '',
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
            'requires_verification' => $requireVerification,
            'message'        => $requireVerification
                /* translators: %s: Booking reference code */
                ? sprintf(__('Almost done! Your booking code is #%s. Please open the confirmation link we emailed you to secure your reservation.', 'tourivo'), $bookingCode)
                /* translators: %s: Booking reference code */
                : sprintf(__('Booking created successfully! Your booking code is #%s.', 'tourivo'), $bookingCode),
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

        $sourceNote = match ((string) ($context['source'] ?? '')) {
            'unverified_expired' => __(' (email address was never confirmed)', 'tourivo'),
            'pending_expired'    => __(' (pending booking expired)', 'tourivo'),
            default              => '',
        };

        LogService::log(
            $bookingId,
            'status_changed',
            sprintf(
                /* translators: 1: Old status, 2: New status */
                __('Booking status changed from %1$s to %2$s', 'tourivo'),
                ucfirst($oldStatus),
                ucfirst($newStatus)
            ) . $sourceNote
        );

        do_action('tourivo/booking_status_changed', $bookingId, $oldStatus, $newStatus, $context);

        return [
            'success' => true,
            /* translators: 1: Old status, 2: New status */
            'message' => sprintf(__('Booking status changed from %1$s to %2$s successfully.', 'tourivo'), ucfirst($oldStatus), ucfirst($newStatus)),
        ];
    }

    /**
     * Issue a fresh per-booking access key, killing every voucher / calendar link handed out so far.
     */
    public function rotateAccessKey(int $bookingId): bool
    {
        if ($bookingId <= 0 || !$this->db) {
            return false;
        }

        $table = $this->db->prefix . 'tourivo_bookings';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $this->db->update(
            $table,
            ['access_key' => bin2hex(random_bytes(16)), 'updated_at' => gmdate('Y-m-d H:i:s')],
            ['id' => $bookingId],
            ['%s', '%s'],
            ['%d']
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($updated === false || $updated === 0) {
            return false;
        }

        wp_cache_delete('booking_' . $bookingId, 'tourivo');
        wp_cache_delete('tourivo_booking_' . $bookingId, 'tourivo');
        LogService::log($bookingId, 'links_revoked', __('Voucher and calendar links were revoked by staff.', 'tourivo'));

        return true;
    }

    /**
     * Whether the item is explicitly marked as free of charge (post meta, overridable by add-ons).
     */
    protected function allowsFreeBooking(int $itemId): bool
    {
        return (bool) apply_filters(
            'tourivo/allow_free_booking',
            get_post_meta($itemId, '_tourivo_allow_free_booking', true) === '1',
            $itemId
        );
    }

    /**
     * Strict Y-m-d calendar date check (rejects 2025-02-30, "tomorrow", timestamps, etc.).
     */
    protected static function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * Random human-friendly booking reference, e.g. TRV-2026-8K3MZQ.
     */
    protected static function generateBookingCode(): string
    {
        return 'TRV-' . gmdate('Y') . '-' . strtoupper(wp_generate_password(6, false));
    }

    /**
     * Status given to new bookings: the admin setting (pending|confirmed), filterable by add-ons.
     */
    protected function defaultBookingStatus(): string
    {
        $configured = (string) Config::get('default_booking_status', 'pending');
        if (!in_array($configured, ['pending', 'confirmed'], true)) {
            $configured = 'pending';
        }

        return (string) apply_filters('tourivo/default_booking_status', $configured);
    }

    /**
     * Signed token proving the holder received the verification email for this booking.
     */
    public static function generateVerificationToken(int $bookingId, string $email): string
    {
        return hash_hmac('sha256', "tourivo_verify_{$bookingId}_" . strtolower($email), wp_salt('nonce'));
    }

    /**
     * Build the one-click email confirmation link.
     */
    public static function buildVerificationUrl(string $bookingCode, int $bookingId, string $email): string
    {
        return add_query_arg([
            'action' => 'tourivo_verify_email',
            'code'   => $bookingCode,
            'token'  => self::generateVerificationToken($bookingId, $email),
        ], admin_url('admin-post.php'));
    }

    /**
     * Confirm the customer's email address for a booking.
     *
     * @return array{success: bool, message: string, booking_id?: int}
     */
    public function verifyEmail(string $bookingCode, string $token): array
    {
        $invalid = ['success' => false, 'message' => __('This confirmation link is invalid.', 'tourivo')];

        if (!$this->db || $bookingCode === '' || $token === '') {
            return $invalid;
        }

        $bookingsTable = $this->db->prefix . 'tourivo_bookings';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $this->db->get_row($this->db->prepare("SELECT * FROM {$bookingsTable} WHERE booking_code = %s LIMIT 1", $bookingCode));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$booking || !hash_equals(self::generateVerificationToken((int) $booking->id, (string) $booking->customer_email), $token)) {
            return $invalid;
        }

        $bookingId = (int) $booking->id;

        if (!empty($booking->email_verified_at)) {
            return ['success' => true, 'booking_id' => $bookingId, 'message' => __('Your email address is already confirmed.', 'tourivo')];
        }

        if ((string) $booking->booking_status === 'cancelled') {
            return ['success' => false, 'message' => __('This booking was cancelled because the email address was not confirmed in time. Please make a new booking.', 'tourivo')];
        }

        $nowGmt = gmdate('Y-m-d H:i:s');

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $updated = $this->db->query($this->db->prepare(
            "UPDATE {$bookingsTable} SET email_verified_at = %s, updated_at = %s WHERE id = %d AND email_verified_at IS NULL",
            $nowGmt,
            $nowGmt,
            $bookingId
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        wp_cache_delete('booking_' . $bookingId, 'tourivo');
        wp_cache_delete('booking_code_' . $bookingCode, 'tourivo');

        // Another request verified it first: still a success for this visitor, but only one run of the follow-up mail.
        if ($updated === 1) {
            LogService::log($bookingId, 'email_verified', __('Customer confirmed their email address.', 'tourivo'), 0);
            do_action('tourivo/booking_email_verified', $bookingId);
        }

        return ['success' => true, 'booking_id' => $bookingId, 'message' => __('Thank you! Your email address is confirmed and your booking is secured.', 'tourivo')];
    }

    /**
     * Cancel bookings that were never confirmed or whose pending window ran out, returning their inventory.
     *
     * Runs from cron. Unverified bookings expire after `unverified_expiry_minutes`; unpaid pending bookings
     * expire after `pending_expiry_hours` (0 disables that second rule).
     *
     * @return array{unverified: int, pending: int}
     */
    public function expireStaleBookings(): array
    {
        $result = ['unverified' => 0, 'pending' => 0];
        if (!$this->db) {
            return $result;
        }

        $bookingsTable     = $this->db->prefix . 'tourivo_bookings';
        $unverifiedMinutes = max(5, (int) Config::get('unverified_expiry_minutes', 60));
        $pendingHours      = max(0, (int) Config::get('pending_expiry_hours', 0));

        $rules = [
            'unverified_expired' => [
                'cutoff' => gmdate('Y-m-d H:i:s', time() - ($unverifiedMinutes * 60)),
                'sql'    => "SELECT id, booking_status, payment_status, email_verified_at FROM {$bookingsTable} WHERE email_verified_at IS NULL AND booking_status IN ('pending', 'confirmed') AND created_at <= %s ORDER BY id ASC LIMIT 100",
                'key'    => 'unverified',
            ],
        ];
        if ($pendingHours > 0) {
            $rules['pending_expired'] = [
                'cutoff' => gmdate('Y-m-d H:i:s', time() - ($pendingHours * 3600)),
                'sql'    => "SELECT id, booking_status, payment_status, email_verified_at FROM {$bookingsTable} WHERE booking_status = 'pending' AND payment_status <> 'paid' AND created_at <= %s ORDER BY id ASC LIMIT 100",
                'key'    => 'pending',
            ];
        }

        foreach ($rules as $source => $rule) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = (array) $this->db->get_results($this->db->prepare($rule['sql'], $rule['cutoff']));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            foreach ($rows as $row) {
                // Re-check in PHP so the rules hold regardless of how the candidate list was produced.
                $allowed = $source === 'unverified_expired' ? ['pending', 'confirmed'] : ['pending'];
                if (!in_array((string) $row->booking_status, $allowed, true)) {
                    continue;
                }
                if ($source === 'unverified_expired' && !empty($row->email_verified_at)) {
                    continue;
                }
                if ($source === 'pending_expired' && (string) $row->payment_status === 'paid') {
                    continue;
                }
                if (!(bool) apply_filters('tourivo/expire_booking', true, $row, $source)) {
                    continue;
                }

                $res = $this->changeStatus((int) $row->id, 'cancelled', ['source' => $source]);
                if (!empty($res['success'])) {
                    $result[$rule['key']]++;
                }
            }
        }

        return $result;
    }
}
