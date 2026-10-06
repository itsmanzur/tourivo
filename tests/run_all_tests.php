<?php
/**
 * Tourivo Master Test Suite Runner
 *
 * Runs all unit, integration, and security verification tests.
 * Usage: php tests/run_all_tests.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

// Load Unit tests
require_once __DIR__ . '/Unit/MoneyTest.php';
require_once __DIR__ . '/Unit/ClientIpTest.php';
require_once __DIR__ . '/Unit/DateValidationTest.php';

// Load Integration tests
require_once __DIR__ . '/Integration/ContainerWiringTest.php';
require_once __DIR__ . '/Integration/InventoryHoldTest.php';
require_once __DIR__ . '/Integration/BookingHygieneTest.php';
require_once __DIR__ . '/Integration/MediumFixesTest.php';
require_once __DIR__ . '/Integration/LowFixesTest.php';
require_once __DIR__ . '/Integration/BookingEngineTest.php';
require_once __DIR__ . '/Integration/BookingStatusTransitionTest.php';
require_once __DIR__ . '/Integration/ConcurrencyOverbookingTest.php';
require_once __DIR__ . '/Integration/RestSecurityTest.php';
require_once __DIR__ . '/Integration/LookupVoucherTest.php';
require_once __DIR__ . '/Integration/WebhookTest.php';
require_once __DIR__ . '/Integration/DataAndMiscTest.php';
require_once __DIR__ . '/Integration/AdminAvailabilityManagerTest.php';
require_once __DIR__ . '/Integration/PricingEngineTest.php';
require_once __DIR__ . '/Integration/EmailSystemTest.php';
require_once __DIR__ . '/Integration/PostBookingExperienceTest.php';
require_once __DIR__ . '/Integration/PrivacyGdprTest.php';

echo "=====================================================\n";
echo "           Tourivo Automated Test Suite\n";
echo "=====================================================\n\n";

$testClasses = [
    \Tourivo\Tests\Unit\MoneyTest::class,
    \Tourivo\Tests\Unit\ClientIpTest::class,
    \Tourivo\Tests\Unit\DateValidationTest::class,
    \Tourivo\Tests\Integration\ContainerWiringTest::class,
    \Tourivo\Tests\Integration\InventoryHoldTest::class,
    \Tourivo\Tests\Integration\BookingHygieneTest::class,
    \Tourivo\Tests\Integration\MediumFixesTest::class,
    \Tourivo\Tests\Integration\LowFixesTest::class,
    \Tourivo\Tests\Integration\BookingEngineTest::class,
    \Tourivo\Tests\Integration\BookingStatusTransitionTest::class,
    \Tourivo\Tests\Integration\ConcurrencyOverbookingTest::class,
    \Tourivo\Tests\Integration\RestSecurityTest::class,
    \Tourivo\Tests\Integration\LookupVoucherTest::class,
    \Tourivo\Tests\Integration\WebhookTest::class,
    \Tourivo\Tests\Integration\DataAndMiscTest::class,
    \Tourivo\Tests\Integration\AdminAvailabilityManagerTest::class,
    \Tourivo\Tests\Integration\PricingEngineTest::class,
    \Tourivo\Tests\Integration\EmailSystemTest::class,
    \Tourivo\Tests\Integration\PostBookingExperienceTest::class,
    \Tourivo\Tests\Integration\PrivacyGdprTest::class,
];

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$failures = [];

class SimpleAssertionTrait {
    public function assertTrue($condition, string $msg = ''): void {
        if (!$condition) throw new \Exception($msg ?: 'Failed asserting that condition is true.');
    }
    public function assertFalse($condition, string $msg = ''): void {
        if ($condition) throw new \Exception($msg ?: 'Failed asserting that condition is false.');
    }
    public function assertEquals($expected, $actual, string $msg = ''): void {
        if ($expected !== $actual) throw new \Exception($msg ?: "Failed asserting that '$actual' equals '$expected'.");
    }
    public function assertNotEquals($expected, $actual, string $msg = ''): void {
        if ($expected === $actual) throw new \Exception($msg ?: "Failed asserting that '$actual' does not equal '$expected'.");
    }
    public function assertNotEmpty($value, string $msg = ''): void {
        if (empty($value)) throw new \Exception($msg ?: 'Failed asserting that value is not empty.');
    }
    public function assertEmpty($value, string $msg = ''): void {
        if (!empty($value)) throw new \Exception($msg ?: 'Failed asserting that value is empty.');
    }
    public function assertCount(int $count, $array, string $msg = ''): void {
        if (count($array) !== $count) throw new \Exception($msg ?: "Failed asserting that count is $count.");
    }
    public function assertStringContainsString(string $needle, string $haystack, string $msg = ''): void {
        if (!str_contains($haystack, $needle)) throw new \Exception($msg ?: "Failed asserting that '$haystack' contains '$needle'.");
    }
    public function assertStringNotContainsString(string $needle, string $haystack, string $msg = ''): void {
        if (str_contains($haystack, $needle)) throw new \Exception($msg ?: "Failed asserting that '$haystack' does not contain '$needle'.");
    }
    public function assertNotNull($value, string $msg = ''): void {
        if ($value === null) throw new \Exception($msg ?: 'Failed asserting that value is not null.');
    }
    public function assertArrayHasKey($key, $array, string $msg = ''): void {
        if (!array_key_exists($key, $array)) throw new \Exception($msg ?: "Failed asserting array has key '$key'.");
    }
}

foreach ($testClasses as $class) {
    echo "Suite: " . substr(strrchr($class, "\\"), 1) . "\n";
    $reflection = new \ReflectionClass($class);
    $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

    $instance = new $class();
    if (!method_exists($instance, 'assertTrue')) {
        // Inject assertion methods if not running under full PHPUnit binary
        class_alias(SimpleAssertionTrait::class, 'InjectedAssertions');
    }

    foreach ($methods as $method) {
        if (str_starts_with($method->getName(), 'test_')) {
            $totalTests++;
            $testName = $method->getName();
            try {
                // Call setUp if present
                $setupMethod = $reflection->getMethod('setUp');
                $setupMethod->setAccessible(true);
                $setupMethod->invoke($instance);

                $method->invoke($instance);
                $passedTests++;
                echo "  [PASS] {$testName}\n";
            } catch (\Throwable $e) {
                $failedTests++;
                $failures[] = "{$class}::{$testName} -> " . $e->getMessage();
                echo "  [FAIL] {$testName}: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "\n";
}

echo "=====================================================\n";
echo "Test Results: {$passedTests} passed, {$failedTests} failed (Total {$totalTests})\n";
echo "=====================================================\n";

if ($failedTests > 0) {
    echo "\nFailures Summary:\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}

echo "All test suites executed successfully!\n";
exit(0);
