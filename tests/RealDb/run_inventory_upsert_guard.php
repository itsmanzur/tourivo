<?php
/**
 * Real-MySQL / MariaDB verification of the InventoryRepository::upsert() capacity guard.
 *
 * The mocked suite only imitates ON DUPLICATE KEY UPDATE. This script runs the real statement:
 *   1. Fixed cases on one row (capacity 5, booked 3, held 1):
 *      - upsert(capacity 3) is refused: false, lastError 'capacity_below_active', row unchanged;
 *      - upsert(capacity 4) and upsert(capacity 10) succeed;
 *      - the same values twice within one second (0 rows affected) still return true without an error.
 *   2. Two processes at once, RUNS times: one keeps booking and cancelling seats, the other keeps setting random
 *      capacities. After every single operation both check that booked + reserved never exceeds the capacity.
 *
 * Usage:
 *   php tests/RealDb/run_inventory_upsert_guard.php
 *
 * Environment: the same as run_inventory_concurrency.php (DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME,
 * DB_CREATE, TABLE_PREFIX, RUNS, TRV_PHP_FLAGS, TRV_TMP_DIR), plus TRV_LOOPS=200 (operations per process and run).
 *
 * Exit code 0 = every check held.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require __DIR__ . '/RealWpdb.php';
require dirname(__DIR__, 2) . '/app/Database/Migrations/MigrationInterface.php';
require dirname(__DIR__, 2) . '/app/Database/Migrations/CreateInventoriesTable.php';
require getenv('TRV_REPO_FILE') ?: dirname(__DIR__, 2) . '/app/Repositories/InventoryRepository.php';

$host   = getenv('DB_HOST') ?: '127.0.0.1';
$port   = (int) (getenv('DB_PORT') ?: 3306);
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : 'root';
$dbName = getenv('DB_NAME') ?: 'tourivo_test';
$prefix = getenv('TABLE_PREFIX') ?: 'trvug_';
$runs   = max(1, (int) (getenv('RUNS') ?: 5));
$create = getenv('DB_CREATE') === '1';
$flags  = getenv('TRV_PHP_FLAGS') ? explode('|', (string) getenv('TRV_PHP_FLAGS')) : [];

echo "=====================================================\n";
echo "  Tourivo real-DB inventory upsert guard test\n";
echo "=====================================================\n";

mysqli_report(MYSQLI_REPORT_OFF);
$admin = new mysqli($host, $user, $pass, '', $port);
if ($admin->connect_errno) {
    fwrite(STDERR, "Cannot connect to MySQL at {$host}:{$port}: {$admin->connect_error}\n");
    exit(2);
}
echo 'Server: ' . $admin->server_info . " | runs: {$runs}\n";

if ($create) {
    $admin->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4");
}

$GLOBALS['wpdb'] = $db = new wpdb($host, $port, $user, $pass, $dbName, $prefix);
$table     = $prefix . 'tourivo_inventories';
$migration = new \Tourivo\Database\Migrations\CreateInventoriesTable();

$resetTable = static function () use ($db, $migration, $prefix): void {
    $db->query("DROP TABLE IF EXISTS `{$prefix}tourivo_inventories`");
    $db->query($migration->up($prefix . 'tourivo_', 'DEFAULT CHARACTER SET utf8mb4'));
};

$failures = [];
$check = static function (string $label, bool $passed, string $detail = '') use (&$failures): void {
    echo ($passed ? '  ✓ ' : '  ✗ ') . $label . ($passed || $detail === '' ? '' : " — {$detail}") . "\n";
    if (!$passed) {
        $failures[] = $label . ($detail === '' ? '' : " — {$detail}");
    }
};

$date   = gmdate('Y-m-d', strtotime('+30 days'));
$itemId = 501;

$row = static function () use ($db, $table, $itemId, $date): ?object {
    return $db->get_row($db->prepare(
        "SELECT total_capacity, booked_count, reserved_count, price_override, status, updated_at FROM `{$table}`
         WHERE item_id = %d AND item_type = 'tour' AND event_date = %s AND time_slot = 'all_day'",
        $itemId,
        $date
    ));
};

// ---------------------------------------------------------------------------------------------------------------
echo "\n▶ Fixed cases (capacity 5, booked 3, held 1)\n";
$resetTable();
$db->query($db->prepare(
    "INSERT INTO `{$table}` (item_id, item_type, event_date, time_slot, total_capacity, booked_count, reserved_count, status, created_at, updated_at)
     VALUES (%d, 'tour', %s, 'all_day', 5, 3, 1, 'available', '2026-01-01 00:00:00', '2026-01-01 00:00:00')",
    $itemId,
    $date
));
$repo   = new \Tourivo\Repositories\InventoryRepository();
$before = $row();

$ok = $repo->upsert($itemId, 'tour', $date, 'all_day', 3, 0, 0, 50.0, 'blocked');
$after = $row();
$check('upsert(3) returns false', $ok === false);
$check("lastError is 'capacity_below_active'", $repo->lastError === 'capacity_below_active', "got '{$repo->lastError}'");
$check('row unchanged (capacity, counts, price, status, updated_at)', $after == $before, json_encode([$before, $after]));

$ok = $repo->upsert($itemId, 'tour', $date, 'all_day', 4);
$check('upsert(4) succeeds (= booked + held)', $ok === true && (int) $row()->total_capacity === 4, "ok=" . var_export($ok, true) . " lastError='{$repo->lastError}'");

$ok = $repo->upsert($itemId, 'tour', $date, 'all_day', 10);
$check('upsert(10) succeeds', $ok === true && (int) $row()->total_capacity === 10, "ok=" . var_export($ok, true) . " lastError='{$repo->lastError}'");
$check('booked/held counts untouched by upserts', (int) $row()->booked_count === 3 && (int) $row()->reserved_count === 1);

// Same values twice inside one second: the second write changes nothing (0 rows affected) and must not be
// mistaken for a refused cut. Start right after a second boundary so both statements share NOW().
$sameSecond = false;
for ($try = 0; $try < 5 && !$sameSecond; $try++) {
    $t = time();
    while (time() === $t) {
        usleep(1000);
    }
    $start  = time();
    $first  = $repo->upsert($itemId, 'tour', $date, 'all_day', 10, 0, 0, 20.0, 'available');
    $second = $repo->upsert($itemId, 'tour', $date, 'all_day', 10, 0, 0, 20.0, 'available');
    $affected   = $db->rows_affected;
    $sameSecond = time() === $start;
}
$check('same value twice in one second: both true, no error', $first === true && $second === true && $repo->lastError === '', "first=" . var_export($first, true) . " second=" . var_export($second, true) . " lastError='{$repo->lastError}'");
$check('second write really was a no-op (0 rows affected, same second)', $sameSecond && $affected === 0, "rows_affected={$affected} sameSecond=" . var_export($sameSecond, true));

$ok = $repo->upsert($itemId + 1, 'tour', $date, 'all_day', 2);
$check('upsert on a new date inserts the row', $ok === true && $repo->lastError === '');

// ---------------------------------------------------------------------------------------------------------------
echo "\n▶ Two processes: bookings vs. capacity cuts ({$runs} runs × " . (getenv('TRV_LOOPS') ?: 200) . " operations each)\n";

$env = array_merge(getenv(), [
    'DB_HOST' => $host, 'DB_PORT' => (string) $port, 'DB_USER' => $user, 'DB_PASSWORD' => $pass,
    'DB_NAME' => $dbName, 'TABLE_PREFIX' => $prefix,
]);

$totals = [];
for ($run = 1; $run <= $runs; $run++) {
    $resetTable();
    $raceItem = 2000 + $run;
    $db->query($db->prepare(
        "INSERT INTO `{$table}` (item_id, item_type, event_date, time_slot, total_capacity, booked_count, reserved_count, status, created_at, updated_at)
         VALUES (%d, 'tour', %s, 'all_day', 6, 0, 0, 'available', NOW(), NOW())",
        $raceItem,
        $date
    ));

    $runDir = (getenv('TRV_TMP_DIR') ?: sys_get_temp_dir()) . '/trv_ug_' . bin2hex(random_bytes(6));
    mkdir($runDir);

    // book_loop: default capacity 6. shrink_loop: random capacities 0..8.
    $workers = [['book_loop', 1, 6], ['shrink_loop', 8, 6]];
    $procs   = [];
    foreach ($workers as $i => [$action, $count, $defaultCapacity]) {
        $cmd = array_merge([PHP_BINARY], $flags, [
            __DIR__ . '/inventory_worker.php', (string) $i, $runDir, $action, (string) $raceItem, $date, (string) $count, (string) $defaultCapacity,
        ]);
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($p)) {
            throw new RuntimeException('Could not spawn worker.');
        }
        $procs[$i] = ['proc' => $p, 'pipes' => $pipes];
    }

    $deadline = microtime(true) + 60;
    while (count(glob($runDir . '/ready_*') ?: []) < count($workers)) {
        if (microtime(true) > $deadline) {
            $stderr = '';
            foreach ($procs as $p) {
                proc_terminate($p['proc']);
                $stderr .= stream_get_contents($p['pipes'][2]) . stream_get_contents($p['pipes'][1]);
            }
            throw new RuntimeException("Workers did not become ready (check extensions / credentials). Output: {$stderr}");
        }
        usleep(2000);
    }
    touch($runDir . '/go');

    $violations = [];
    foreach ($procs as $i => $p) {
        $out = stream_get_contents($p['pipes'][1]);
        $err = stream_get_contents($p['pipes'][2]);
        proc_close($p['proc']);
        // Only the last line is the result: PHP may print startup warnings first.
        $lines = preg_split('/\r?\n/', trim((string) $out));
        $line  = json_decode((string) end($lines), true);
        if (!is_array($line)) {
            throw new RuntimeException("Worker {$i} produced no result. stderr: {$err} stdout: {$out}");
        }
        foreach ($line['tally'] as $k => $n) {
            $totals[$line['action'] . ':' . $k] = ($totals[$line['action'] . ':' . $k] ?? 0) + $n;
        }
        array_push($violations, ...$line['violations']);
    }
    array_map('unlink', glob($runDir . '/*') ?: []);
    rmdir($runDir);

    $final = $db->get_row($db->prepare("SELECT * FROM `{$table}` WHERE item_id = %d", $raceItem));
    $finalOk = $final && (int) $final->booked_count + (int) $final->reserved_count <= (int) $final->total_capacity;
    $check("run {$run}: booked + reserved never exceeded capacity", $violations === [] && $finalOk, implode('; ', array_slice($violations, 0, 3)) . ($finalOk ? '' : ' final=' . json_encode($final)));
}

ksort($totals);
echo '  Operations: ' . json_encode($totals) . "\n";
$check(
    'the race was real: cuts were both saved and refused, seats were booked',
    ($totals['shrink_loop:saved'] ?? 0) > 0 && ($totals['shrink_loop:capacity_below_active'] ?? 0) > 0 && ($totals['book_loop:booked'] ?? 0) > 0
);

$db->query("DROP TABLE IF EXISTS `{$table}`");
if ($create) {
    $admin->query("DROP DATABASE IF EXISTS `{$dbName}`");
}

if ($failures) {
    echo "\n✗ " . count($failures) . " failure(s).\n";
    exit(1);
}

echo "\n✓ Upsert guard held on " . $admin->server_info . ".\n";
exit(0);
