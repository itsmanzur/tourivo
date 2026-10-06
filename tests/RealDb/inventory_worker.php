<?php
/**
 * One concurrent "customer" for the real-MySQL inventory test.
 *
 * Usage (spawned by run_inventory_concurrency.php, not meant to be run by hand):
 *   php inventory_worker.php <workerId> <runDir> <action> <itemId> <date> <count> <defaultCapacity>
 *
 * It connects, signals readiness, spins until the orchestrator creates "<runDir>/go", then performs exactly one
 * repository call and prints a single JSON line with the outcome.
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

$ok = match ($action) {
    'commit'  => $repo->commitSpots((int) $itemId, 'tour', $date, 'all_day', (int) $count, (int) $defaultCapacity, false),
    'reserve' => $repo->reserveSpots((int) $itemId, 'tour', $date, 'all_day', (int) $count, (int) $defaultCapacity),
    default   => false,
};

echo json_encode(['worker' => (int) $workerId, 'action' => $action, 'ok' => $ok, 'errors' => $GLOBALS['wpdb']->errors]), "\n";
