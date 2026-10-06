<?php
/**
 * Real-MySQL concurrency verification for InventoryRepository.
 *
 * The mocked unit suite cannot prove that SELECT ... FOR UPDATE, gap locks, duplicate-key races and deadlock
 * retries really prevent overbooking. This script does: it fires many truly parallel OS processes (each with its
 * own MySQL connection) at the same inventory row and asserts that no more seats are sold than exist.
 *
 * Usage:
 *   php tests/RealDb/run_inventory_concurrency.php
 *
 * Environment:
 *   DB_HOST=127.0.0.1  DB_PORT=3306  DB_USER=root  DB_PASSWORD=root
 *   DB_NAME=tourivo_test     database to use (must exist unless DB_CREATE=1)
 *   DB_CREATE=1              create DB_NAME first and drop it afterwards
 *   TABLE_PREFIX=trvct_      prefix for the scratch table (default: trvct_)
 *   RUNS=5                   repetitions of every scenario (default 5)
 *   WORKERS=12               parallel workers for the contention scenarios (default 12)
 *   TRV_PHP_FLAGS=           extra php CLI args for workers, "|"-separated (e.g. "-d|extension=mysqli")
 *
 * Exit code 0 = no overbooking / lost update observed in any run.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require __DIR__ . '/RealWpdb.php';
require dirname(__DIR__, 2) . '/app/Database/Migrations/MigrationInterface.php';
require dirname(__DIR__, 2) . '/app/Database/Migrations/CreateInventoriesTable.php';

$host   = getenv('DB_HOST') ?: '127.0.0.1';
$port   = (int) (getenv('DB_PORT') ?: 3306);
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : 'root';
$dbName = getenv('DB_NAME') ?: 'tourivo_test';
$prefix = getenv('TABLE_PREFIX') ?: 'trvct_';
$runs   = max(1, (int) (getenv('RUNS') ?: 5));
$nWork  = max(4, (int) (getenv('WORKERS') ?: 12));
$create = getenv('DB_CREATE') === '1';
$flags  = getenv('TRV_PHP_FLAGS') ? explode('|', (string) getenv('TRV_PHP_FLAGS')) : [];

echo "=====================================================\n";
echo "  Tourivo real-MySQL inventory concurrency test\n";
echo "=====================================================\n";

mysqli_report(MYSQLI_REPORT_OFF);
$admin = new mysqli($host, $user, $pass, '', $port);
if ($admin->connect_errno) {
    fwrite(STDERR, "Cannot connect to MySQL at {$host}:{$port}: {$admin->connect_error}\n");
    exit(2);
}
echo 'Server: MySQL ' . $admin->server_info . " | runs per scenario: {$runs} | workers: {$nWork}\n";

if ($create) {
    $admin->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4");
}

$db = new wpdb($host, $port, $user, $pass, $dbName, $prefix);
$table = $prefix . 'tourivo_inventories';
$engine = (string) $db->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'");

$migration = new \Tourivo\Database\Migrations\CreateInventoriesTable();

function resetTable(wpdb $db, \Tourivo\Database\Migrations\CreateInventoriesTable $m, string $prefix): void
{
    $db->query("DROP TABLE IF EXISTS `{$prefix}tourivo_inventories`");
    $db->query($m->up($prefix . 'tourivo_', 'DEFAULT CHARACTER SET utf8mb4'));
}

/**
 * Run one scenario once. $workers is a list of [action, count] pairs fired simultaneously.
 *
 * @return array{oks: int, okByAction: array<string,int>, errors: array<int,string>, rows: array<int,object>}
 */
function fire(array $workers, int $itemId, string $date, int $defaultCapacity, array $flags, string $dbName, string $prefix, string $host, int $port, string $user, string $pass, wpdb $db): array
{
    $runDir = sys_get_temp_dir() . '/trv_inv_' . bin2hex(random_bytes(6));
    mkdir($runDir);

    $env = array_merge(getenv(), [
        'DB_HOST' => $host, 'DB_PORT' => (string) $port, 'DB_USER' => $user, 'DB_PASSWORD' => $pass,
        'DB_NAME' => $dbName, 'TABLE_PREFIX' => $prefix,
    ]);

    $procs = [];
    foreach ($workers as $i => [$action, $count]) {
        $cmd = array_merge([PHP_BINARY], $flags, [
            __DIR__ . '/inventory_worker.php', (string) $i, $runDir, $action, (string) $itemId, $date, (string) $count, (string) $defaultCapacity,
        ]);
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($p)) {
            throw new RuntimeException('Could not spawn worker.');
        }
        $procs[$i] = ['proc' => $p, 'pipes' => $pipes];
    }

    // Barrier: wait until every worker holds an open connection, then release them together.
    $deadline = microtime(true) + 60;
    while (count(glob($runDir . '/ready_*') ?: []) < count($workers)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Workers did not become ready (check extensions / credentials).');
        }
        usleep(2000);
    }
    touch($runDir . '/go');

    $oks = 0;
    $byAction = [];
    $errors = [];
    foreach ($procs as $i => $p) {
        $out = stream_get_contents($p['pipes'][1]);
        $err = stream_get_contents($p['pipes'][2]);
        proc_close($p['proc']);

        $line = json_decode(trim((string) $out), true);
        if (!is_array($line)) {
            throw new RuntimeException("Worker {$i} produced no result. stderr: {$err} stdout: {$out}");
        }
        if ($line['ok']) {
            $oks++;
            $byAction[$line['action']] = ($byAction[$line['action']] ?? 0) + 1;
        }
        foreach ($line['errors'] as $e) {
            $errors[] = $e;
        }
    }

    array_map('unlink', glob($runDir . '/*') ?: []);
    rmdir($runDir);

    $rows = $db->get_results($db->prepare(
        "SELECT * FROM `{$prefix}tourivo_inventories` WHERE item_id = %d AND event_date = %s",
        $itemId,
        $date
    ));

    return ['oks' => $oks, 'okByAction' => $byAction, 'errors' => $errors, 'rows' => $rows];
}

