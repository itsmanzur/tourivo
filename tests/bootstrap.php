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
    $GLOBALS['tourivo_mock_options'] = [];
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
            public array $bookings = [];
            public array $booking_items = [];
            public array $inventories = [];
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

                // 1. INSERT INTO wp_tourivo_inventories
                if (stripos($trimmed, 'INSERT INTO') !== false && stripos($trimmed, 'tourivo_inventories') !== false) {
                    if (preg_match("/VALUES\s*\(\s*(\d+)\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*'([^']+)'/is", $trimmed, $m)) {
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
                            'status'          => $status,
                        ];
                        $this->insert_id = $id;
                        $this->rows_affected = 1;
                        return 1;
                    }
                }

                // 2. UPDATE wp_tourivo_inventories
                if (stripos($trimmed, 'UPDATE') !== false && stripos($trimmed, 'tourivo_inventories') !== false) {
                    if (preg_match('/SET\s+booked_count\s*=\s*(\d+)\s*,\s*reserved_count\s*=\s*(\d+)\s*,\s*status\s*=\s*\'([^\']+)\'.*WHERE\s+id\s*=\s*(\d+)/is', $trimmed, $m)) {
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

                $this->rows_affected = 1;
                return 1;
            }

            public function get_var(?string $query = null, int $x = 0, int $y = 0): mixed {
                if (!$query) return null;
                if (stripos($query, 'COUNT(*)') !== false) {
                    if (stripos($query, 'tourivo_bookings') !== false) {
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
                return null;
            }

            public function get_row(?string $query = null, string $output = OBJECT, int $y = 0): mixed {
                if (!$query) return null;

                if (stripos($query, 'tourivo_bookings') !== false) {
                    if (preg_match('/id\s*=\s*(\d+)/i', $query, $m)) {
                        $id = (int) $m[1];
                        return isset($this->bookings[$id]) ? (object) $this->bookings[$id] : null;
                    }
                    if (preg_match("/booking_code\s*=\s*'([^']+)'/i", $query, $m)) {
                        $code = $m[1];
                        foreach ($this->bookings as $b) {
                            if (($b['booking_code'] ?? '') === $code) {
                                return (object) $b;
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
                if (stripos($query, 'tourivo_booking_items') !== false) {
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
                return [];
            }

            public function insert(string $table, array $data, ?array $format = null): int|bool {
                if (stripos($table, 'tourivo_bookings') !== false) {
                    $id = count($this->bookings) + 1;
                    $data['id'] = $id;
                    $data['created_at'] = $data['created_at'] ?? gmdate('Y-m-d H:i:s');
                    $this->bookings[$id] = $data;
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
            public function set_body_params(array $params): void { $this->params = $params; }
            public function get_params(): array { return $this->params; }
            public function get_json_params(): array { return $this->params; }
        }
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
    if (!function_exists('do_action')) {
        function do_action(string $hook_name, ...$args): void {
            global $tourivo_mock_actions;
            $tourivo_mock_actions[] = ['hook' => $hook_name, 'args' => $args];
        }
    }
    if (!function_exists('add_action')) {
        function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void {}
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
    if (!function_exists('wp_cache_delete')) {
        function wp_cache_delete(string $key, string $group = ''): bool { return true; }
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
    if (!function_exists('wp_mail')) {
        function wp_mail(mixed ...$args): bool { return true; }
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
                if (isset($args['meta_key']) && isset($args['meta_value'])) {
                    if (($tourivo_mock_postmeta[$id][$args['meta_key']] ?? null) != $args['meta_value']) {
                        continue;
                    }
                }
                $res[] = ($args['fields'] ?? '') === 'ids' ? $id : $post;
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
    if (!function_exists('wp_insert_term')) {
        function wp_insert_term(string $term, string $taxonomy, array $args = []): array { return ['term_id' => 1]; }
    }

    require_once dirname(__DIR__) . '/app/Support/functions.php';
}
