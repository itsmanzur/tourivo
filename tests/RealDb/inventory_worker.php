<?php
/**
 * One concurrent "customer" for the real-MySQL inventory test.
 *
 * Usage (spawned by run_inventory_concurrency.php, not meant to be run by hand):
 *   php inventory_worker.php <workerId> <runDir> <action> <itemId> <date> <count> <defaultCapacity>
 *
 * It connects, signals readiness, spins until the orchestrator creates "<runDir>/go", then performs exactly one
 * repository call (or, for book_loop / shrink_loop, TRV_LOOPS of them) and prints a single JSON line with the
 * outcome.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require __DIR__ . '/RealWpdb.php';
require getenv('TRV_REPO_FILE') ?: dirname(__DIR__, 2) . '/app/Repositories/InventoryRepository.php';

[, $workerId, $runDir, $action, $itemId, $date, $count, $defaultCapacity] = $argv;

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3306);

$GLOBALS['wpdb'] = new wpdb(
    $host,
    $port,
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : 'root',
    (string) getenv('DB_NAME'),
    (string) getenv('TABLE_PREFIX')
);

$repo = new \Tourivo\Repositories\InventoryRepository();

file_put_contents("{$runDir}/ready_{$workerId}", '1');
while (!file_exists("{$runDir}/go")) {
    usleep(500);
}

/**
 * Read the row and count it if more seats are in use than exist (the invariant every action must keep).
 */
$overbooked = static function () use ($itemId, $date): ?string {
    $db  = $GLOBALS['wpdb'];
    $row = $db->get_row($db->prepare(
        "SELECT total_capacity, booked_count, reserved_count FROM {$db->prefix}tourivo_inventories
         WHERE item_id = %d AND item_type = 'tour' AND event_date = %s AND time_slot = 'all_day'",
        (int) $itemId,
        $date
    ));
    if ($row && (int) $row->booked_count + (int) $row->reserved_count > (int) $row->total_capacity) {
        return "booked {$row->booked_count} + reserved {$row->reserved_count} > capacity {$row->total_capacity}";
    }
    return null;
};

$loops      = max(1, (int) (getenv('TRV_LOOPS') ?: 200));
$violations = [];
$tally      = [];

switch ($action) {
    case 'commit':
        $ok = $repo->commitSpots((int) $itemId, 'tour', $date, 'all_day', (int) $count, (int) $defaultCapacity, false);
        break;
    case 'reserve':
        $ok = $repo->reserveSpots((int) $itemId, 'tour', $date, 'all_day', (int) $count, (int) $defaultCapacity);
        break;
    case 'upsert':
        // <count> is the new capacity.
        $ok = $repo->upsert((int) $itemId, 'tour', $date, 'all_day', (int) $count);
        $tally[$ok ? 'ok' : $repo->lastError] = 1;
        break;
    case 'book_loop':
        // A stream of customers: hold a seat, turn it into a booking, and now and then cancel one.
        for ($i = 0; $i < $loops; $i++) {
            if ($repo->reserveSpots((int) $itemId, 'tour', $date, 'all_day', 1, (int) $defaultCapacity)) {
                $committed = $repo->commitSpots((int) $itemId, 'tour', $date, 'all_day', 1, (int) $defaultCapacity, true);
                if (!$committed) {
                    $repo->releaseSpots((int) $itemId, 'tour', $date, 'all_day', 1);
                }
                $tally[$committed ? 'booked' : 'commit_refused'] = ($tally[$committed ? 'booked' : 'commit_refused'] ?? 0) + 1;
            } else {
                $tally['hold_refused'] = ($tally['hold_refused'] ?? 0) + 1;
            }
            if ($i % 2 === 1) {
                $repo->releaseBooked((int) $itemId, 'tour', $date, 'all_day', 1);
            }
            if (($v = $overbooked()) !== null) {
                $violations[] = "book_loop #{$i}: {$v}";
            }
        }
        $ok = true;
        break;
    case 'shrink_loop':
        // An admin editing the calendar: capacities between 0 and <count>.
        mt_srand((int) $workerId + 1);
        for ($i = 0; $i < $loops; $i++) {
            $saved = $repo->upsert((int) $itemId, 'tour', $date, 'all_day', mt_rand(0, (int) $count));
            $key   = $saved ? 'saved' : $repo->lastError;
            $tally[$key] = ($tally[$key] ?? 0) + 1;
            if (($v = $overbooked()) !== null) {
                $violations[] = "shrink_loop #{$i}: {$v}";
            }
        }
        $ok = true;
        break;
    default:
        $ok = false;
}

echo json_encode([
    'worker'     => (int) $workerId,
    'action'     => $action,
    'ok'         => $ok,
    'errors'     => $GLOBALS['wpdb']->errors,
    'tally'      => $tally,
    'violations' => array_slice($violations, 0, 20),
]), "\n";
