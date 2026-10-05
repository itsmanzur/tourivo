<?php
/**
 * Tourivo Real MySQL Availability & Bulk Upsert Manual Verification Script
 *
 * Usage:
 *   php tests/manual/test_availability_real_mysql.php
 *
 * Environment variables (optional):
 *   DB_HOST=127.0.0.1 (or localhost:3306)
 *   DB_NAME=local (or tourivo_test)
 *   DB_USER=root
 *   DB_PASSWORD=root
 *   TABLE_PREFIX=wp_
 */

declare(strict_types=1);

echo "=====================================================\n";
echo "   Tourivo Real MySQL Availability Verification\n";
echo "=====================================================\n\n";

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = 3306;
if (str_contains($dbHost, ':')) {
    [$dbHost, $portStr] = explode(':', $dbHost, 2);
    $dbPort = (int) $portStr;
}
$dbName = getenv('DB_NAME') ?: 'local';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'root';
$prefix = getenv('TABLE_PREFIX') ?: 'wp_';
$tableName = $prefix . 'tourivo_inventories';

echo "Connecting to MySQL server at {$dbHost}:{$dbPort} (DB: {$dbName})...\n";

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "✓ Database connected successfully.\n\n";
} catch (\PDOException $e) {
    echo "✗ Failed to connect to MySQL database: " . $e->getMessage() . "\n";
    echo "\nPlease ensure MySQL is running, or specify connection credentials via:\n";
    echo "  DB_HOST=127.0.0.1 DB_NAME=your_db DB_USER=your_user DB_PASSWORD=your_pass php tests/manual/test_availability_real_mysql.php\n";
    exit(1);
}

// 1. Ensure Table and Unique Index Exist
echo "1. Verifying table schema and unique key constraint on {$tableName}...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS `{$tableName}` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `item_id` bigint(20) unsigned NOT NULL,
    `item_type` varchar(20) NOT NULL DEFAULT 'tour',
    `event_date` date NOT NULL,
    `time_slot` varchar(50) NOT NULL DEFAULT 'all_day',
    `total_capacity` int(11) NOT NULL DEFAULT 1,
    `booked_count` int(11) NOT NULL DEFAULT 0,
    `reserved_count` int(11) NOT NULL DEFAULT 0,
    `price_override` decimal(10,2) DEFAULT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'available',
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `item_date_slot_unique` (`item_id`, `item_type`, `event_date`, `time_slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "✓ Schema verified.\n\n";

$testItemId = 999991;
$testItemType = 'tour';
$testDate = date('Y-m-d', strtotime('+30 days'));
$timeSlot = 'all_day';

// Clean test records
$stmtClean = $pdo->prepare("DELETE FROM `{$tableName}` WHERE `item_id` = ?");
$stmtClean->execute([$testItemId]);

// 2. Test Initial Upsert (Insert)
echo "2. Testing initial INSERT via ON DUPLICATE KEY UPDATE...\n";
$stmtUpsert = $pdo->prepare("
    INSERT INTO `{$tableName}` 
        (`item_id`, `item_type`, `event_date`, `time_slot`, `total_capacity`, `booked_count`, `reserved_count`, `price_override`, `status`, `created_at`, `updated_at`)
    VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        `total_capacity` = VALUES(`total_capacity`),
        `price_override` = VALUES(`price_override`),
        `status` = VALUES(`status`),
        `updated_at` = NOW()
");

$stmtUpsert->execute([$testItemId, $testItemType, $testDate, $timeSlot, 12, 0, 0, 150.00, 'available']);
$row = $pdo->query("SELECT * FROM `{$tableName}` WHERE `item_id` = {$testItemId} AND `event_date` = '{$testDate}'")->fetch();

if ($row && (int) $row['total_capacity'] === 12 && (float) $row['price_override'] === 150.00 && $row['status'] === 'available') {
    echo "✓ Initial record created with capacity 12 and price override \$150.00.\n\n";
} else {
    echo "✗ Initial insert verification failed.\n";
    exit(1);
}

// 3. Simulate Bookings and Update Capacity via Upsert
echo "3. Simulating active bookings and running bulk UPDATE via ON DUPLICATE KEY...\n";
$pdo->exec("UPDATE `{$tableName}` SET `booked_count` = 3, `reserved_count` = 1 WHERE `id` = {$row['id']}");

// Re-upsert with modified capacity 18 and new status 'blocked'
$stmtUpsert->execute([$testItemId, $testItemType, $testDate, $timeSlot, 18, 0, 0, 199.50, 'blocked']);
$updatedRow = $pdo->query("SELECT * FROM `{$tableName}` WHERE `id` = {$row['id']}")->fetch();

if ($updatedRow && (int) $updatedRow['total_capacity'] === 18 && (int) $updatedRow['booked_count'] === 3 && (int) $updatedRow['reserved_count'] === 1 && $updatedRow['status'] === 'blocked' && (float) $updatedRow['price_override'] === 199.50) {
    echo "✓ Unique key matched existing row! Capacity updated to 18, price override to \$199.50, status to 'blocked', while preserving 3 booked & 1 reserved count.\n\n";
} else {
    echo "✗ Duplicate key update failed to preserve existing booking counts.\n";
    var_dump($updatedRow);
    exit(1);
}

// 4. Test Resetting Price Override to NULL
echo "4. Testing price override reset to NULL in real MySQL...\n";
$stmtUpsert->execute([$testItemId, $testItemType, $testDate, $timeSlot, 18, 0, 0, null, 'available']);
$resetRow = $pdo->query("SELECT * FROM `{$tableName}` WHERE `id` = {$row['id']}")->fetch();

if ($resetRow && $resetRow['price_override'] === null && $resetRow['status'] === 'available') {
    echo "✓ Price override successfully reset to NULL.\n\n";
} else {
    echo "✗ Price override reset failed.\n";
    var_dump($resetRow);
    exit(1);
}

// 5. Test Multi-Row Batch Transaction with Rollback
echo "5. Testing atomic transaction rollback on simulated error...\n";
$pdo->beginTransaction();
$stmtUpsert->execute([$testItemId, $testItemType, date('Y-m-d', strtotime('+31 days')), $timeSlot, 10, 0, 0, null, 'available']);
$stmtUpsert->execute([$testItemId, $testItemType, date('Y-m-d', strtotime('+32 days')), $timeSlot, 10, 0, 0, null, 'available']);
// Rollback transaction
$pdo->rollBack();

$countPostRollback = (int) $pdo->query("SELECT COUNT(*) FROM `{$tableName}` WHERE `item_id` = {$testItemId} AND `event_date` > '{$testDate}'")->fetchColumn();
if ($countPostRollback === 0) {
    echo "✓ Transaction rollback successfully reverted uncommitted batch records.\n\n";
} else {
    echo "✗ Transaction rollback failed: records remained in database.\n";
    exit(1);
}

// Cleanup
$stmtClean->execute([$testItemId]);
echo "✓ Cleaned up test records.\n\n";
echo "=====================================================\n";
echo "All MySQL Database Availability Tests Passed! ✓\n";
echo "=====================================================\n";
exit(0);
