<?php

declare(strict_types=1);

namespace Tourivo\Repositories;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class InventoryRepository
 *
 * Data Access Layer for the wp_tourivo_inventories table with row-level transaction safety.
 *
 * @package Tourivo\Repositories
 */
class InventoryRepository
{
    /**
     * The table name with prefix.
     *
     * @var string
     */
    protected string $table;

    /**
     * WordPress Database object.
     *
     * @var wpdb|null
     */
    protected ?wpdb $db = null;

    /**
     * InventoryRepository constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = ($wpdb ? $wpdb->prefix : 'wp_') . 'tourivo_inventories';
    }

    /**
     * Get inventory record for a specific item, date and slot.
     *
     * @param int    $itemId
     * @param string $itemType
     * @param string $date (Y-m-d)
     * @param string $timeSlot
     * @return object|null
     */
    public function getRecord(int $itemId, string $itemType, string $date, string $timeSlot = 'all_day'): ?object
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE item_id = %d AND item_type = %s AND event_date = %s AND time_slot = %s 
             LIMIT 1",
            $itemId,
            $itemType,
            $date,
            $timeSlot
        );

        $row = $this->db->get_row($query);
        return $row ?: null;
    }

    /**
     * Get inventory records for a date range.
     *
     * @param int    $itemId
     * @param string $itemType
     * @param string $startDate (Y-m-d)
     * @param string $endDate   (Y-m-d)
     * @param string $timeSlot
     * @return array<object>
     */
    public function getRecordsInRange(int $itemId, string $itemType, string $startDate, string $endDate, string $timeSlot = 'all_day'): array
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE item_id = %d AND item_type = %s AND event_date >= %s AND event_date <= %s AND time_slot = %s 
             ORDER BY event_date ASC",
            $itemId,
            $itemType,
            $startDate,
            $endDate,
            $timeSlot
        );

        $results = $this->db->get_results($query);
        return is_array($results) ? $results : [];
    }

    /**
     * Insert or update an inventory capacity record.
     *
     * @param int         $itemId
     * @param string      $itemType
     * @param string      $date
     * @param string      $timeSlot
     * @param int         $totalCapacity
     * @param int         $bookedCount
     * @param int         $reservedCount
     * @param float|null  $priceOverride
     * @param string      $status
     * @return bool
     */
    public function upsert(
        int $itemId,
        string $itemType,
        string $date,
        string $timeSlot,
        int $totalCapacity,
        int $bookedCount = 0,
        int $reservedCount = 0,
        ?float $priceOverride = null,
        string $status = 'available'
    ): bool {
        $priceVal = $priceOverride !== null ? $priceOverride : 'NULL';
        $priceSql = $priceOverride !== null ? '%f' : 'NULL';

        $query = $this->db->prepare(
            "INSERT INTO {$this->table} 
                (item_id, item_type, event_date, time_slot, total_capacity, booked_count, reserved_count, price_override, status, created_at, updated_at)
             VALUES 
                (%d, %s, %s, %s, %d, %d, %d, {$priceSql}, %s, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                total_capacity = VALUES(total_capacity),
                price_override = VALUES(price_override),
                status = VALUES(status),
                updated_at = NOW()",
            $itemId,
            $itemType,
            $date,
            $timeSlot,
            $totalCapacity,
            $bookedCount,
            $reservedCount,
            ...($priceOverride !== null ? [$priceOverride, $status] : [$status])
        );

        return $this->db->query($query) !== false;
    }

    /**
     * Atomically reserve inventory spots (Temporary Checkout Hold).
     *
     * @param int    $itemId
     * @param string $itemType
     * @param string $date
     * @param string $timeSlot
     * @param int    $count
     * @param int    $defaultCapacity
     * @return bool
     */
    public function reserveSpots(int $itemId, string $itemType, string $date, string $timeSlot, int $count, int $defaultCapacity): bool
    {
        $this->db->query('START TRANSACTION');

        // Fetch row with row lock
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE item_id = %d AND item_type = %s AND event_date = %s AND time_slot = %s 
             FOR UPDATE",
            $itemId,
            $itemType,
            $date,
            $timeSlot
        );

        $row = $this->db->get_row($query);

        $totalCapacity = $row ? (int) $row->total_capacity : $defaultCapacity;
        $bookedCount   = $row ? (int) $row->booked_count : 0;
        $reservedCount = $row ? (int) $row->reserved_count : 0;
        $status        = $row ? $row->status : 'available';

        if ($status !== 'available') {
            $this->db->query('ROLLBACK');
            return false;
        }

        $availableSpots = $totalCapacity - $bookedCount - $reservedCount;

        if ($availableSpots < $count) {
            $this->db->query('ROLLBACK');
            return false;
        }

        // Apply hold
        if ($row) {
            $update = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET reserved_count = reserved_count + %d, updated_at = NOW() 
                 WHERE id = %d",
                $count,
                $row->id
            );
            $this->db->query($update);
        } else {
            $insert = $this->db->prepare(
                "INSERT INTO {$this->table} 
                    (item_id, item_type, event_date, time_slot, total_capacity, booked_count, reserved_count, status, created_at, updated_at)
                 VALUES 
                    (%d, %s, %s, %s, %d, 0, %d, 'available', NOW(), NOW())",
                $itemId,
                $itemType,
                $date,
                $timeSlot,
                $defaultCapacity,
                $count
            );
            $this->db->query($insert);
        }

        $this->db->query('COMMIT');
        return true;
    }

    /**
     * Atomically commit reserved hold to permanent booked count.
     *
     * @param int    $itemId
     * @param string $itemType
     * @param string $date
     * @param string $timeSlot
     * @param int    $count
     * @param int    $defaultCapacity
     * @param bool   $hasPriorHold
     * @return bool
     */
    public function commitSpots(
        int $itemId,
        string $itemType,
        string $date,
        string $timeSlot,
        int $count,
        int $defaultCapacity,
        bool $hasPriorHold = true
    ): bool {
        $this->db->query('START TRANSACTION');

        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE item_id = %d AND item_type = %s AND event_date = %s AND time_slot = %s 
             FOR UPDATE",
            $itemId,
            $itemType,
            $date,
            $timeSlot
        );

        $row = $this->db->get_row($query);

        if (!$row) {
            if ($count > $defaultCapacity) {
                $this->db->query('ROLLBACK');
                return false;
            }

            $insert = $this->db->prepare(
                "INSERT INTO {$this->table} 
                    (item_id, item_type, event_date, time_slot, total_capacity, booked_count, reserved_count, status, created_at, updated_at)
                 VALUES 
                    (%d, %s, %s, %s, %d, %d, 0, 'available', NOW(), NOW())",
                $itemId,
                $itemType,
                $date,
                $timeSlot,
                $defaultCapacity,
                $count
            );
            $this->db->query($insert);
            $this->db->query('COMMIT');
            return true;
        }

        $totalCapacity = (int) $row->total_capacity;
        $bookedCount   = (int) $row->booked_count;
        $reservedCount = (int) $row->reserved_count;

        if ($hasPriorHold) {
            $newReserved = max(0, $reservedCount - $count);
            $newBooked   = $bookedCount + $count;
        } else {
            $available = $totalCapacity - $bookedCount - $reservedCount;
            if ($available < $count) {
                $this->db->query('ROLLBACK');
                return false;
            }
            $newReserved = $reservedCount;
            $newBooked   = $bookedCount + $count;
        }

        $newStatus = ($newBooked >= $totalCapacity) ? 'sold_out' : $row->status;

        $update = $this->db->prepare(
            "UPDATE {$this->table} 
             SET booked_count = %d, reserved_count = %d, status = %s, updated_at = NOW() 
             WHERE id = %d",
            $newBooked,
            $newReserved,
            $newStatus,
            $row->id
        );
        $this->db->query($update);

        $this->db->query('COMMIT');
        return true;
    }

    /**
     * Release temporary reservation hold.
     *
     * @param int    $itemId
     * @param string $itemType
     * @param string $date
     * @param string $timeSlot
     * @param int    $count
     * @return bool
     */
    public function releaseSpots(int $itemId, string $itemType, string $date, string $timeSlot, int $count): bool
    {
        $query = $this->db->prepare(
            "UPDATE {$this->table} 
             SET reserved_count = GREATEST(0, reserved_count - %d), updated_at = NOW() 
             WHERE item_id = %d AND item_type = %s AND event_date = %s AND time_slot = %s",
            $count,
            $itemId,
            $itemType,
            $date,
            $timeSlot
        );

        return $this->db->query($query) !== false;
    }

    /**
     * Release all expired holds older than given minutes.
     *
     * @param int $minutes
     * @return int Number of reset records
     */
    public function clearExpiredHolds(int $minutes = 15): int
    {
        $query = $this->db->prepare(
            "UPDATE {$this->table} 
             SET reserved_count = 0 
             WHERE reserved_count > 0 AND updated_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $minutes
        );

        $res = $this->db->query($query);
        return is_numeric($res) ? (int) $res : 0;
    }

    /**
     * Atomically release permanently booked spots when a booking is cancelled.
     *
     * @param int    $itemId
     * @param string $itemType
     * @param string $date (Y-m-d)
     * @param string $timeSlot
     * @param int    $count
     * @return bool
     */
    public function releaseBooked(int $itemId, string $itemType, string $date, string $timeSlot, int $count): bool
    {
        $this->db->query('START TRANSACTION');

        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE item_id = %d AND item_type = %s AND event_date = %s AND time_slot = %s 
             FOR UPDATE",
            $itemId,
            $itemType,
            $date,
            $timeSlot
        );

        $row = $this->db->get_row($query);
        if ($row) {
            $newBooked = max(0, ((int) $row->booked_count) - $count);
            $newStatus = ($newBooked < (int) $row->total_capacity && $row->status === 'sold_out') ? 'available' : $row->status;

            $update = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET booked_count = %d, status = %s, updated_at = NOW() 
                 WHERE id = %d",
                $newBooked,
                $newStatus,
                $row->id
            );
            $this->db->query($update);
        }

        $this->db->query('COMMIT');
        return true;
    }
}
