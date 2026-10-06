<?php
/**
 * Tourivo Test Suite Bootstrap
 */

declare(strict_types=1);

// Determine if we are running inside full WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (file_exists($_tests_dir . '/includes/functions.php')) {
    // 1. Full WordPress Test Environment
    require_once $_tests_dir . '/includes/functions.php';

    tests_add_filter('muplugins_loaded', static function () {
        require dirname(__DIR__) . '/tourivo.php';
        \Tourivo\Database\Schema::migrate();
    });

    require $_tests_dir . '/includes/bootstrap.php';
} else {
    // 2. Standalone In-Memory Mock Bootstrap for High-Speed Unit & Integration Tests
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
    }
    if (!defined('TOURIVO_VERSION')) {
        define('TOURIVO_VERSION', '1.3.0');
    }
    if (!defined('TOURIVO_PLUGIN_DIR')) {
        define('TOURIVO_PLUGIN_DIR', dirname(__DIR__) . '/');
    }
    if (!defined('TOURIVO_PLUGIN_URL')) {
        define('TOURIVO_PLUGIN_URL', 'http://localhost/wp-content/plugins/tourivo/');
    }
    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }
    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }
    if (!defined('MINUTE_IN_SECONDS')) {
        define('MINUTE_IN_SECONDS', 60);
    }
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    // PSR-4 Autoloader
    spl_autoload_register(static function (string $class) {
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

    // Mock storage
    $GLOBALS['tourivo_mock_options'] = [
        'admin_email' => 'admin@example.com',
        'blogname'    => 'Tourivo Test Site',
    ];
    $GLOBALS['tourivo_mock_transients'] = [];
    $GLOBALS['tourivo_mock_posts'] = [];
    $GLOBALS['tourivo_mock_postmeta'] = [];
    $GLOBALS['tourivo_mock_terms'] = [];
    $GLOBALS['tourivo_mock_actions'] = [];
    $GLOBALS['tourivo_mock_filters'] = [];

    // Mock WordPress WP_Post
    if (!class_exists('WP_Post')) {
        class WP_Post {
            public int $ID = 0;
            public string $post_title = '';
            public string $post_type = 'post';
            public string $post_status = 'publish';
            public string $post_content = '';
            public string $post_excerpt = '';
            public string $post_name = '';
            public int $post_parent = 0;
            public string $post_date = '';
            public function __construct(array|object $data = []) {
                foreach ((array) $data as $k => $v) {
                    $this->$k = $v;
                }
            }
        }
    }

    // Mock WordPress Core DB
    if (!class_exists('wpdb')) {
        class wpdb {
            public string $prefix = 'wp_';
            public string $posts = 'wp_posts';
            public string $postmeta = 'wp_postmeta';
            public string $options = 'wp_options';
            public array $bookings = [];
            public array $booking_items = [];
            public array $inventories = [];
            public array $inquiries = [];
            public array $logs = [];
            public int $insert_id = 0;
            public int $rows_affected = 0;

            public function get_charset_collate(): string {
                return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            }

            public function prepare(string $query, mixed ...$args): string {
                if (empty($args)) return $query;
                if (count($args) === 1 && is_array($args[0])) $args = $args[0];
                foreach ($args as $arg) {
                    $val = is_numeric($arg) ? $arg : "'" . addslashes((string)$arg) . "'";
                    $query = preg_replace('/%[sdfF]/', (string)$val, $query, 1);
                }
                return $query;
            }

            public function query(string $query): int|bool {
                $trimmed = trim($query);
                if (in_array(strtoupper($trimmed), ['START TRANSACTION', 'COMMIT', 'ROLLBACK'], true)) {
                    $this->rows_affected = 1;
                    return 1;
                }

                // 0. INSERT IGNORE INTO wp_tourivo_inventories: create the row only if it does not exist yet
                if (stripos($trimmed, 'INSERT IGNORE INTO') !== false && stripos($trimmed, 'tourivo_inventories') !== false) {
                    if (preg_match("/VALUES\s*\(\s*(\d+)\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*'([^']+)'/is", $trimmed, $m)) {
                        $key = "{$m[1]}_{$m[3]}";
                        // While the "lose the first-row race" hook is armed, ensureRow() is a no-op so the fallback
                        // INSERT path (and its retry) is what gets exercised.
                        if (isset($this->inventories[$key]) || !empty($GLOBALS['tourivo_mock_fail_next_inventory_insert'])) {
                            $this->rows_affected = 0;
                            return 0;
                        }
                        $this->inventories[$key] = [
                            'id' => count($this->inventories) + 1, 'item_id' => (int) $m[1], 'item_type' => $m[2],
                            'event_date' => $m[3], 'time_slot' => $m[4], 'total_capacity' => (int) $m[5],
                            'booked_capacity' => (int) $m[6], 'booked_count' => (int) $m[6], 'reserved_count' => (int) $m[7],
                            'price_override' => null, 'status' => $m[8],
                        ];
                        $this->rows_affected = 1;
                        return 1;
                    }
                }

                // 1. INSERT INTO wp_tourivo_inventories
                if (stripos($trimmed, 'INSERT INTO') !== false && stripos($trimmed, 'tourivo_inventories') !== false) {
                    // Test hook: simulate losing the "first row for this date" race (a concurrent writer inserts the
                    // row first, our INSERT then fails with a duplicate-key error).
                    if (!empty($GLOBALS['tourivo_mock_fail_next_inventory_insert'])) {
                        $GLOBALS['tourivo_mock_fail_next_inventory_insert'] = false;
                        if (preg_match("/VALUES\s*\(\s*(\d+)\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*(\d+)/i", $trimmed, $mr)) {
                            $this->inventories["{$mr[1]}_{$mr[3]}"] = [
                                'id' => count($this->inventories) + 1, 'item_id' => (int) $mr[1], 'item_type' => $mr[2],
                                'event_date' => $mr[3], 'time_slot' => $mr[4], 'total_capacity' => (int) $mr[5],
                                'booked_capacity' => 1, 'booked_count' => 1, 'reserved_count' => 0,
                                'price_override' => null, 'status' => 'available',
                            ];
                        }
                        $this->rows_affected = 0;
                        return false;
                    }
                    if (preg_match("/VALUES\s*\(\s*(\d+)\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(NULL|'[^']*'|[0-9\.]+)\s*,\s*'([^']+)'/is", $trimmed, $m)) {
                        $itemId   = (int) $m[1];
                        $itemType = $m[2];
                        $date     = $m[3];
                        $slot     = $m[4];
                        $totalCap = (int) $m[5];
                        $booked   = (int) $m[6];
                        $reserved = (int) $m[7];
                        $priceRaw = trim($m[8], "'");
                        $priceVal = (strtoupper($priceRaw) === 'NULL' || $priceRaw === '') ? null : (float) $priceRaw;
                        $status   = $m[9];
                        $key = "{$itemId}_{$date}";
                        if (isset($this->inventories[$key])) {
                            $this->inventories[$key]['total_capacity'] = $totalCap;
                            $this->inventories[$key]['price_override'] = $priceVal;
                            $this->inventories[$key]['status']         = $status;
                        } else {
                            $id = count($this->inventories) + 1;
                            $this->inventories[$key] = [
                                'id'              => $id,
                                'item_id'         => $itemId,
                                'item_type'       => $itemType,
                                'event_date'      => $date,
                                'time_slot'       => $slot,
                                'total_capacity'  => $totalCap,
                                'booked_capacity' => $booked,
                                'booked_count'    => $booked,
                                'reserved_count'  => $reserved,
                                'price_override'  => $priceVal,
                                'status'          => $status,
                            ];
                        }
                        $this->insert_id = $this->inventories[$key]['id'] ?? 1;
                        $this->rows_affected = 1;
                        return 1;
                    } elseif (preg_match("/VALUES\s*\(\s*(\d+)\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*'([^']+)'/is", $trimmed, $m)) {
                        $itemId   = (int) $m[1];
                        $itemType = $m[2];
                        $date     = $m[3];
                        $slot     = $m[4];
                        $totalCap = (int) $m[5];
                        $booked   = (int) $m[6];
                        $reserved = (int) $m[7];
                        $status   = $m[8];
                        $key = "{$itemId}_{$date}";
                        $id = count($this->inventories) + 1;
                        $this->inventories[$key] = [
                            'id'              => $id,
                            'item_id'         => $itemId,
                            'item_type'       => $itemType,
                            'event_date'      => $date,
                            'time_slot'       => $slot,
                            'total_capacity'  => $totalCap,
                            'booked_capacity' => $booked,
                            'booked_count'    => $booked,
                            'reserved_count'  => $reserved,
                            'price_override'  => null,
                            'status'          => $status,
                        ];
                        $this->insert_id = $id;
                        $this->rows_affected = 1;
                        return 1;
                    }
                }

                // 2. UPDATE wp_tourivo_inventories
                if (stripos($trimmed, 'UPDATE') !== false && stripos($trimmed, 'tourivo_inventories') !== false) {
                    if (preg_match('/SET\s+booked_count\s*=\s*(\d+)\s*,\s*reserved_count\s*=\s*(\d+)\s*,\s*status\s*=\s*\'([^\']+)\'.*WHERE\s+item_id\s*=\s*(\d+).*event_date\s*=\s*\'([^\']+)\'/is', $trimmed, $m)) {
                        $booked   = (int) $m[1];
                        $reserved = (int) $m[2];
                        $status   = $m[3];
                        $itemId   = (int) $m[4];
                        $date     = $m[5];
                        $key      = "{$itemId}_{$date}";
                        if (isset($this->inventories[$key])) {
                            $this->inventories[$key]['booked_count']    = $booked;
                            $this->inventories[$key]['booked_capacity'] = $booked;
                            $this->inventories[$key]['reserved_count']  = $reserved;
                            $this->inventories[$key]['status']          = $status;
                            $this->rows_affected = 1;
                            return 1;
                        }
                    } elseif (preg_match('/SET\s+booked_count\s*=\s*(\d+)\s*,\s*reserved_count\s*=\s*(\d+)\s*,\s*status\s*=\s*\'([^\']+)\'.*WHERE\s+id\s*=\s*(\d+)/is', $trimmed, $m)) {
                        $booked   = (int) $m[1];
                        $reserved = (int) $m[2];
                        $status   = $m[3];
                        $id       = (int) $m[4];
                        foreach ($this->inventories as $k => $inv) {
                            if ((int)$inv['id'] === $id) {
                                $this->inventories[$k]['booked_count']    = $booked;
                                $this->inventories[$k]['booked_capacity'] = $booked;
                                $this->inventories[$k]['reserved_count']  = $reserved;
                                $this->inventories[$k]['status']          = $status;
                                $this->rows_affected = 1;
                                return 1;
                            }
                        }
                    } elseif (preg_match('/SET\s+booked_count\s*=\s*(\d+)\s*,\s*status\s*=\s*\'([^\']+)\'.*WHERE\s+id\s*=\s*(\d+)/is', $trimmed, $m)) {
                        $booked = (int) $m[1];
                        $status = $m[2];
                        $id     = (int) $m[3];
                        foreach ($this->inventories as $k => $inv) {
                            if ((int)$inv['id'] === $id) {
                                $this->inventories[$k]['booked_count']    = $booked;
                                $this->inventories[$k]['booked_capacity'] = $booked;
                                $this->inventories[$k]['status']          = $status;
                                $this->rows_affected = 1;
                                return 1;
                            }
                        }
                    } elseif (preg_match('/SET\s+reserved_count\s*=\s*reserved_count\s*\+\s*(\d+).*WHERE\s+id\s*=\s*(\d+)/is', $trimmed, $m)) {
                        $count = (int) $m[1];
                        $id    = (int) $m[2];
                        foreach ($this->inventories as $k => $inv) {
                            if ((int)$inv['id'] === $id) {
                                $this->inventories[$k]['reserved_count'] = ((int)$this->inventories[$k]['reserved_count']) + $count;
                                $this->rows_affected = 1;
                                return 1;
                            }
                        }
                    } elseif (preg_match('/SET\s+reserved_count\s*=\s*GREATEST\(0,\s*reserved_count\s*-\s*(\d+)\).*WHERE\s+item_id\s*=\s*(\d+).*event_date\s*=\s*\'([^\']+)\'/is', $trimmed, $m)) {
                        $count  = (int) $m[1];
                        $itemId = (int) $m[2];
                        $date   = $m[3];
                        $key    = "{$itemId}_{$date}";
                        if (isset($this->inventories[$key])) {
                            $this->inventories[$key]['reserved_count'] = max(0, ((int)$this->inventories[$key]['reserved_count']) - $count);
                            $this->rows_affected = 1;
                            return 1;
                        }
                    }
                }

                // 3. UPDATE wp_tourivo_bookings
                if (stripos($trimmed, 'UPDATE') !== false && stripos($trimmed, 'tourivo_bookings') !== false) {
                    // Email verification stamp (conditional on still being NULL)
                    if (preg_match("/SET\s+email_verified_at\s*=\s*'([^']+)'.*WHERE\s+id\s*=\s*(\d+)\s+AND\s+email_verified_at\s+IS\s+NULL/is", $trimmed, $m)) {
                        $id = (int) $m[2];
                        if (isset($this->bookings[$id]) && empty($this->bookings[$id]['email_verified_at'])) {
                            $this->bookings[$id]['email_verified_at'] = $m[1];
                            $this->rows_affected = 1;
                            return 1;
                        }
                        $this->rows_affected = 0;
                        return 0;
                    }
                    // Migration backfill: mark every legacy booking as already verified
                    if (preg_match('/SET\s+email_verified_at\s*=\s*created_at\s+WHERE\s+email_verified_at\s+IS\s+NULL/is', $trimmed)) {
                        $n = 0;
                        foreach ($this->bookings as $bid => $b) {
                            if (empty($b['email_verified_at'])) {
                                $this->bookings[$bid]['email_verified_at'] = $b['created_at'] ?? gmdate('Y-m-d H:i:s');
                                $n++;
                            }
                        }
                        $this->rows_affected = $n;
                        return $n;
                    }
                    if (preg_match("/SET\s+booking_status\s*=\s*'([^']+)'.*WHERE\s+id\s*=\s*(\d+)\s+AND\s+booking_status\s*=\s*'([^']+)'/is", $trimmed, $m)) {
                        $newStatus = $m[1];
                        $id        = (int) $m[2];
                        $oldStatus = $m[3];
                        if (isset($this->bookings[$id]) && $this->bookings[$id]['booking_status'] === $oldStatus) {
                            $this->bookings[$id]['booking_status'] = $newStatus;
                            $this->rows_affected = 1;
                            return 1;
                        } else {
                            $this->rows_affected = 0;
                            return 0;
                        }
                    }
                }

                // 3b. UPDATE wp_tourivo_logs (PII redaction)
                if (stripos($trimmed, 'UPDATE') !== false && stripos($trimmed, 'tourivo_logs') !== false) {
                    if (preg_match("/SET\s+details\s*=\s*'([^']*)'\s+WHERE\s+booking_id\s*=\s*(\d+)\s+AND\s+action\s+IN\s*\(([^)]*)\)/is", $trimmed, $m)) {
                        $actions = array_map(static fn ($a) => trim($a, " '"), explode(',', $m[3]));
                        $n = 0;
                        foreach ($this->logs as $lid => $log) {
                            if ((int) ($log['booking_id'] ?? 0) === (int) $m[2] && in_array($log['action'] ?? '', $actions, true)) {
                                $this->logs[$lid]['details'] = $m[1];
                                $n++;
                            }
                        }
                        $this->rows_affected = $n;
                        return $n;
                    }
                }

                // 4. DELETE FROM wp_tourivo_inquiries
                if (stripos($trimmed, 'DELETE FROM') !== false && stripos($trimmed, 'tourivo_inquiries') !== false) {
                    if (preg_match("/customer_email\s*=\s*'([^']+)'/i", $trimmed, $m)) {
                        $targetEmail = $m[1];
                        $cnt = 0;
                        foreach ($this->inquiries as $id => $inq) {
                            if (($inq['customer_email'] ?? '') === $targetEmail) {
                                unset($this->inquiries[$id]);
                                $cnt++;
                            }
                        }
                        $this->rows_affected = $cnt;
                        return $cnt;
                    } elseif (preg_match('/id\s+IN\s*\(([^)]+)\)/i', $trimmed, $mIds)) {
                        $ids = array_map('intval', explode(',', $mIds[1]));
                        $cnt = 0;
                        foreach ($ids as $id) {
                            if (isset($this->inquiries[$id])) {
                                unset($this->inquiries[$id]);
                                $cnt++;
                            }
                        }
                        $this->rows_affected = $cnt;
                        return $cnt;
                    }
                }

                $this->rows_affected = 1;
                return 1;
            }

            public function get_var(?string $query = null, int $x = 0, int $y = 0): mixed {
                if (!$query) return null;
                if (stripos($query, 'COUNT(*)') !== false) {
                    if (stripos($query, 'tourivo_inquiries') !== false) {
                        if (preg_match("/customer_email\s*=\s*'([^']+)'/i", $query, $mEmail)) {
                            $cnt = 0;
                            foreach ($this->inquiries as $inq) {
                                if (($inq['customer_email'] ?? '') === $mEmail[1]) $cnt++;
                            }
                            return $cnt;
                        }
                        if (preg_match("/created_at\s*<=\s*'([^']+)'/i", $query, $mDate)) {
                            $cutoff = strtotime($mDate[1]);
                            $cnt = 0;
                            foreach ($this->inquiries as $inq) {
                                if (strtotime((string)($inq['created_at'] ?? '')) <= $cutoff) $cnt++;
                            }
                            return $cnt;
                        }
                        return count($this->inquiries);
                    }
                    if (stripos($query, 'tourivo_bookings') !== false) {
                        if (preg_match("/customer_email\s*=\s*'([^']+)'/i", $query, $mEmail)) {
                            $cnt = 0;
                            foreach ($this->bookings as $b) {
                                if (($b['customer_email'] ?? '') === $mEmail[1]) $cnt++;
                            }
                            return $cnt;
                        }
                        if (preg_match("/created_at\s*<=\s*'([^']+)'/i", $query, $mDate)) {
                            $cutoff = strtotime($mDate[1]);
                            $cnt = 0;
                            foreach ($this->bookings as $b) {
                                if (strtotime((string)($b['created_at'] ?? '')) <= $cutoff) {
                                    if (stripos($query, "customer_name != 'Anonymized'") !== false && ($b['customer_name'] ?? '') === 'Anonymized') {
                                        continue;
                                    }
                                    $cnt++;
                                }
                            }
                            return $cnt;
                        }
                        return count($this->bookings);
                    }
                }
                if (stripos($query, 'booking_status') !== false && preg_match('/id\s*=\s*(\d+)/i', $query, $m)) {
                    $id = (int) $m[1];
                    return $this->bookings[$id]['booking_status'] ?? null;
                }
                if (stripos($query, 'SHOW TABLES') !== false) {
                    return 'wp_tourivo_bookings';
                }
                if (stripos($query, 'tourivo_inventories') !== false && stripos($query, 'SELECT 1') !== false) {
                    $itemIds = [];
                    if (preg_match('/item_id\s+IN\s*\(([^)]+)\)/i', $query, $mIds)) {
                        $itemIds = array_map('intval', explode(',', $mIds[1]));
                    } elseif (preg_match('/item_id\s*=\s*(\d+)/i', $query, $mId)) {
                        $itemIds = [(int) $mId[1]];
                    }

                    foreach ($this->inventories as $inv) {
                        if (!empty($itemIds) && !in_array((int) $inv['item_id'], $itemIds, true)) {
                            continue;
                        }
                        $cap = (int) ($inv['total_capacity'] ?? 0);
                        $booked = (int) ($inv['booked_count'] ?? 0);
                        $reserved = (int) ($inv['reserved_count'] ?? 0);
                        $status = (string) ($inv['status'] ?? 'available');
                        if ($status === 'available' && ($cap - $booked - $reserved) > 0) {
                            return 1;
                        }
                    }
                    return null;
                }
                return null;
            }

            public function esc_like(string $text): string {
                return addcslashes($text, '_%\\');
            }

            public function get_col(?string $query = null, int $x = 0): array {
                if (!$query) return [];
                if (stripos($query, 'option_name') !== false && preg_match("/LIKE\s+'([^']*)'/i", $query, $mLike)) {
                    $prefix = rtrim(str_replace('\\_', '_', stripslashes($mLike[1])), '%');
                    $names = [];
                    foreach (array_keys($GLOBALS['tourivo_mock_options'] ?? []) as $name) {
                        if (str_starts_with((string) $name, $prefix)) {
                            $names[] = (string) $name;
                        }
                    }
                    return $names;
                }
                if (stripos($query, 'tourivo_inquiries') !== false) {
                    $res = [];
                    $cutoff = null;
                    if (preg_match("/created_at\s*<=\s*'([^']+)'/i", $query, $mDate)) {
                        $cutoff = strtotime($mDate[1]);
                    }
                    foreach ($this->inquiries as $inq) {
                        if ($cutoff !== null && strtotime((string)($inq['created_at'] ?? '')) > $cutoff) {
                            continue;
                        }
                        $res[] = (int) $inq['id'];
                    }
                    return $res;
                }
                return [];
            }

            public function get_row(?string $query = null, string $output = OBJECT, int $y = 0): mixed {
                if (!$query) return null;

                if (stripos($query, 'tourivo_inquiries') !== false) {
                    if (preg_match('/id\s*=\s*(\d+)/i', $query, $m)) {
                        $id = (int) $m[1];
                        return isset($this->inquiries[$id]) ? (object) $this->inquiries[$id] : null;
                    }
                }

                if (stripos($query, 'tourivo_bookings') !== false) {
                    if (preg_match('/id\s*=\s*(\d+)/i', $query, $m)) {
                        $id = (int) $m[1];
                        return isset($this->bookings[$id]) ? (object) $this->bookings[$id] : null;
                    }
                    if (preg_match("/booking_code\s*=\s*'([^']+)'/i", $query, $mCode)) {
                        $code = $mCode[1];
                        $email = null;
                        if (preg_match("/customer_email\s*=\s*'([^']+)'/i", $query, $mEmail)) {
                            $email = $mEmail[1];
                        }
                        foreach ($this->bookings as $b) {
                            if (($b['booking_code'] ?? '') === $code) {
                                if ($email !== null && ($b['customer_email'] ?? '') !== $email) {
                                    continue;
                                }
                                return (object) $b;
                            }
                        }
                    }
                }

                if (stripos($query, 'tourivo_booking_items') !== false) {
                    if (preg_match('/booking_id\s*=\s*(\d+)/i', $query, $m)) {
                        $bookingId = (int) $m[1];
                        foreach ($this->booking_items as $item) {
                            if ((int)$item['booking_id'] === $bookingId) {
                                return (object) $item;
                            }
                        }
                    }
                }

                if (stripos($query, 'tourivo_inventories') !== false) {
                    if (preg_match('/item_id\s*=\s*(\d+)/i', $query, $mItem) && preg_match("/event_date\s*=\s*'([^']+)'/i", $query, $mDate)) {
                        $itemId = (int) $mItem[1];
                        $date = $mDate[1];
                        $key = "{$itemId}_{$date}";
                        return isset($this->inventories[$key]) ? (object) $this->inventories[$key] : null;
                    }
                    if (preg_match('/item_id\s*=\s*(\d+)/i', $query, $mItem)) {
                        $itemId = (int) $mItem[1];
                        foreach ($this->inventories as $inv) {
                            if ((int)$inv['item_id'] === $itemId) {
                                return (object) $inv;
                            }
                        }
                    }
                }

                return null;
            }

            public function get_results(?string $query = null, string $output = OBJECT): array {
                if (!$query) return [];

                if (stripos($query, 'tourivo_inquiries') !== false) {
                    $matched = [];
                    $targetEmail = null;
                    if (preg_match("/customer_email\s*=\s*'([^']+)'/i", $query, $mEmail)) {
                        $targetEmail = $mEmail[1];
                    }
                    foreach ($this->inquiries as $inq) {
                        if ($targetEmail !== null && ($inq['customer_email'] ?? '') !== $targetEmail) {
                            continue;
                        }
                        $matched[] = ($output === ARRAY_A) ? $inq : (object) $inq;
                    }
                    $limit = 0; $offset = 0;
                    if (preg_match('/LIMIT\s+(\d+)\s+OFFSET\s+(\d+)/i', $query, $mLim)) {
                        $limit  = (int) $mLim[1];
                        $offset = (int) $mLim[2];
                        return array_slice($matched, $offset, $limit);
                    }
                    return $matched;
                }

                if (stripos($query, 'tourivo_bookings') !== false && stripos($query, 'tourivo_booking_items') === false) {
                    $matched = [];
                    $targetEmail = null;
                    $cutoff = null;
                    if (preg_match("/customer_email\s*=\s*'([^']+)'/i", $query, $mEmail)) {
                        $targetEmail = $mEmail[1];
                    }
                    if (preg_match("/created_at\s*<=\s*'([^']+)'/i", $query, $mDate)) {
                        $cutoff = strtotime($mDate[1]);
                    }
                    foreach ($this->bookings as $b) {
                        if ($targetEmail !== null && ($b['customer_email'] ?? '') !== $targetEmail) {
                            continue;
                        }
                        if ($cutoff !== null) {
                            if (strtotime((string)($b['created_at'] ?? '')) > $cutoff) continue;
                            if (stripos($query, "customer_name != 'Anonymized'") !== false && ($b['customer_name'] ?? '') === 'Anonymized') continue;
                        }
                        $matched[] = ($output === ARRAY_A) ? $b : (object) $b;
                    }
                    if (preg_match('/LIMIT\s+(\d+)\s+OFFSET\s+(\d+)/i', $query, $mLim)) {
                        $limit  = (int) $mLim[1];
                        $offset = (int) $mLim[2];
                        return array_slice($matched, $offset, $limit);
                    }
                    if (preg_match('/LIMIT\s+(\d+)/i', $query, $mLimOnly)) {
                        return array_slice($matched, 0, (int) $mLimOnly[1]);
                    }
                    return $matched;
                }

                if (stripos($query, 'tourivo_booking_items') !== false) {
                    if (preg_match('/booking_id\s+IN\s*\(([^)]+)\)/i', $query, $mIn)) {
                        $wanted = array_map('intval', explode(',', $mIn[1]));
                        $res = [];
                        foreach ($this->booking_items as $item) {
                            if (in_array((int) $item['booking_id'], $wanted, true)) {
                                $res[] = (object) $item;
                            }
                        }
                        return $res;
                    }
                    if (preg_match('/booking_id\s*=\s*(\d+)/i', $query, $m)) {
                        $bookingId = (int) $m[1];
                        $res = [];
                        foreach ($this->booking_items as $item) {
                            if ((int)$item['booking_id'] === $bookingId) {
                                $res[] = (object) $item;
                            }
                        }
                        return $res;
                    }
                }
                if (stripos($query, 'tourivo_inventories') !== false) {
                    if (stripos($query, 'recorded_days') !== false) {
                        $itemIds = [];
                        if (preg_match('/item_id\s+IN\s*\(([^)]+)\)/i', $query, $mIds)) {
                            $itemIds = array_map('intval', explode(',', $mIds[1]));
                        } elseif (preg_match('/item_id\s*=\s*(\d+)/i', $query, $mId)) {
                            $itemIds = [(int) $mId[1]];
                        }

                        $counts = [];
                        foreach ($this->inventories as $inv) {
                            $itemId = (int) ($inv['item_id'] ?? 0);
                            if (!empty($itemIds) && !in_array($itemId, $itemIds, true)) {
                                continue;
                            }
                            $counts[$itemId] = ($counts[$itemId] ?? 0) + 1;
                        }
                        $res = [];
                        foreach ($counts as $itemId => $cnt) {
                            $res[] = (object) ['item_id' => $itemId, 'recorded_days' => $cnt];
                        }
                        return $res;
                    }
                    if (preg_match('/item_id\s*=\s*(\d+)/i', $query, $mItem)) {
                        $itemId = (int) $mItem[1];
                        $res = [];
                        foreach ($this->inventories as $inv) {
                            if ((int)$inv['item_id'] === $itemId) {
                                $res[] = (object) $inv;
                            }
                        }
                        return $res;
                    }
                }

                if (stripos($query, 'tourivo_logs') !== false) {
                    if (preg_match('/booking_id\s*=\s*(\d+)/i', $query, $m)) {
                        $bookingId = (int) $m[1];
                        $res = [];
                        foreach ($this->logs as $log) {
                            if ((int)($log['booking_id'] ?? 0) === $bookingId) {
                                $res[] = ($output === ARRAY_A) ? $log : (object) $log;
                            }
                        }
                        return array_reverse($res);
                    }
                }

                if (stripos($query, 'tourivo_bookings') !== false && stripos($query, 'tourivo_booking_items') !== false) {
                    if (preg_match("/DATE\(i\.check_in\)\s*=\s*'([^']+)'/i", $query, $mDate)) {
                        $targetDate = $mDate[1];
                        $res = [];
                        foreach ($this->bookings as $b) {
                            if (($b['booking_status'] ?? '') !== 'confirmed') {
                                continue;
                            }
                            foreach ($this->booking_items as $item) {
                                if ((int)($item['booking_id'] ?? 0) === (int)($b['id'] ?? 0)) {
                                    $itemDate = substr((string)($item['check_in'] ?? ''), 0, 10);
                                    if ($itemDate === $targetDate) {
                                        $row = array_merge($b, [
                                             'item_title'     => $item['item_title'] ?? '',
                                             'check_in'       => $item['check_in'] ?? '',
                                             'check_out'      => $item['check_out'] ?? '',
                                             'adults_count'   => $item['adults_count'] ?? 1,
                                             'children_count' => $item['children_count'] ?? 0,
                                             'infants_count'  => $item['infants_count'] ?? 0,
                                        ]);
                                        $res[] = ($output === ARRAY_A) ? $row : (object) $row;
                                    }
                                }
                            }
                        }
                        return $res;
                    }
                }

                return [];
            }

            public function insert(string $table, array $data, ?array $format = null): int|bool {
                if (stripos($table, 'tourivo_bookings') !== false) {
                    // Test hook: simulate N unique-key (booking_code) collisions
                    if (!empty($GLOBALS['tourivo_mock_fail_next_booking_insert'])) {
                        $GLOBALS['tourivo_mock_fail_next_booking_insert']--;
                        $this->rows_affected = 0;
                        return false;
                    }
                    $id = count($this->bookings) + 1;
                    $data['id'] = $id;
                    $data['created_at'] = $data['created_at'] ?? gmdate('Y-m-d H:i:s');
                    $this->bookings[$id] = $data;
                    $this->insert_id = $id;
                    $this->rows_affected = 1;
                    return 1;
                }
                if (stripos($table, 'tourivo_inquiries') !== false) {
                    $id = count($this->inquiries) + 1;
                    $data['id'] = $id;
                    $data['created_at'] = $data['created_at'] ?? gmdate('Y-m-d H:i:s');
                    $this->inquiries[$id] = $data;
                    $this->insert_id = $id;
                    $this->rows_affected = 1;
                    return 1;
                }
                if (stripos($table, 'tourivo_booking_items') !== false) {
                    $id = count($this->booking_items) + 1;
                    $data['id'] = $id;
                    $this->booking_items[$id] = $data;
                    $this->insert_id = $id;
                    $this->rows_affected = 1;
                    return 1;
                }
                if (stripos($table, 'tourivo_inventories') !== false) {
                    $key = "{$data['item_id']}_{$data['event_date']}";
                    $data['id'] = count($this->inventories) + 1;
                    $this->inventories[$key] = $data;
                    $this->insert_id = $data['id'];
                    $this->rows_affected = 1;
                    return 1;
                }
                if (stripos($table, 'tourivo_logs') !== false) {
                    $id = count($this->logs) + 1;
                    $data['id'] = $id;
                    $this->logs[$id] = $data;
                    $this->insert_id = $id;
                    $this->rows_affected = 1;
                    return 1;
                }
                $this->rows_affected = 1;
                return 1;
            }

            public function update(string $table, array $data, array $where, ?array $format = null, ?array $where_format = null): int|bool {
                if (stripos($table, 'tourivo_bookings') !== false) {
                    $id = (int) ($where['id'] ?? 0);
                    if (isset($this->bookings[$id])) {
                        if (isset($where['booking_status']) && $this->bookings[$id]['booking_status'] !== $where['booking_status']) {
                            $this->rows_affected = 0;
                            return 0;
                        }
                        $this->bookings[$id] = array_merge($this->bookings[$id], $data);
                        $this->rows_affected = 1;
                        return 1;
                    }
                    $this->rows_affected = 0;
                    return 0;
                }
                if (stripos($table, 'tourivo_inquiries') !== false) {
                    $id = (int) ($where['id'] ?? 0);
                    if (isset($this->inquiries[$id])) {
                        $this->inquiries[$id] = array_merge($this->inquiries[$id], $data);
                        $this->rows_affected = 1;
                        return 1;
                    }
                }
                if (stripos($table, 'tourivo_inventories') !== false) {
                    $id = (int) ($where['id'] ?? 0);
                    foreach ($this->inventories as $k => $inv) {
                        if ((int)($inv['id'] ?? 0) === $id) {
                            $this->inventories[$k] = array_merge($inv, $data);
                            $this->rows_affected = 1;
                            return 1;
                        }
                    }
                }
                $this->rows_affected = 1;
                return 1;
            }
        }
    }

    global $wpdb;
    $wpdb = new wpdb();

    // Mock WordPress REST classes if missing
    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request {
            public string $method;
            public string $route;
            public array $params = [];
            public function __construct(string $method = 'GET', string $route = '') {
                $this->method = $method;
                $this->route = $route;
            }
            public function set_body_params(array $params): void { $this->params = array_merge($this->params, $params); }
            public function set_json_params(array $params): void { $this->params = array_merge($this->params, $params); }
            public function set_query_params(array $params): void { $this->params = array_merge($this->params, $params); }
            public function set_param(string $key, mixed $value): void { $this->params[$key] = $value; }
            public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
            public function get_params(): array { return $this->params; }
            public function get_json_params(): array { return $this->params; }
        }
    }

    if (!function_exists('__return_true')) {
        function __return_true(): bool { return true; }
    }
    if (!function_exists('__return_false')) {
        function __return_false(): bool { return false; }
    }
    if (!function_exists('__return_null')) {
        function __return_null(): mixed { return null; }
    }
    if (!function_exists('__return_empty_array')) {
        function __return_empty_array(): array { return []; }
    }

    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response {
            public mixed $data;
            public int $status;
            public function __construct(mixed $data = null, int $status = 200) {
                $this->data = $data;
                $this->status = $status;
            }
            public function get_data(): mixed { return $this->data; }
            public function get_status(): int { return $this->status; }
        }
    }

    if (!class_exists('WP_Error')) {
        class WP_Error {
            public string $code;
            public string $message;
            public mixed $data;
            public function __construct(string $code = '', string $message = '', mixed $data = null) {
                $this->code = $code;
                $this->message = $message;
                $this->data = $data;
            }
            public function get_error_code(): string { return $this->code; }
            public function get_error_message(): string { return $this->message; }
            public function get_error_data(): mixed { return $this->data; }
        }
    }

    if (!function_exists('is_wp_error')) {
        function is_wp_error(mixed $thing): bool {
            return $thing instanceof WP_Error;
        }
    }

    if (!function_exists('dbDelta')) {
        function dbDelta(string|array $queries = '', bool $execute = true): array {
            return [];
        }
    }

    // Mock WordPress functions
    if (!function_exists('__')) {
        function __(string $text, string $domain = 'default'): string { return $text; }
    }
    if (!function_exists('esc_html__')) {
        function esc_html__(string $text, string $domain = 'default'): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('esc_attr__')) {
        function esc_attr__(string $text, string $domain = 'default'): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('esc_html')) {
        function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('esc_html_e')) {
        function esc_html_e(string $text, string $domain = 'default'): void { echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('_e')) {
        function _e(string $text, string $domain = 'default'): void { echo $text; }
    }
    if (!function_exists('_n')) {
        function _n(string $single, string $plural, int $number, string $domain = 'default'): string {
            return $number === 1 ? $single : $plural;
        }
    }
    if (!function_exists('esc_attr')) {
        function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('esc_attr_e')) {
        function esc_attr_e(string $text, string $domain = 'default'): void { echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('esc_url')) {
        function esc_url(string $url): string { return str_replace('&', '&#038;', $url); }
    }
    if (!function_exists('esc_url_raw')) {
        function esc_url_raw(string $url): string { return $url; }
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field(string $str): string { return trim(strip_tags($str)); }
    }
    if (!function_exists('sanitize_textarea_field')) {
        function sanitize_textarea_field(string $str): string { return trim(strip_tags($str)); }
    }
    if (!function_exists('sanitize_email')) {
        function sanitize_email(string $email): string { return filter_var(trim($email), FILTER_SANITIZE_EMAIL) ?: ''; }
    }
    if (!function_exists('sanitize_key')) {
        function sanitize_key(string $key): string { return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $key)); }
    }
    if (!function_exists('is_email')) {
        function is_email(string $email): bool { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL); }
    }
    if (!function_exists('absint')) {
        function absint(mixed $maybeint): int { return abs((int) $maybeint); }
    }
    if (!function_exists('wp_unslash')) {
        function wp_unslash(mixed $val): mixed { return is_string($val) ? stripslashes($val) : $val; }
    }
    if (!function_exists('wp_slash')) {
        function wp_slash(mixed $val): mixed { return is_string($val) ? addslashes($val) : $val; }
    }
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string { return json_encode($data, $options, $depth) ?: ''; }
    }
    if (!function_exists('wp_parse_url')) {
        function wp_parse_url(string $url, int $component = -1): mixed { return parse_url($url, $component); }
    }
    if (!function_exists('wp_date')) {
        function wp_date(string $format, ?int $timestamp = null): string { return gmdate($format, $timestamp ?? time()); }
    }
    if (!function_exists('current_time')) {
        function current_time(string $type, int $gmt = 0): string { return gmdate('Y-m-d H:i:s'); }
    }
    if (!function_exists('wp_salt')) {
        function wp_salt(string $scheme = 'auth'): string { return 'mock_secret_salt_1234567890'; }
    }
    if (!function_exists('wp_hash')) {
        function wp_hash(string $data, string $scheme = 'auth'): string { return hash_hmac('md5', $data, wp_salt($scheme)); }
    }
    if (!function_exists('get_bloginfo')) {
        function get_bloginfo(string $show = '', string $filter = 'raw'): string { return 'Tourivo Test Site'; }
    }
    if (!function_exists('home_url')) {
        function home_url(string $path = ''): string { return 'http://localhost' . $path; }
    }
    if (!function_exists('admin_url')) {
        function admin_url(string $path = ''): string { return 'http://localhost/wp-admin/' . $path; }
    }
    if (!function_exists('add_query_arg')) {
        function add_query_arg(array $args, string $url): string {
            $query = http_build_query($args);
            $sep = str_contains($url, '?') ? '&' : '?';
            return $url . $sep . $query;
        }
    }
    if (!function_exists('apply_filters')) {
        function apply_filters(string $hook_name, mixed $value, ...$args): mixed {
            global $tourivo_mock_filters;
            if (isset($tourivo_mock_filters[$hook_name])) {
                foreach ($tourivo_mock_filters[$hook_name] as $cb) {
                    $value = $cb($value, ...$args);
                }
            }
            return $value;
        }
    }
    if (!function_exists('wp_generate_password')) {
        function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string {
            return substr(md5(uniqid((string) mt_rand(), true)), 0, $length);
        }
    }
    if (!function_exists('untrailingslashit')) {
        function untrailingslashit(string $string): string {
            return rtrim($string, '/\\');
        }
    }
    if (!function_exists('trailingslashit')) {
        function trailingslashit(string $string): string {
            return rtrim($string, '/\\') . '/';
        }
    }
    if (!function_exists('locate_template')) {
        function locate_template(array|string $template_names, bool $load = false, bool $require_once = true, array $args = []): string {
            return '';
        }
    }
    if (!function_exists('add_filter')) {
        function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void {
            global $tourivo_mock_filters;
            $tourivo_mock_filters[$hook_name][] = $callback;
        }
    }
    if (!function_exists('remove_filter')) {
        function remove_filter(string $hook_name, callable $callback, int $priority = 10): bool {
            global $tourivo_mock_filters;
            if (isset($tourivo_mock_filters[$hook_name])) {
                foreach ($tourivo_mock_filters[$hook_name] as $idx => $cb) {
                    if ($cb === $callback) {
                        unset($tourivo_mock_filters[$hook_name][$idx]);
                        return true;
                    }
                }
            }
            return true;
        }
    }
    if (!function_exists('add_action')) {
        function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void {
            global $tourivo_mock_action_callbacks;
            $tourivo_mock_action_callbacks[$hook_name][] = [
                'callback'      => $callback,
                'priority'      => $priority,
                'accepted_args' => $accepted_args,
            ];
        }
    }
    if (!function_exists('remove_action')) {
        function remove_action(string $hook_name, callable $callback, int $priority = 10): bool {
            global $tourivo_mock_action_callbacks;
            if (isset($tourivo_mock_action_callbacks[$hook_name])) {
                foreach ($tourivo_mock_action_callbacks[$hook_name] as $idx => $entry) {
                    if ($entry['callback'] === $callback) {
                        unset($tourivo_mock_action_callbacks[$hook_name][$idx]);
                        return true;
                    }
                }
            }
            return true;
        }
    }
    if (!function_exists('do_action')) {
        function do_action(string $hook_name, ...$args): void {
            global $tourivo_mock_actions, $tourivo_mock_action_callbacks;
            $tourivo_mock_actions[] = ['hook' => $hook_name, 'args' => $args];
            if (!empty($tourivo_mock_action_callbacks[$hook_name])) {
                foreach ($tourivo_mock_action_callbacks[$hook_name] as $entry) {
                    $cb = $entry['callback'];
                    $accepted = $entry['accepted_args'] ?? count($args);
                    $passed = array_slice($args, 0, $accepted);
                    $cb(...$passed);
                }
            }
        }
    }
    if (!function_exists('get_option')) {
        function get_option(string $option, mixed $default = false): mixed {
            global $tourivo_mock_options;
            return $tourivo_mock_options[$option] ?? $default;
        }
    }
    if (!function_exists('update_option')) {
        function update_option(string $option, mixed $value, mixed $autoload = null): bool {
            global $tourivo_mock_options;
            $tourivo_mock_options[$option] = $value;
            return true;
        }
    }
    if (!function_exists('delete_option')) {
        function delete_option(string $option): bool {
            global $tourivo_mock_options;
            unset($tourivo_mock_options[$option]);
            return true;
        }
    }
    if (!function_exists('get_transient')) {
        function get_transient(string $transient): mixed {
            global $tourivo_mock_transients;
            return $tourivo_mock_transients[$transient] ?? false;
        }
    }
    if (!function_exists('set_transient')) {
        function set_transient(string $transient, mixed $value, int $expiration = 0): bool {
            global $tourivo_mock_transients;
            $tourivo_mock_transients[$transient] = $value;
            return true;
        }
    }
    if (!function_exists('delete_transient')) {
        function delete_transient(string $transient): bool {
            global $tourivo_mock_transients;
            unset($tourivo_mock_transients[$transient]);
            return true;
        }
    }
    if (!function_exists('wp_using_ext_object_cache')) {
        function wp_using_ext_object_cache(?bool $using = null): bool {
            return !empty($GLOBALS['tourivo_mock_ext_object_cache']);
        }
    }
    if (!function_exists('wp_cache_add')) {
        function wp_cache_add(string|int $key, mixed $data, string $group = '', int $expire = 0): bool {
            global $tourivo_mock_transients;
            $k = 'cache_' . $group . '_' . $key;
            if (isset($tourivo_mock_transients[$k])) {
                return false;
            }
            $tourivo_mock_transients[$k] = $data;
            return true;
        }
    }
    if (!function_exists('wp_cache_incr')) {
        function wp_cache_incr(string|int $key, int $offset = 1, string $group = ''): int|false {
            global $tourivo_mock_transients;
            $k = 'cache_' . $group . '_' . $key;
            if (!isset($tourivo_mock_transients[$k])) {
                return false;
            }
            $tourivo_mock_transients[$k] = (int) $tourivo_mock_transients[$k] + $offset;
            return $tourivo_mock_transients[$k];
        }
    }
    if (!function_exists('wp_cache_get')) {
        function wp_cache_get(string|int $key, string $group = '', bool $force = false, bool &$found = null): mixed {
            global $tourivo_mock_transients;
            $k = 'cache_' . $group . '_' . $key;
            if (isset($tourivo_mock_transients[$k])) {
                $found = true;
                return $tourivo_mock_transients[$k];
            }
            $found = false;
            return false;
        }
    }
    if (!function_exists('wp_cache_set')) {
        function wp_cache_set(string|int $key, mixed $data, string $group = '', int $expire = 0): bool {
            global $tourivo_mock_transients;
            $k = 'cache_' . $group . '_' . $key;
            $tourivo_mock_transients[$k] = $data;
            return true;
        }
    }
    if (!function_exists('wp_cache_delete')) {
        function wp_cache_delete(string $key, string $group = ''): bool {
            global $tourivo_mock_transients;
            $k = 'cache_' . $group . '_' . $key;
            unset($tourivo_mock_transients[$k]);
            return true;
        }
    }
    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in(): bool { return false; }
    }
    if (!function_exists('get_current_user_id')) {
        function get_current_user_id(): int { return 1; }
    }
    if (!function_exists('current_user_can')) {
        function current_user_can(string $capability, ...$args): bool { return true; }
    }
    if (!function_exists('get_user_by')) {
        function get_user_by(string $field, mixed $value): object|bool {
            return (object) ['ID' => 1, 'user_email' => 'user@example.com', 'display_name' => 'Test User'];
        }
    }
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        class MockPHPMailer {
            public string $AltBody = '';
            public string $Body = '';
            public string $Subject = '';
            public array $to = [];
            public array $headers = [];
        }
    }

    $GLOBALS['tourivo_mock_sent_emails'] = [];
    $GLOBALS['tourivo_mock_scheduled_events'] = [];

    if (!function_exists('wp_mail')) {
        function wp_mail(mixed ...$args): bool {
            $to          = $args[0] ?? '';
            $subject     = $args[1] ?? '';
            $message     = $args[2] ?? '';
            $headers     = $args[3] ?? '';
            $attachments = $args[4] ?? [];

            $mockMailer = new MockPHPMailer();
            $mockMailer->Subject = is_string($subject) ? $subject : '';
            $mockMailer->Body    = is_string($message) ? $message : '';
            do_action('phpmailer_init', $mockMailer);

            $atts = [
                'to'          => $to,
                'subject'     => $subject,
                'message'     => $message,
                'headers'     => $headers,
                'attachments' => $attachments,
                'alt_body'    => $mockMailer->AltBody,
            ];

            $pre = apply_filters('pre_wp_mail', null, $atts);
            if (null !== $pre) {
                if ($pre === false) {
                    do_action('wp_mail_failed', new WP_Error('wp_mail_failed', 'Simulated mail failure'));
                    return false;
                }
                $GLOBALS['tourivo_mock_sent_emails'][] = $atts;
                return (bool) $pre;
            }

            $GLOBALS['tourivo_mock_sent_emails'][] = $atts;
            return true;
        }
    }

    if (!function_exists('wp_schedule_single_event')) {
        function wp_schedule_single_event(int $timestamp, string $hook, array $args = [], bool $wp_error = false): bool {
            $GLOBALS['tourivo_mock_scheduled_events'][] = [
                'type'      => 'single',
                'timestamp' => $timestamp,
                'hook'      => $hook,
                'args'      => $args,
            ];
            return true;
        }
    }

    if (!function_exists('wp_schedule_event')) {
        function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = [], bool $wp_error = false): bool {
            $GLOBALS['tourivo_mock_scheduled_events'][] = [
                'type'       => 'recurring',
                'recurrence' => $recurrence,
                'timestamp'  => $timestamp,
                'hook'       => $hook,
                'args'       => $args,
            ];
            return true;
        }
    }

    if (!function_exists('wp_next_scheduled')) {
        function wp_next_scheduled(string $hook, array $args = []): int|false {
            foreach ($GLOBALS['tourivo_mock_scheduled_events'] ?? [] as $ev) {
                if ($ev['hook'] === $hook) {
                    return $ev['timestamp'] ?? time();
                }
            }
            return false;
        }
    }

    if (!function_exists('wp_clear_scheduled_hook')) {
        function wp_clear_scheduled_hook(string $hook, array $args = [], bool $wp_error = false): int|false {
            $count = 0;
            if (isset($GLOBALS['tourivo_mock_scheduled_events'])) {
                foreach ($GLOBALS['tourivo_mock_scheduled_events'] as $k => $ev) {
                    if ($ev['hook'] === $hook) {
                        unset($GLOBALS['tourivo_mock_scheduled_events'][$k]);
                        $count++;
                    }
                }
            }
            return $count;
        }
    }

    if (!function_exists('spawn_cron')) {
        function spawn_cron(int $gmt_time = 0): bool { return true; }
    }

    if (!function_exists('as_enqueue_async_action')) {
        function as_enqueue_async_action(string $hook, array $args = [], string $group = ''): int {
            $GLOBALS['tourivo_mock_scheduled_events'][] = [
                'type'  => 'action_scheduler_async',
                'hook'  => $hook,
                'args'  => $args,
                'group' => $group,
            ];
            return count($GLOBALS['tourivo_mock_scheduled_events']);
        }
    }

    if (!function_exists('as_schedule_single_action')) {
        function as_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = ''): int {
            $GLOBALS['tourivo_mock_scheduled_events'][] = [
                'type'      => 'action_scheduler_single',
                'timestamp' => $timestamp,
                'hook'      => $hook,
                'args'      => $args,
                'group'     => $group,
            ];
            return count($GLOBALS['tourivo_mock_scheduled_events']);
        }
    }

    // Mock Posts and PostMeta
    if (!function_exists('wp_insert_post')) {
        function wp_insert_post(array $args): int {
            global $tourivo_mock_posts;
            $id = count($tourivo_mock_posts) + 1;
            $args['ID'] = $id;
            $args['post_status'] = $args['post_status'] ?? 'publish';
            $tourivo_mock_posts[$id] = new WP_Post($args);
            return $id;
        }
    }
    if (!function_exists('wp_update_post')) {
        function wp_update_post(array $args): int {
            global $tourivo_mock_posts;
            $id = (int) ($args['ID'] ?? 0);
            if (isset($tourivo_mock_posts[$id])) {
                $cur = (array) $tourivo_mock_posts[$id];
                $tourivo_mock_posts[$id] = new WP_Post(array_merge($cur, $args));
            }
            return $id;
        }
    }
    if (!function_exists('get_the_title')) {
        function get_the_title(int|WP_Post|null $post = 0): string {
            global $tourivo_mock_posts;
            $id = is_object($post) ? (int) $post->ID : (int) $post;
            return $tourivo_mock_posts[$id]->post_title ?? 'Test Item Title';
        }
    }
    if (!function_exists('get_permalink')) {
        function get_permalink(int|WP_Post $post = 0): string {
            $id = is_object($post) ? (int) $post->ID : (int) $post;
            return 'http://localhost/item/' . $id;
        }
    }
    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags(string $text, bool $remove_breaks = false): string { return strip_tags($text); }
    }
    if (!function_exists('wp_trim_words')) {
        function wp_trim_words(string $text, int $num_words = 55): string { return $text; }
    }
    if (!function_exists('is_singular')) {
        function is_singular(string|array $post_types = ''): bool { return false; }
    }
    if (!function_exists('get_the_ID')) {
        function get_the_ID(): int { return 1; }
    }
    if (!function_exists('get_post')) {
        function get_post(int $postId): ?WP_Post {
            global $tourivo_mock_posts;
            return $tourivo_mock_posts[$postId] ?? null;
        }
    }
    if (!function_exists('get_post_status')) {
        function get_post_status(int $postId): string|bool {
            global $tourivo_mock_posts;
            return $tourivo_mock_posts[$postId]->post_status ?? false;
        }
    }
    if (!function_exists('get_post_type')) {
        function get_post_type(int $postId): string|bool {
            global $tourivo_mock_posts;
            return $tourivo_mock_posts[$postId]->post_type ?? false;
        }
    }
    if (!function_exists('get_posts')) {
        function get_posts(array $args): array {
            global $tourivo_mock_posts, $tourivo_mock_postmeta;
            $res = [];
            foreach ($tourivo_mock_posts as $id => $post) {
                if (isset($args['post_type'])) {
                    $pts = (array) $args['post_type'];
                    if (!in_array($post->post_type, $pts, true)) continue;
                }
                if (isset($args['title']) && !empty($args['title'])) {
                    if ($post->post_title !== $args['title']) continue;
                }
                if (isset($args['meta_key']) && isset($args['meta_value'])) {
                    if (($tourivo_mock_postmeta[$id][$args['meta_key']] ?? null) != $args['meta_value']) {
                        continue;
                    }
                }
                $res[] = ($args['fields'] ?? '') === 'ids' ? $id : $post;
                if (isset($args['posts_per_page']) && $args['posts_per_page'] > 0 && count($res) >= $args['posts_per_page']) {
                    break;
                }
            }
            return $res;
        }
    }
    if (!function_exists('get_post_meta')) {
        function get_post_meta(int $postId, string $key = '', bool $single = false): mixed {
            global $tourivo_mock_postmeta;
            if (empty($key)) return $tourivo_mock_postmeta[$postId] ?? [];
            $val = $tourivo_mock_postmeta[$postId][$key] ?? '';
            return $single ? $val : [$val];
        }
    }
    if (!function_exists('update_post_meta')) {
        function update_post_meta(int $postId, string $key, mixed $value): bool {
            global $tourivo_mock_postmeta;
            $tourivo_mock_postmeta[$postId][$key] = $value;
            return true;
        }
    }
    if (!function_exists('delete_post_meta')) {
        function delete_post_meta(int $postId, string $key, mixed $value = ''): bool {
            global $tourivo_mock_postmeta;
            if (isset($tourivo_mock_postmeta[$postId][$key])) {
                unset($tourivo_mock_postmeta[$postId][$key]);
            }
            return true;
        }
    }
    if (!function_exists('wp_delete_post')) {
        function wp_delete_post(int $postId, bool $force = false): bool {
            global $tourivo_mock_posts, $tourivo_mock_postmeta;
            unset($tourivo_mock_posts[$postId], $tourivo_mock_postmeta[$postId]);
            return true;
        }
    }
    if (!function_exists('wp_set_object_terms')) {
        function wp_set_object_terms(int $object_id, array|int|string $terms, string $taxonomy, bool $append = false): array { return (array) $terms; }
    }
    if (!function_exists('term_exists')) {
        function term_exists(string|int $term, string $taxonomy = ''): int|array|null { return 1; }
    }
    if (!function_exists('wp_create_nonce')) {
        function wp_create_nonce(string|int $action = -1): string {
            return 'mock_nonce_' . md5((string) $action);
        }
    }
    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce(string $nonce, string|int $action = -1): int|bool {
            return ($nonce === 'mock_nonce_' . md5((string) $action) || $nonce === 'valid_nonce' || $nonce === 'tourivo_test_nonce');
        }
    }
    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer(string|int $action = -1, string $query_arg = 'false', bool $die = true): int|bool {
            $nonce = $_POST[$query_arg] ?? $_GET[$query_arg] ?? ($_POST['nonce'] ?? $_GET['nonce'] ?? '');
            if (wp_verify_nonce((string)$nonce, $action)) {
                return 1;
            }
            if ($die) {
                wp_send_json_error(['message' => 'Invalid security token / nonce.'], 403);
                throw new \Exception('check_ajax_referer_died');
            }
            return false;
        }
    }
    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success(mixed $data = null, ?int $status_code = null, int $options = 0): void {
            $response = ['success' => true];
            if ($data !== null) {
                $response['data'] = $data;
            }
            $GLOBALS['tourivo_last_ajax_response'] = [
                'status' => $status_code ?: 200,
                'body'   => $response,
            ];
        }
    }
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error(mixed $data = null, ?int $status_code = null, int $options = 0): void {
            $response = ['success' => false];
            if ($data !== null) {
                $response['data'] = $data;
            }
            $GLOBALS['tourivo_last_ajax_response'] = [
                'status' => $status_code ?: 400,
                'body'   => $response,
            ];
        }
    }

    if (!function_exists('nocache_headers')) {
        function nocache_headers(): array { return []; }
    }

    $GLOBALS['tourivo_mock_shortcodes'] = [];
    if (!function_exists('add_shortcode')) {
        function add_shortcode(string $tag, callable $callback): void {
            global $tourivo_mock_shortcodes;
            $tourivo_mock_shortcodes[$tag] = $callback;
        }
    }
    if (!function_exists('do_shortcode')) {
        function do_shortcode(string $content, bool $ignore_html = false): string {
            global $tourivo_mock_shortcodes;
            foreach ($tourivo_mock_shortcodes as $tag => $cb) {
                if (str_contains($content, "[{$tag}")) {
                    $content = (string) preg_replace_callback("/\\[{$tag}(?:\\s+([^\\]]*))?\\]/", function ($matches) use ($cb) {
                        $attrStr = $matches[1] ?? '';
                        $atts = [];
                        if (!empty($attrStr)) {
                            preg_match_all('/(\\w+)=["\']?([^"\']+)["\']?/', $attrStr, $attrMatches, PREG_SET_ORDER);
                            foreach ($attrMatches as $m) {
                                $atts[$m[1]] = $m[2];
                            }
                        }
                        return (string) $cb($atts);
                    }, $content);
                }
            }
            return $content;
        }
    }
    $GLOBALS['tourivo_mock_usermeta'] = [];

    if (!function_exists('update_user_meta')) {
        function update_user_meta(int $userId, string $key, mixed $value): bool {
            global $tourivo_mock_usermeta;
            $tourivo_mock_usermeta[$userId][$key] = $value;
            return true;
        }
    }

    if (!function_exists('get_user_meta')) {
        function get_user_meta(int $userId, string $key = '', bool $single = false): mixed {
            global $tourivo_mock_usermeta;
            if (empty($key)) return $tourivo_mock_usermeta[$userId] ?? [];
            $val = $tourivo_mock_usermeta[$userId][$key] ?? '';
            return $single ? $val : [$val];
        }
    }

    if (!function_exists('delete_user_meta')) {
        function delete_user_meta(int $userId, string $key, mixed $value = ''): bool {
            global $tourivo_mock_usermeta;
            if (isset($tourivo_mock_usermeta[$userId][$key])) {
                unset($tourivo_mock_usermeta[$userId][$key]);
            }
            return true;
        }
    }

    if (!function_exists('get_privacy_policy_url')) {
        function get_privacy_policy_url(): string {
            return 'http://localhost/privacy-policy';
        }
    }

    if (!function_exists('wp_add_privacy_policy_content')) {
        function wp_add_privacy_policy_content(string $plugin_name, string $policy_text): void {
            $GLOBALS['tourivo_mock_privacy_policy_content'][$plugin_name] = $policy_text;
        }
    }

    if (!class_exists('WP_REST_Server')) {
        class WP_REST_Server {
            public const READABLE  = 'GET';
            public const CREATABLE = 'POST';
        }
    }

    if (!function_exists('sanitize_hex_color')) {
        function sanitize_hex_color(string $color): ?string {
            return preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color) ? $color : null;
        }
    }

    if (!function_exists('get_role')) {
        function get_role(string $role): ?object { return null; }
    }
    if (!function_exists('is_multisite')) {
        function is_multisite(): bool { return !empty($GLOBALS['tourivo_mock_is_multisite']); }
    }
    if (!function_exists('get_sites')) {
        function get_sites(array $args = []): array { return $GLOBALS['tourivo_mock_sites'] ?? []; }
    }
    if (!function_exists('switch_to_blog')) {
        function switch_to_blog(int $blogId): bool { $GLOBALS['tourivo_mock_blog_log'][] = "switch:{$blogId}"; return true; }
    }
    if (!function_exists('restore_current_blog')) {
        function restore_current_blog(): bool { $GLOBALS['tourivo_mock_blog_log'][] = 'restore'; return true; }
    }

    if (!function_exists('register_rest_route')) {
        function register_rest_route(string $route_namespace, string $route, array $args = [], bool $override = false): bool {
            $GLOBALS['tourivo_mock_rest_routes'][$route_namespace . $route] = $args;
            return true;
        }
    }

    if (!function_exists('wp_kses_post')) {
        function wp_kses_post(string $data): string {
            return $data;
        }
    }

    require_once dirname(__DIR__) . '/app/Support/functions.php';
}
