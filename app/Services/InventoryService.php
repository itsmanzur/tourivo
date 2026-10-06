<?php

declare(strict_types=1);

namespace Tourivo\Services;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use Tourivo\Models\Room;
use Tourivo\Models\Tour;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class InventoryService
 *
 * Core business logic for availability tracking, date checks, checkout holds and calendar grids.
 *
 * @package Tourivo\Services
 */
class InventoryService
{
    /**
     * Inventory Repository.
     *
     * @var InventoryRepository
     */
    protected InventoryRepository $repository;

    /**
     * InventoryService constructor.
     *
     * @param InventoryRepository $repository
     */
    public function __construct(InventoryRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get default capacity for an item.
     *
     * @param int    $itemId
     * @param string $itemType
     * @return int
     */
    public function getDefaultCapacity(int $itemId, string $itemType): int
    {
        if ($itemType === 'tour') {
            $tour = new Tour($itemId);
            if ($tour->getDailyCapacity() > 0) {
                return $tour->getDailyCapacity();
            }

            // No explicit daily capacity configured: fall back to the group-size limit (legacy behaviour).
            return $tour->getMaxGuests() > 0 ? $tour->getMaxGuests() : 20;
        }

        if ($itemType === 'hotel_room' || $itemType === 'room') {
            $room = new Room($itemId);
            return $room->getQuantity() > 0 ? $room->getQuantity() : 1;
        }

        return 1;
    }

    /**
     * Normalize and strictly whitelist time slot value.
     *
     * @param string|null $timeSlot
     * @return string
     */
    public static function normalizeTimeSlot(?string $timeSlot): string
    {
        $allowed = ['all_day'];
        $allowed = (array) apply_filters('tourivo/allowed_time_slots', $allowed);

        $slot = !empty($timeSlot) ? sanitize_text_field(trim($timeSlot)) : 'all_day';
        if (!in_array($slot, $allowed, true)) {
            return 'all_day';
        }

        return $slot;
    }

    /**
     * Get active unit price for an item on a specific date.
     *
     * @param int         $itemId
     * @param string      $itemType
     * @param string|null $date
     * @param string      $timeSlot
     * @return float
     */
    public function getUnitPrice(int $itemId, string $itemType, ?string $date = null, string $timeSlot = 'all_day'): float
    {
        $timeSlot = self::normalizeTimeSlot($timeSlot);

        // 1. Check if custom price override exists in DB for this date
        if (!empty($date)) {
            $record = $this->repository->getRecord($itemId, $itemType, $date, $timeSlot);
            if ($record && isset($record->price_override) && $record->price_override !== null && (float) $record->price_override > 0) {
                return (float) $record->price_override;
            }
        }

        // 2. Default to Model price
        if ($itemType === 'tour') {
            $tour = new Tour($itemId);
            return $tour->getActivePrice();
        }

        if ($itemType === 'hotel_room' || $itemType === 'room') {
            $room = new Room($itemId);
            return $room->getNightlyPrice();
        }

        return 0.0;
    }

    /**
     * Check real-time availability for a single date or date range.
     *
     * @param int         $itemId
     * @param string      $itemType
     * @param string      $startDate (Y-m-d)
     * @param string|null $endDate   (Y-m-d, optional for single day tours)
     * @param string      $timeSlot
     * @param int         $requestedCount Number of seats or rooms
     * @return array{
     *     available: bool,
     *     available_spots: int,
     *     unit_price: float,
     *     total_price: float,
     *     currency_symbol: string,
     *     dates_checked: array<string>,
     *     message: string
     * }
     */
    public function checkAvailability(
        int $itemId,
        string $itemType,
        string $startDate,
        ?string $endDate = null,
        string $timeSlot = 'all_day',
        int $requestedCount = 1,
        bool $enforceFuture = true,
        ?string $holdToken = null
    ): array {
        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');
        $defaultCapacity = $this->getDefaultCapacity($itemId, $itemType);
        $dates = $this->generateDateList($startDate, $endDate, $enforceFuture);

        if (empty($dates)) {
            return [
                'available'       => false,
                'available_spots' => 0,
                'unit_price'      => 0.0,
                'total_price'     => 0.0,
                'currency_symbol' => $currencySymbol,
                'dates_checked'   => [],
                'message'         => __('Invalid date range specified.', 'tourivo'),
            ];
        }

        // A valid hold for exactly this selection is the caller's own reservation: don't count it against them.
        $ownHoldSpots = 0;
        $hold = $this->getHold($holdToken);
        if ($hold !== null) {
            $normalizedType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
            if ($this->holdMatches($hold, $itemId, $normalizedType, $dates, $timeSlot, 1)) {
                $ownHoldSpots = (int) $hold['count'];
            }
        }

        $minAvailableSpots = PHP_INT_MAX;
        $totalCalculatedPrice = 0.0;
        $allAvailable = true;

        foreach ($dates as $date) {
            $record = $this->repository->getRecord($itemId, $itemType, $date, $timeSlot);

            $capacity = $record ? (int) $record->total_capacity : $defaultCapacity;
            $booked   = $record ? (int) $record->booked_count : 0;
            $reserved = $record ? (int) $record->reserved_count : 0;
            $status   = $record ? $record->status : 'available';

            $spotsLeft = max(0, $capacity - $booked - max(0, $reserved - $ownHoldSpots));
            if ($spotsLeft < $minAvailableSpots) {
                $minAvailableSpots = $spotsLeft;
            }

            if ($status !== 'available' || $spotsLeft < $requestedCount) {
                $allAvailable = false;
            }

            $datePrice = $this->getUnitPrice($itemId, $itemType, $date, $timeSlot);
            $totalCalculatedPrice += ($datePrice * $requestedCount);
        }

        $avgUnitPrice = count($dates) > 0 ? ($totalCalculatedPrice / ($requestedCount * count($dates))) : 0.0;

        return [
            'available'       => $allAvailable,
            'available_spots' => ($minAvailableSpots === PHP_INT_MAX) ? $defaultCapacity : $minAvailableSpots,
            'unit_price'      => round($avgUnitPrice, 2),
            'total_price'     => round($totalCalculatedPrice, 2),
            'currency_symbol' => $currencySymbol,
            'dates_checked'   => $dates,
            'message'         => $allAvailable
                /* translators: %d: Number of spots left */
                ? sprintf(__('Available (%d spots left)', 'tourivo'), $minAvailableSpots)
                : __('Selected dates or quantity are not available.', 'tourivo'),
        ];
    }

    /**
     * Get whole month calendar availability grid.
     *
     * @param int    $itemId
     * @param string $itemType
     * @param int    $year
     * @param int    $month
     * @param string $timeSlot
     * @return array<string, array{status: string, spots: int, price: float, formatted_price: string}>
     */
    public function getCalendarAvailability(int $itemId, string $itemType, int $year, int $month, string $timeSlot = 'all_day'): array
    {
        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');
        $defaultCapacity = $this->getDefaultCapacity($itemId, $itemType);

        // Sanitize year & month bounds
        $currentYear = (int) wp_date('Y');
        $year  = max($currentYear - 1, min($currentYear + 5, $year));
        $month = max(1, min(12, $month));

        // Use gmdate('t') instead of cal_days_in_month for cross-platform hosting portability
        $daysInMonth = (int) gmdate('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $records = $this->repository->getRecordsInRange($itemId, $itemType, $startDate, $endDate, $timeSlot);
        $recordsByDate = [];
        foreach ($records as $rec) {
            $recordsByDate[$rec->event_date] = $rec;
        }

        $calendar = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $record = $recordsByDate[$dateStr] ?? null;

            $capacity = $record ? (int) $record->total_capacity : $defaultCapacity;
            $booked   = $record ? (int) $record->booked_count : 0;
            $reserved = $record ? (int) $record->reserved_count : 0;
            $status   = $record ? $record->status : 'available';

            $spotsLeft = max(0, $capacity - $booked - $reserved);

            if ($spotsLeft <= 0) {
                $status = 'sold_out';
            } elseif ($status === 'available' && $spotsLeft <= 3) {
                $status = 'limited';
            }

            $price = $this->getUnitPrice($itemId, $itemType, $dateStr, $timeSlot);

            $calendar[$dateStr] = [
                'status'          => $status,
                'spots'           => $spotsLeft,
                'price'           => $price,
                'formatted_price' => Money::format($price),
            ];
        }

        return $calendar;
    }

    /**
     * Option-name prefix under which checkout holds are persisted.
     *
     * Holds live in a dedicated, per-token option (never autoloaded) instead of a transient so the
     * reserved spots can always be released exactly, even if the object cache is flushed.
     */
    public const HOLD_OPTION_PREFIX = 'tourivo_hold_';

    /**
     * Default checkout hold lifetime in minutes.
     */
    public const HOLD_TTL_MINUTES = 15;

    /**
     * Resolve a hold token to its stored, still-valid hold data.
     *
     * @param string|null $holdToken
     * @return array<string, mixed>|null
     */
    public function getHold(?string $holdToken): ?array
    {
        if (empty($holdToken) || !str_starts_with($holdToken, 'trv_hold_')) {
            return null;
        }

        $data = get_option(self::HOLD_OPTION_PREFIX . $holdToken, false);
        if (!is_array($data) || (int) ($data['expires_at'] ?? 0) <= time()) {
            return null;
        }

        return $data;
    }

    /**
     * Whether a hold was issued for exactly this item, dates and slot and covers at least $count spots.
     *
     * A token is only a capability for the reservation it was issued for; presenting it for any other
     * item/date/quantity must never unlock the "skip capacity check" commit path.
     *
     * @param array<string, mixed> $hold
     * @param array<string>        $dates
     */
    protected function holdMatches(array $hold, int $itemId, string $itemType, array $dates, string $timeSlot, int $count): bool
    {
        return (int) ($hold['item_id'] ?? 0) === $itemId
            && (string) ($hold['item_type'] ?? '') === $itemType
            && (string) ($hold['time_slot'] ?? '') === $timeSlot
            && array_values((array) ($hold['dates'] ?? [])) === array_values($dates)
            && (int) ($hold['count'] ?? 0) >= $count;
    }

    /**
     * Hold spots during checkout with unique hold token.
     *
     * @param int         $itemId
     * @param string      $itemType
     * @param string      $startDate
     * @param string|null $endDate
     * @param string      $timeSlot
     * @param int         $count
     * @param int         $ttlMinutes
     * @param bool        $enforceFuture
     * @param string      $owner Opaque requester key (e.g. hashed IP), stored for abuse tracking.
     * @return string|false Hold token or false
     */
    public function holdInventory(
        int $itemId,
        string $itemType,
        string $startDate,
        ?string $endDate = null,
        string $timeSlot = 'all_day',
        int $count = 1,
        int $ttlMinutes = self::HOLD_TTL_MINUTES,
        bool $enforceFuture = true,
        string $owner = ''
    ): string|false {
        $this->maybeReleaseExpiredHolds();

        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
        $dates = $this->generateDateList($startDate, $endDate, $enforceFuture);
        if (empty($dates)) {
            return false;
        }

        $defaultCapacity = $this->getDefaultCapacity($itemId, $itemType);

        // Reserve all dates
        $reservedDates = [];
        foreach ($dates as $date) {
            $success = $this->repository->reserveSpots($itemId, $itemType, $date, $timeSlot, $count, $defaultCapacity);
            if (!$success) {
                // Rollback previously reserved dates in this loop
                foreach ($reservedDates as $rDate) {
                    $this->repository->releaseSpots($itemId, $itemType, $rDate, $timeSlot, $count);
                }
                return false;
            }
            $reservedDates[] = $date;
        }

        $holdToken = 'trv_hold_' . bin2hex(random_bytes(16));
        update_option(self::HOLD_OPTION_PREFIX . $holdToken, [
            'item_id'    => $itemId,
            'item_type'  => $itemType,
            'dates'      => $dates,
            'time_slot'  => $timeSlot,
            'count'      => $count,
            'owner'      => $owner,
            'expires_at' => time() + ($ttlMinutes * 60),
        ], false);

        return $holdToken;
    }

    /**
     * Commit booking (convert hold to confirmed booking or direct booking).
     *
     * @param int         $itemId
     * @param string      $itemType
     * @param string      $startDate
     * @param string|null $endDate
     * @param string      $timeSlot
     * @param int         $count
     * @param string|null $holdToken
     * @param bool        $enforceFuture
     * @return bool
     */
    public function commitBooking(
        int $itemId,
        string $itemType,
        string $startDate,
        ?string $endDate = null,
        string $timeSlot = 'all_day',
        int $count = 1,
        ?string $holdToken = null,
        bool $enforceFuture = true
    ): bool {
        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
        $dates = $this->generateDateList($startDate, $endDate, $enforceFuture);
        if (empty($dates)) {
            return false;
        }

        $defaultCapacity = $this->getDefaultCapacity($itemId, $itemType);

        $hold = $this->getHold($holdToken);
        $hasPriorHold = $hold !== null && $this->holdMatches($hold, $itemId, $itemType, $dates, $timeSlot, $count);

        $committedDates = [];
        foreach ($dates as $date) {
            $ok = $this->repository->commitSpots($itemId, $itemType, $date, $timeSlot, $count, $defaultCapacity, $hasPriorHold);
            if (!$ok) {
                // Rollback any committed dates if one fails
                foreach ($committedDates as $cDate) {
                    $this->repository->releaseBooked($itemId, $itemType, $cDate, $timeSlot, $count);
                }
                return false;
            }
            $committedDates[] = $date;
        }

        if ($hasPriorHold && $hold !== null && !empty($holdToken)) {
            // Hold covered more spots than were booked: give the surplus back immediately.
            $surplus = (int) $hold['count'] - $count;
            if ($surplus > 0) {
                foreach ($dates as $date) {
                    $this->repository->releaseSpots($itemId, $itemType, $date, $timeSlot, $surplus);
                }
            }
            delete_option(self::HOLD_OPTION_PREFIX . $holdToken);
        }

        return true;
    }

    /**
     * Release booked inventory back to pool (e.g. upon booking cancellation).
     *
     * @param int         $itemId
     * @param string      $itemType
     * @param string      $startDate
     * @param string|null $endDate
     * @param string      $timeSlot
     * @param int         $count
     * @return bool
     */
    public function releaseBookingInventory(
        int $itemId,
        string $itemType,
        string $startDate,
        ?string $endDate = null,
        string $timeSlot = 'all_day',
        int $count = 1
    ): bool {
        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
        $dates = $this->generateDateList($startDate, $endDate, false);
        if (empty($dates)) {
            return false;
        }

        foreach ($dates as $date) {
            $this->repository->releaseBooked($itemId, $itemType, $date, $timeSlot, $count);
        }

        return true;
    }

    /**
     * Release hold if checkout cancelled.
     *
     * @param string $holdToken
     * @return bool
     */
    public function releaseHold(string $holdToken): bool
    {
        if (!str_starts_with($holdToken, 'trv_hold_')) {
            return false;
        }

        $optionName = self::HOLD_OPTION_PREFIX . $holdToken;
        $data = get_option($optionName, false);
        if (!is_array($data)) {
            return false;
        }

        $this->releaseHoldData($data);
        delete_option($optionName);

        return true;
    }

    /**
     * Give a hold's reserved spots back to the pool.
     *
     * @param array<string, mixed> $data
     */
    protected function releaseHoldData(array $data): void
    {
        $itemId   = (int) ($data['item_id'] ?? 0);
        $itemType = (string) ($data['item_type'] ?? 'tour');
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
        $timeSlot = (string) ($data['time_slot'] ?? 'all_day');
        $count    = (int) ($data['count'] ?? 1);

        foreach ((array) ($data['dates'] ?? []) as $date) {
            $this->repository->releaseSpots($itemId, $itemType, (string) $date, $timeSlot, $count);
        }
    }

    /**
     * Release every expired hold, returning exactly the spots each one reserved.
     *
     * Unlike a blanket "zero all reserved counts" sweep this never touches holds that are still live.
     *
     * @return int Number of holds released.
     */
    public function releaseExpiredHolds(): int
    {
        global $wpdb;
        if (!$wpdb) {
            return 0;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $names = (array) $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 500",
            $wpdb->esc_like(self::HOLD_OPTION_PREFIX) . '%'
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $released = 0;
        foreach ($names as $name) {
            $name = (string) $name;
            $data = get_option($name, false);
            if (!is_array($data)) {
                delete_option($name);
                continue;
            }
            if ((int) ($data['expires_at'] ?? 0) > time()) {
                continue;
            }

            $this->releaseHoldData($data);
            delete_option($name);
            $released++;
        }

        return $released;
    }

    /**
     * Opportunistic, throttled expiry sweep so inventory frees up even if WP-Cron is slow or disabled.
     */
    protected function maybeReleaseExpiredHolds(): void
    {
        if (get_transient('trv_hold_sweep')) {
            return;
        }
        set_transient('trv_hold_sweep', 1, 60);
        $this->releaseExpiredHolds();
    }

    /**
     * Generate a validated list of dates between start and end.
     *
     * @param string      $startDate (Y-m-d)
     * @param string|null $endDate   (Y-m-d)
     * @param bool        $enforceFuture Check whether start date must not be in past
     * @return array<string>
     */
    public function generateDateList(string $startDate, ?string $endDate = null, bool $enforceFuture = true): array
    {
        // 1. Strict Y-m-d validation
        $start = DateTime::createFromFormat('Y-m-d', trim($startDate));
        if (!$start || $start->format('Y-m-d') !== trim($startDate)) {
            return [];
        }

        $today = new DateTime(wp_date('Y-m-d'));
        if ($enforceFuture && $start < $today) {
            return [];
        }

        if (empty($endDate) || $startDate === $endDate) {
            return [$start->format('Y-m-d')];
        }

        $end = DateTime::createFromFormat('Y-m-d', trim($endDate));
        if (!$end || $end->format('Y-m-d') !== trim($endDate)) {
            return [$start->format('Y-m-d')];
        }

        if ($start > $end) {
            return [];
        }

        // Cap date span at 365 days max to prevent DoS memory spikes
        $diffDays = (int) $start->diff($end)->format('%a');
        if ($diffDays > 365) {
            return [];
        }

        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);

        $dates = [];
        foreach ($period as $dt) {
            $dates[] = $dt->format('Y-m-d');
        }

        return !empty($dates) ? $dates : [$start->format('Y-m-d')];
    }

    /**
     * Batch update inventory capacity, blocking status, and pricing overrides for an item.
     *
     * @param int                  $itemId
     * @param string               $itemType 'tour' or 'room'
     * @param string               $startDate (Y-m-d)
     * @param string|null          $endDate   (Y-m-d)
     * @param array<string, mixed> $changes   ['status' => 'available'|'blocked', 'capacity' => int, 'price_override' => float, 'reset_price' => bool, 'days_of_week' => array<int>]
     * @param bool                 $force     Force-block dates with existing bookings without cancelling them
     * @param string               $timeSlot
     * @return array{success: bool, message: string, updated_count?: int, updated_dates?: array<string>}
     */
    public function updateAvailability(
        int $itemId,
        string $itemType,
        string $startDate,
        ?string $endDate = null,
        array $changes = [],
        bool $force = false,
        string $timeSlot = 'all_day'
    ): array {
        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : ($itemType === 'tour' ? 'tour' : '');

        if (empty($itemType)) {
            return [
                'success' => false,
                'message' => __('Invalid item type. Must be tour or room.', 'tourivo'),
            ];
        }

        if ($itemId <= 0) {
            return [
                'success' => false,
                'message' => __('Invalid item ID.', 'tourivo'),
            ];
        }

        $post = get_post($itemId);
        if (!$post) {
            return [
                'success' => false,
                'message' => __('Selected item was not found.', 'tourivo'),
            ];
        }

        $expectedPostType = $itemType === 'tour' ? 'tourivo_tour' : 'tourivo_room';
        if ($post->post_type !== $expectedPostType) {
            return [
                'success' => false,
                'message' => __('Selected item does not match the given item type.', 'tourivo'),
            ];
        }

        // Validate capacity parameter if given
        if (isset($changes['capacity']) && (!is_numeric($changes['capacity']) || (int) $changes['capacity'] < 0)) {
            return [
                'success' => false,
                'message' => __('Capacity must be a non-negative whole number.', 'tourivo'),
            ];
        }

        // Validate price override parameter if given
        if (isset($changes['price_override']) && $changes['price_override'] !== '' && $changes['price_override'] !== null) {
            if (!is_numeric($changes['price_override']) || (float) $changes['price_override'] < 0) {
                return [
                    'success' => false,
                    'message' => __('Price override must be a non-negative number.', 'tourivo'),
                ];
            }
        }

        // Generate date list
        $dates = $this->generateDateList($startDate, $endDate, false);
        if (empty($dates)) {
            return [
                'success' => false,
                'message' => __('Invalid date range specified. Range must be valid Y-m-d format and maximum 365 days.', 'tourivo'),
            ];
        }

        // Apply day-of-week filter if provided
        if (!empty($changes['days_of_week']) && is_array($changes['days_of_week'])) {
            $allowedDays = array_map('intval', $changes['days_of_week']);
            $dates = array_values(array_filter($dates, static function (string $d) use ($allowedDays): bool {
                $dt = new DateTime($d);
                $isoDay = (int) $dt->format('N'); // 1 (Mon) - 7 (Sun)
                $wDay   = (int) $dt->format('w'); // 0 (Sun) - 6 (Sat)
                return in_array($isoDay, $allowedDays, true) || in_array($wDay, $allowedDays, true);
            }));
        }

        if (empty($dates)) {
            return [
                'success' => false,
                'message' => __('No dates matched the day-of-week filter.', 'tourivo'),
            ];
        }

        $defaultCapacity = $this->getDefaultCapacity($itemId, $itemType);
        $recordsToUpdate = [];

        // Pre-validate all dates against existing bookings and holds
        foreach ($dates as $date) {
            $record = $this->repository->getRecord($itemId, $itemType, $date, $timeSlot);

            $curCapacity = $record ? (int) $record->total_capacity : $defaultCapacity;
            $curBooked   = $record ? (int) $record->booked_count : 0;
            $curReserved = $record ? (int) $record->reserved_count : 0;
            $curStatus   = $record ? (string) $record->status : 'available';
            $curPrice    = ($record && $record->price_override !== null) ? (float) $record->price_override : null;

            $targetCapacity = isset($changes['capacity']) ? (int) $changes['capacity'] : $curCapacity;
            $targetStatus   = (isset($changes['status']) && in_array($changes['status'], ['available', 'blocked'], true))
                ? (string) $changes['status']
                : $curStatus;

            $targetPrice = $curPrice;
            if (!empty($changes['reset_price'])) {
                $targetPrice = null;
            } elseif (isset($changes['price_override']) && $changes['price_override'] !== '' && $changes['price_override'] !== null) {
                $targetPrice = (float) $changes['price_override'];
            }

            $activeSpots = $curBooked + $curReserved;

            // 1. Cannot reduce capacity below active spots
            if (isset($changes['capacity']) && $targetCapacity < $activeSpots) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        /* translators: 1: Active booked count, 2: Date */
                        __('Cannot set capacity below active bookings (%1$d booked/held on %2$s).', 'tourivo'),
                        $activeSpots,
                        $date
                    ),
                ];
            }