$failures = [];
$deadlocks = 0;
$date = gmdate('Y-m-d', strtotime('+30 days'));

/** @param array<string, callable> $scenarios */
$scenarios = [
    'fresh date: commit race (capacity 5)' => [
        'workers' => array_fill(0, $nWork, ['commit', 1]),
        'seed'    => null,
        'cap'     => 5,
        'check'   => function (array $r): array {
            $row = $r['rows'][0] ?? null;
            return [
                'exactly 5 sold'           => $r['oks'] === 5,
                'booked_count == 5'        => $row && (int) $row->booked_count === 5,
                'single inventory row'     => count($r['rows']) === 1,
                'status sold_out'          => $row && $row->status === 'sold_out',
            ];
        },
    ],
    'fresh date: hold race (capacity 5)' => [
        'workers' => array_fill(0, $nWork, ['reserve', 1]),
        'seed'    => null,
        'cap'     => 5,
        'check'   => function (array $r): array {
            $row = $r['rows'][0] ?? null;
            return [
                'exactly 5 held'           => $r['oks'] === 5,
                'reserved_count == 5'      => $row && (int) $row->reserved_count === 5,
                'single inventory row'     => count($r['rows']) === 1,
            ];
        },
    ],
    'existing row: mixed holds + bookings (capacity 4)' => [
        'workers' => array_merge(array_fill(0, 6, ['reserve', 1]), array_fill(0, 6, ['commit', 1])),
        'seed'    => ['capacity' => 4, 'status' => 'available'],
        'cap'     => 4,
        'check'   => function (array $r): array {
            $row = $r['rows'][0] ?? null;
            $used = $row ? (int) $row->booked_count + (int) $row->reserved_count : -1;
            return [
                'exactly 4 seats granted'  => $r['oks'] === 4,
                'booked + reserved == 4'   => $used === 4,
                'never exceeds capacity'   => $row && $used <= (int) $row->total_capacity,
            ];
        },
    ],
    'multi-seat bookings (capacity 5, 2 seats each)' => [
        'workers' => array_fill(0, 6, ['commit', 2]),
        'seed'    => null,
        'cap'     => 5,
        'check'   => function (array $r): array {
            $row = $r['rows'][0] ?? null;
            return [
                'exactly 2 bookings fit'   => $r['oks'] === 2,
                'booked_count == 4'        => $row && (int) $row->booked_count === 4,
            ];
        },
    ],
    'blocked date rejects every booking' => [
        'workers' => array_fill(0, 6, ['commit', 1]),
        'seed'    => ['capacity' => 10, 'status' => 'blocked'],
        'cap'     => 10,
        'check'   => function (array $r): array {
            $row = $r['rows'][0] ?? null;
            return [
                'nobody got a seat'        => $r['oks'] === 0,
                'booked_count == 0'        => $row && (int) $row->booked_count === 0,
            ];
        },
    ],
];

foreach ($scenarios as $name => $s) {
    echo "\n▶ {$name}\n";
    $scenarioFailed = false;

    for ($run = 1; $run <= $runs; $run++) {
        resetTable($db, $migration, $prefix);
        $itemId = 1000 + $run;

        if ($s['seed']) {
            $db->query($db->prepare(
                "INSERT INTO `{$table}` (item_id, item_type, event_date, time_slot, total_capacity, booked_count, reserved_count, status)
                 VALUES (%d, 'tour', %s, 'all_day', %d, 0, 0, %s)",
                $itemId,
                $date,
                $s['seed']['capacity'],
                $s['seed']['status']
            ));
        }

        $result = fire($s['workers'], $itemId, $date, $s['cap'], $flags, $dbName, $prefix, $host, $port, $user, $pass, $db);
        $deadlocks += count(array_filter($result['errors'], static fn (string $e): bool => str_starts_with($e, '1213:')));

        foreach (($s['check'])($result) as $label => $passed) {
            if (!$passed) {
                $scenarioFailed = true;
                $failures[] = "{$name} (run {$run}): {$label} — oks={$result['oks']} rows=" . json_encode($result['rows']);
            }
        }
    }

    echo $scenarioFailed ? "  ✗ FAILED\n" : "  ✓ {$runs}/{$runs} runs consistent\n";
}

$db->query("DROP TABLE IF EXISTS `{$table}`");
if ($create) {
    $admin->query("DROP DATABASE IF EXISTS `{$dbName}`");
}

echo "\nDeadlocks observed and recovered by retry: {$deadlocks}\n";

if ($failures) {
    echo "\n✗ " . count($failures) . " violation(s):\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "\n✓ No overbooking, lost update or duplicate row in any run.\n";
exit(0);
