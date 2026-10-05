<?php
// phpcs:ignoreFile
/**
 * Unit Test for Booking Status Transitions & Inventory Integrity
 */

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    if (!class_exists('wpdb')) {
        class wpdb {
            public string $prefix = 'wp_';
        }
    }

    if (!function_exists('get_option')) {
        function get_option(string $key, mixed $default = []) {
            return $default;
        }
    }

    if (!function_exists('get_bloginfo')) {
        function get_bloginfo(string $show = '', string $filter = 'raw'): string {
            return 'USD';
        }
    }

    if (!function_exists('__')) {
        function __(string $text, string $domain = 'default'): string {
            return $text;
        }
    }

    if (!function_exists('current_time')) {
        function current_time(string $type, int $gmt = 0): string {
            return gmdate('Y-m-d H:i:s');
        }
    }

    if (!function_exists('sanitize_key')) {
        function sanitize_key(string $key): string {
            return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $key));
        }
    }

    if (!function_exists('sanitize_textarea_field')) {
        function sanitize_textarea_field(string $str): string {
            return trim(strip_tags($str));
        }
    }

    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in(): bool {
            return false;
        }
    }

    if (!function_exists('get_current_user_id')) {
        function get_current_user_id(): int {
            return 1;
        }
    }

    if (!function_exists('wp_cache_delete')) {
        function wp_cache_delete(string $key, string $group = ''): bool {
            return true;
        }
    }

    $GLOBALS['test_fired_actions'] = [];

    if (!function_exists('do_action')) {
        function do_action(string $tag, mixed ...$args): void {
            $GLOBALS['test_fired_actions'][] = ['tag' => $tag, 'args' => $args];
        }
    }

    spl_autoload_register(function (string $class): void {
        $prefix = 'Tourivo\\';
        $baseDir = dirname(__DIR__) . '/app/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
}

namespace Tourivo\Tests {
    use Tourivo\Services\BookingService;
    use Tourivo\Services\InventoryService;
    use Tourivo\Services\EmailService;
    use Tourivo\Repositories\InventoryRepository;

    class MockInventoryService extends InventoryService
    {
        public int $commitCalls = 0;
        public int $releaseCalls = 0;
        public bool $commitShouldSucceed = true;

        public function __construct()
        {
            // Do not call parent constructor
        }

        public function commitBooking(int $itemId, string $itemType, string $startDate, ?string $endDate = null, string $timeSlot = 'all_day', int $count = 1, ?string $holdToken = null): bool
        {
            $this->commitCalls++;
            return $this->commitShouldSucceed;
        }

        public function releaseBookingInventory(int $itemId, string $itemType, string $startDate, ?string $endDate = null, string $timeSlot = 'all_day', int $count = 1): bool
        {
            $this->releaseCalls++;
            return true;
        }
    }

    class MockEmailService extends EmailService
    {
        public function __construct()
        {
            // Do not call parent constructor
        }
    }

    class TestWpdb extends \wpdb
    {
        public string $prefix = 'wp_';
        public int $insert_id = 1;
        public mixed $booking = null;
        public array $items = [];
        public int $updateCalls = 0;

        public function prepare(string $query, ...$args): string
        {
            return $query;
        }

        public function get_row(string $query, string $output = OBJECT): mixed
        {
            return $this->booking;
        }

        public function get_results(string $query, string $output = OBJECT): array
        {
            return $this->items;
        }

        public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int|bool
        {
            $this->updateCalls++;
            if ($this->booking && isset($data['booking_status'])) {
                $this->booking->booking_status = $data['booking_status'];
            }
            return 1;
        }

        public function insert(string $table, array $data, array $format = []): int|bool
        {
            return 1;
        }
    }

    class BookingStatusTransitionTest
    {
        private function assert(string $desc, bool $condition): void
        {
            if ($condition) {
                echo "[PASS] {$desc}\n";
            } else {
                echo "[FAIL] {$desc}\n";
                throw new \RuntimeException("Assertion failed: {$desc}");
            }
        }

        public function run(): void
        {
            echo "--- Running Booking Status Transitions & Inventory Integrity Tests ---\n";

            global $wpdb;
            $db = new TestWpdb();
            $wpdb = $db;

            $inv = new MockInventoryService();
            $email = new MockEmailService();
            $service = new BookingService($inv, $email);

            $booking = new \stdClass();
            $booking->id = 101;
            $booking->booking_code = 'TRV-2026-STATUS';
            $booking->booking_status = 'confirmed';

            $item = new \stdClass();
            $item->item_id = 10;
            $item->item_type = 'tour';
            $item->check_in = '2026-11-20 00:00:00';
            $item->check_out = null;
            $item->time_slot = 'morning';
            $item->quantity = 3;

            $db->booking = $booking;
            $db->items = [$item];

            // Test 1: Cancel booking -> inventory released
            $res = $service->changeStatus(101, 'cancelled');
            $this->assert('Status update to cancelled returned success', $res['success'] === true);
            $this->assert('Inventory was released on cancel (releaseCalls = 1)', $inv->releaseCalls === 1);
            $this->assert('Booking status in DB is now cancelled', $db->booking->booking_status === 'cancelled');

            // Test 2: Cancel again (already cancelled) -> no-op, inventory NOT released a second time
            $inv->releaseCalls = 0;
            $res2 = $service->changeStatus(101, 'cancelled');
            $this->assert('Cancelling already cancelled booking returns success (no-op)', $res2['success'] === true);
            $this->assert('Inventory was NOT released again (releaseCalls = 0)', $inv->releaseCalls === 0);

            // Test 3: Reactivate from cancelled to confirmed -> re-commits inventory
            $inv->commitCalls = 0;
            $inv->commitShouldSucceed = true;
            $res3 = $service->changeStatus(101, 'confirmed');
            $this->assert('Reactivating to confirmed returned success', $res3['success'] === true);
            $this->assert('Inventory was re-committed on reactivate (commitCalls = 1)', $inv->commitCalls === 1);
            $this->assert('Booking status in DB is confirmed', $db->booking->booking_status === 'confirmed');

            // Set back to cancelled for sold-out test
            $service->changeStatus(101, 'cancelled');
            $this->assert('Set back to cancelled', $db->booking->booking_status === 'cancelled');

            // Test 4: Reactivate on sold-out date -> commit fails, revert inventory, status remains cancelled
            $inv->commitCalls = 0;
            $inv->releaseCalls = 0;
            $inv->commitShouldSucceed = false; // Sold out!

            $res4 = $service->changeStatus(101, 'confirmed');
            $this->assert('Reactivation on sold-out date fails', $res4['success'] === false);
            $this->assert('Booking status remains cancelled after failed reactivation', $db->booking->booking_status === 'cancelled');

            echo "All Booking Status Transition tests passed successfully!\n\n";
        }
    }

    $test = new BookingStatusTransitionTest();
    $test->run();
}
