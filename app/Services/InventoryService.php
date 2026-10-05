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
        int $requestedCount = 1
    ): array {
        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');
        $defaultCapacity = $this->getDefaultCapacity($itemId, $itemType);
        $dates = $this->generateDateList($startDate, $endDate);

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

        $minAvailableSpots = PHP_INT_MAX;
        $totalCalculatedPrice = 0.0;
        $allAvailable = true;

        foreach ($dates as $date) {
            $record = $this->repository->getRecord($itemId, $itemType, $date, $timeSlot);

            $capacity = $record ? (int) $record->total_capacity : $defaultCapacity;
            $booked   = $record ? (int) $record->booked_count : 0;
            $reserved = $record ? (int) $record->reserved_count : 0;
            $status   = $record ? $record->status : 'available';

            $spotsLeft = max(0, $capacity - $booked - $reserved);
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
     * Hold spots during checkout with unique hold token.
     *
     * @param int         $itemId
     * @param string      $itemType
     * @param string      $startDate
     * @param string|null $endDate
     * @param string      $timeSlot
     * @param int         $count
     * @param int         $ttlMinutes
     * @return string|false Hold token or false
     */
    public function holdInventory(
        int $itemId,
        string $itemType,
        string $startDate,
        ?string $endDate = null,
        string $timeSlot = 'all_day',
        int $count = 1,
        int $ttlMinutes = 15
    ): string|false {
        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
        $dates = $this->generateDateList($startDate, $endDate);
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
        $holdData = [
            'item_id'    => $itemId,
            'item_type'  => $itemType,
            'dates'      => $dates,
            'time_slot'  => $timeSlot,
            'count'      => $count,
            'expires_at' => time() + ($ttlMinutes * 60),
        ];

        set_transient($holdToken, $holdData, $ttlMinutes * 60);

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
     * @return bool
     */
    public function commitBooking(
        int $itemId,
        string $itemType,
        string $startDate,
        ?string $endDate = null,
        string $timeSlot = 'all_day',
        int $count = 1,
        ?string $holdToken = null
    ): bool {
        $timeSlot = self::normalizeTimeSlot($timeSlot);
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
        $dates = $this->generateDateList($startDate, $endDate);
        if (empty($dates)) {
            return false;
        }

        $defaultCapacity = $this->getDefaultCapacity($itemId, $itemType);
        $hasPriorHold = !empty($holdToken) && get_transient($holdToken) !== false;

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

        if ($hasPriorHold && !empty($holdToken)) {
            delete_transient($holdToken);
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
        $data = get_transient($holdToken);
        if (!is_array($data)) {
            return false;
        }

        $itemId   = (int) ($data['item_id'] ?? 0);
        $itemType = (string) ($data['item_type'] ?? 'tour');
        $itemType = ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour';
        $dates    = (array) ($data['dates'] ?? []);
        $timeSlot = (string) ($data['time_slot'] ?? 'all_day');
        $count    = (int) ($data['count'] ?? 1);

        foreach ($dates as $date) {
            $this->repository->releaseSpots($itemId, $itemType, $date, $timeSlot, $count);
        }

        delete_transient($holdToken);
        return true;
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
}
