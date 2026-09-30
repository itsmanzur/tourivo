<?php
/**
 * Unit Test for ClientIp with Proxy & Anti-Spoofing Verification
 */

declare(strict_types=1);

namespace {
    if (!function_exists('get_option')) {
        $GLOBALS['wp_test_options'] = [];
        function get_option(string $key, mixed $default = []) {
            return $GLOBALS['wp_test_options'][$key] ?? $default;
        }
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field(string $str): string {
            return trim(strip_tags($str));
        }
    }
    if (!function_exists('wp_unslash')) {
        function wp_unslash(mixed $val): mixed {
            return $val;
        }
    }
    if (!function_exists('apply_filters')) {
        $GLOBALS['wp_test_filters'] = [];
        function apply_filters(string $tag, mixed $value, mixed ...$args): mixed {
            if (isset($GLOBALS['wp_test_filters'][$tag])) {
                return call_user_func($GLOBALS['wp_test_filters'][$tag], $value, ...$args);
            }
            return $value;
        }
    }
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
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
    use Tourivo\Support\ClientIp;

    class ClientIpTest
    {
        private function assert(string $desc, bool $condition): void
        {
            if ($condition) {
                echo "[PASS] {$desc}" . PHP_EOL;
            } else {
                echo "[FAIL] {$desc}" . PHP_EOL;
                throw new \RuntimeException("Assertion failed: {$desc}");
            }
        }

        public function run(): void
        {
            echo "==================================================" . PHP_EOL;
            echo "Running Tourivo ClientIp Anti-Spoofing Test Suite" . PHP_EOL;
            echo "==================================================" . PHP_EOL;

            // Test 1: Direct mode ('disabled') ignores spoofed headers
            $GLOBALS['wp_test_options']['tourivo_settings'] = ['proxy_mode' => 'disabled'];
            $_SERVER['REMOTE_ADDR'] = '203.0.113.195';
            $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.2.3.4';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
            $ip = ClientIp::get();
            $this->assert("1. Disabled mode returns REMOTE_ADDR directly and ignores headers", $ip === '203.0.113.195');

            // Test 2: Spoofed CF-Connecting-IP header from untrusted direct origin connection
            $GLOBALS['wp_test_options']['tourivo_settings'] = ['proxy_mode' => 'cloudflare'];
            $_SERVER['REMOTE_ADDR'] = '198.51.100.22'; // Random non-CF public IP
            $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.10'; // Attacker spoof
            $ip = ClientIp::get();
            $this->assert("2. Cloudflare mode rejects spoofed CF header from non-CF remote IP", $ip === '198.51.100.22');

            // Test 3: Legitimate Cloudflare edge server connection
            $GLOBALS['wp_test_options']['tourivo_settings'] = ['proxy_mode' => 'cloudflare'];
            $_SERVER['REMOTE_ADDR'] = '172.64.32.10'; // Valid Cloudflare IPv4 range (172.64.0.0/13)
            $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.10'; // Genuine visitor IP
            $ip = ClientIp::get();
            $this->assert("3. Cloudflare mode accepts CF header from verified Cloudflare edge IP", $ip === '203.0.113.10');

            // Test 4: Reverse Proxy mode rejects XFF from untrusted remote IP
            $GLOBALS['wp_test_options']['tourivo_settings'] = ['proxy_mode' => 'reverse_proxy'];
            $_SERVER['REMOTE_ADDR'] = '198.51.100.50'; // Non-trusted remote
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99, 10.0.0.1';
            $ip = ClientIp::get();
            $this->assert("4. Reverse proxy mode rejects XFF header from untrusted remote IP", $ip === '198.51.100.50');

            // Test 5: Reverse Proxy mode from trusted private proxy evaluates XFF right-to-left
            $GLOBALS['wp_test_options']['tourivo_settings'] = [
                'proxy_mode' => 'reverse_proxy',
                'trusted_proxies' => '10.0.0.0/8, 192.168.1.50'
            ];
            $_SERVER['REMOTE_ADDR'] = '10.0.0.2'; // Trusted proxy
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.88, 192.168.1.50'; // Client, intermediate proxy
            $ip = ClientIp::get();
            $this->assert("5. Reverse proxy mode parses XFF right-to-left and finds first untrusted client IP", $ip === '203.0.113.88');

            // Test 6: Custom Cloudflare range override filter
            $GLOBALS['wp_test_filters']['tourivo/cloudflare_ranges'] = function (array $ranges): array {
                $ranges[] = '198.51.100.0/24'; // Custom edge range
                return $ranges;
            };
            $GLOBALS['wp_test_options']['tourivo_settings'] = ['proxy_mode' => 'cloudflare'];
            $_SERVER['REMOTE_ADDR'] = '198.51.100.55'; // Now matches custom range
            $_SERVER['HTTP_CF_CONNECTING_IP'] = '192.0.2.123';
            $ip = ClientIp::get();
            $this->assert("6. Custom Cloudflare range filter works dynamically", $ip === '192.0.2.123');

            echo "==================================================" . PHP_EOL;
            echo ">>> ALL CLIENT IP ANTI-SPOOFING TESTS PASSED! <<<" . PHP_EOL;
            echo "==================================================" . PHP_EOL;
        }
    }

    $test = new ClientIpTest();
    $test->run();
}
