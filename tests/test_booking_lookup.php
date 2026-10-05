<?php
// phpcs:ignoreFile
/**
 * Unit Test for Booking Lookup AJAX & Voucher URL Formatting
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

    if (!function_exists('wp_salt')) {
        function wp_salt(string $scheme = 'auth'): string {
            return 'unit_test_salt_secret_key_12345';
        }
    }

    if (!function_exists('esc_url_raw')) {
        function esc_url_raw(string $url): string {
            return str_replace([' '], ['%20'], $url);
        }
    }

    if (!function_exists('esc_url')) {
        function esc_url(string $url): string {
            return str_replace('&', '&#038;', esc_url_raw($url));
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html(string $text): string {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('esc_html__')) {
        function esc_html__(string $text, string $domain = 'default'): string {
            return esc_html($text);
        }
    }

    if (!function_exists('__')) {
        function __(string $text, string $domain = 'default'): string {
            return $text;
        }
    }

    if (!function_exists('admin_url')) {
        function admin_url(string $path = '', string $scheme = 'admin'): string {
            return 'https://example.com/wp-admin/' . ltrim($path, '/');
        }
    }

    if (!function_exists('add_query_arg')) {
        function add_query_arg(...$args): string {
            $qs = [];
            if (is_array($args[0])) {
                $params = $args[0];
                $url = $args[1] ?? 'https://example.com/wp-admin/admin-post.php';
            } else {
                $params = [$args[0] => $args[1]];
                $url = $args[2] ?? 'https://example.com/wp-admin/admin-post.php';
            }
            $parts = explode('?', $url, 2);
            $base = $parts[0];
            if (isset($parts[1])) {
                parse_str($parts[1], $existing);
                $params = array_merge($existing, $params);
            }
            return $base . '?' . http_build_query($params);
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters(string $tag, mixed $value, mixed ...$args): mixed {
            return $value;
        }
    }

    if (!function_exists('wp_unslash')) {
        function wp_unslash(mixed $val): mixed {
            return $val;
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field(string $str): string {
            return trim(strip_tags($str));
        }
    }

    if (!function_exists('sanitize_email')) {
        function sanitize_email(string $email): string {
            return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
        }
    }

    if (!function_exists('is_email')) {
        function is_email(string $email): bool {
            return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
        }
    }

    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer(string $action, string $query_arg = 'false', bool $die = true): bool {
            return true;
        }
    }

    if (!function_exists('get_transient')) {
        function get_transient(string $transient): mixed {
            return false;
        }
    }

    if (!function_exists('set_transient')) {
        function set_transient(string $transient, mixed $value, int $expiration = 0): bool {
            return true;
        }
    }

    $GLOBALS['last_json_response'] = null;

    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success(mixed $data = null, ?int $status_code = null, int $options = 0): void {
            $GLOBALS['last_json_response'] = ['success' => true, 'data' => $data, 'status' => $status_code];
        }
    }

    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error(mixed $data = null, ?int $status_code = null, int $options = 0): void {
            $GLOBALS['last_json_response'] = ['success' => false, 'data' => $data, 'status' => $status_code];
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
    use Tourivo\Shortcodes\BookingLookupShortcode;

    class FakeWpdb
    {
        public string $prefix = 'wp_';
        public mixed $bookingRow = null;
        public array $itemsRows = [];

        public function prepare(string $query, ...$args): string
        {
            return $query;
        }

        public function get_row(string $query, string $output = OBJECT): mixed
        {
            return $this->bookingRow;
        }

        public function get_results(string $query, string $output = OBJECT): array
        {
            return $this->itemsRows;
        }
    }

    class BookingLookupTest
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
            echo "--- Running BookingLookup AJAX & Voucher URL Tests ---\n";

            global $wpdb;
            $fakeDb = new FakeWpdb();
            $wpdb = $fakeDb;

            // 1. Mock a booking with apostrophe in customer_name ("D'Souza & Sons")
            $booking = new \stdClass();
            $booking->id = 42;
            $booking->booking_code = 'TRV-DSOUZA99';
            $booking->customer_name = "Mario D'Souza & Sons";
            $booking->customer_email = "mario.dsouza@example.com";
            $booking->created_at = '2026-10-05 12:00:00';
            $booking->booking_status = 'confirmed';
            $booking->payment_status = 'paid';
            $booking->payment_method = 'credit_card';
            $booking->total_amount = 450.00;

            $item = new \stdClass();
            $item->item_title = "Cox's Bazar & Saint Martin Cruise";
            $item->item_type = 'tour';
            $item->total_price = 450.00;
            $item->check_in = '2026-11-10';
            $item->check_out = '2026-11-14';
            $item->quantity = 2;

            $fakeDb->bookingRow = $booking;
            $fakeDb->itemsRows = [$item];

            $_POST = [
                'nonce' => 'test_nonce',
                'booking_code' => 'TRV-DSOUZA99',
                'customer_email' => 'mario.dsouza@example.com',
            ];

            BookingLookupShortcode::handleLookupAjax();

            $response = $GLOBALS['last_json_response'];
            $this->assert('AJAX response was success', $response !== null && $response['success'] === true);

            $data = $response['data'];

            // (a) Customer name must contain raw "Mario D'Souza & Sons", NOT "Mario D&#039;Souza &amp; Sons"
            $this->assert(
                'Customer name is unescaped raw string for textContent',
                $data['customer_name'] === "Mario D'Souza & Sons"
            );

            // (b) Booking code and payment method
            $this->assert('Booking code matches', $data['booking_code'] === 'TRV-DSOUZA99');
            $this->assert('Payment method is Credit Card', $data['payment_method'] === 'Credit Card');

            // (c) voucher_url must not contain &#038; entity and should cleanly parse query parameters
            $voucherUrl = $data['voucher_url'];
            $this->assert('Voucher URL does not contain &#038;', strpos($voucherUrl, '&#038;') === false);

            $urlParts = parse_url($voucherUrl);
            parse_str($urlParts['query'] ?? '', $queryParams);

            $this->assert('Voucher URL has action tourivo_print_voucher', ($queryParams['action'] ?? '') === 'tourivo_print_voucher');
            $this->assert('Voucher URL has code parameter matching TRV-DSOUZA99', ($queryParams['code'] ?? '') === 'TRV-DSOUZA99');
            $this->assert('Voucher URL has valid non-empty token parameter', !empty($queryParams['token']));

            // (d) items_html should have properly escaped HTML
            $this->assert('items_html is formatted HTML string', strpos($data['items_html'], 'tourivo-lookup-item-row') !== false);

            echo "All BookingLookup tests passed successfully!\n\n";
        }
    }

    $test = new BookingLookupTest();
    $test->run();
}