            // 2. Cannot block date with active bookings unless forced
            if ($targetStatus === 'blocked' && $activeSpots > 0 && !$force) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        /* translators: 1: Date, 2: Active booked count */
                        __('Date %1$s has active bookings (%2$d booked/held). Use the "Force Block" option to block future bookings without cancelling existing ones.', 'tourivo'),
                        $date,
                        $activeSpots
                    ),
                ];
            }

            $recordsToUpdate[] = [
                'date'           => $date,
                'capacity'       => $targetCapacity,
                'booked_count'   => $curBooked,
                'reserved_count' => $curReserved,
                'price_override' => $targetPrice,
                'status'         => $targetStatus,
            ];
        }

        // Execute batch upsert inside database transaction
        global $wpdb;
        if ($wpdb) {
            $wpdb->query('START TRANSACTION');
        }

        $updatedCount = 0;
        foreach ($recordsToUpdate as $r) {
            $ok = $this->repository->upsert(
                $itemId,
                $itemType,
                $r['date'],
                $timeSlot,
                $r['capacity'],
                $r['booked_count'],
                $r['reserved_count'],
                $r['price_override'],
                $r['status']
            );

            if (!$ok) {
                if ($wpdb) {
                    $wpdb->query('ROLLBACK');
                }
                return [
                    'success' => false,
                    /* translators: %s: Date */
                    'message' => sprintf(__('Failed to save availability record for %s.', 'tourivo'), $r['date']),
                ];
            }
            $updatedCount++;
        }

        if ($wpdb) {
            $wpdb->query('COMMIT');
        }

        // 1. Invalidate SEO structured data cache
        SeoService::clearItemAvailabilityCache($itemId, $itemType);

        // 2. Audit log entry
        $fromStr = $dates[0];
        $toStr   = end($dates);
        $summaryParts = [];
        if (isset($changes['status'])) {
            $summaryParts[] = "status: {$changes['status']}";
        }
        if (isset($changes['capacity'])) {
            $summaryParts[] = "capacity: {$changes['capacity']}";
        }
        if (!empty($changes['reset_price'])) {
            $summaryParts[] = 'price: reset to default';
        } elseif (isset($changes['price_override']) && $changes['price_override'] !== '') {
            $summaryParts[] = 'price override: ' . Money::format((float) $changes['price_override']);
        }
        if ($force) {
            $summaryParts[] = 'force: true';
        }
        $changeSummary = implode(', ', $summaryParts);

        $logMsg = sprintf(
            'Updated availability for %s #%d (%s to %s, %d days): %s',
            $itemType,
            $itemId,
            $fromStr,
            $toStr,
            $updatedCount,
            $changeSummary
        );
        LogService::log(0, 'availability_update', $logMsg, get_current_user_id());

        // 3. Fire developer action hook
        do_action('tourivo/availability_updated', $itemId, $itemType, $fromStr, $toStr, $changes);

        return [
            'success'       => true,
            'message'       => sprintf(
                /* translators: %d: Number of updated days */
                __('Availability successfully updated for %d date(s).', 'tourivo'),
                $updatedCount
            ),
            'updated_count' => $updatedCount,
            'updated_dates' => $dates,
        ];
    }

    /**
     * Get rich admin availability grid for calendar management.
     *
     * @param int    $itemId
     * @param string $itemType
     * @param int    $year
     * @param int    $month
     * @param string $timeSlot
     * @return array<string, array<string, mixed>>
     */
    public function getAdminCalendarAvailability(
        int $itemId,
        string $itemType,
        int $year,
        int $month,
        string $timeSlot = 'all_day'
    ): array {
        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
        $defaultCapacity = $this->getDefaultCapacity($itemId, $itemType);

        $basePrice = 0.0;
        if ($itemType === 'tour') {
            $tour = new Tour($itemId);
            $basePrice = $tour->getActivePrice();
        } else {
            $room = new Room($itemId);
            $basePrice = $room->getNightlyPrice();
        }

        $currentYear = (int) wp_date('Y');
        $year  = max($currentYear - 1, min($currentYear + 5, $year));
        $month = max(1, min(12, $month));

        $daysInMonth = (int) gmdate('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $records = $this->repository->getRecordsInRange($itemId, $itemType, $startDate, $endDate, $timeSlot);
        $recordsByDate = [];
        foreach ($records as $rec) {
            $recordsByDate[$rec->event_date] = $rec;
        }

        $todayStr = wp_date('Y-m-d');
        $calendar = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $record = $recordsByDate[$dateStr] ?? null;

            $capacity = $record ? (int) $record->total_capacity : $defaultCapacity;
            $booked   = $record ? (int) $record->booked_count : 0;
            $reserved = $record ? (int) $record->reserved_count : 0;
            $status   = $record ? (string) $record->status : 'available';
            $priceOverride = ($record && $record->price_override !== null) ? (float) $record->price_override : null;

            $effectivePrice = $priceOverride !== null ? $priceOverride : $basePrice;
            $spotsLeft = max(0, $capacity - $booked - $reserved);

            if ($status === 'available' && $spotsLeft <= 0) {
                $status = 'sold_out';
            }

            $dt = new DateTime($dateStr);
            $dayOfWeek = (int) $dt->format('N'); // 1 = Monday, 7 = Sunday

            $calendar[$dateStr] = [
                'date'            => $dateStr,
                'day'             => $day,
                'day_of_week'     => $dayOfWeek,
                'status'          => $status,
                'total_capacity'  => $capacity,
                'booked_count'    => $booked,
                'reserved_count'  => $reserved,
                'available_spots' => $spotsLeft,
                'price_override'  => $priceOverride,
                'base_price'      => $basePrice,
                'effective_price' => $effectivePrice,
                'formatted_price' => Money::format($effectivePrice),
                'is_past'         => $dateStr < $todayStr,
                'is_today'        => $dateStr === $todayStr,
            ];
        }

        return $calendar;
    }
}
