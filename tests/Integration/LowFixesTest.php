<?php
/**
 * Regression tests for the "Low" batch: voucher access keys, availability item-type guard, tax rounding,
 * free bookings, per-departure capacity, multisite activation, number-format settings and DOM-sink hygiene.
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Admin\SettingsPage;
use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Core\Installer;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PricingService;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Tests\TestCase;

class LowFixesTest extends TestCase
{
    protected InventoryService $inventory;
    protected BookingService $bookings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();
        $GLOBALS['tourivo_mock_options']          = ['admin_email' => 'admin@example.com'];
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $GLOBALS['tourivo_mock_action_callbacks'] = [];
        $GLOBALS['tourivo_mock_is_multisite']     = false;
        $GLOBALS['tourivo_mock_blog_log']         = [];
        $_POST = [];
        Config::flushDefaults();

        $this->inventory = new InventoryService(new InventoryRepository());
        $this->bookings  = new BookingService($this->inventory, new EmailService());
        Container::getInstance()->instance(BookingService::class, $this->bookings);
    }

    protected function tearDown(): void
    {
        $GLOBALS['tourivo_mock_is_multisite'] = false;
        $_POST = [];
        parent::tearDown();
    }

    protected function book(int $itemId, int $day, array $extra = [], bool $trusted = false): array
    {
        return $this->bookings->createBooking($this->createBookingPayload(array_merge([
            'item_id'  => $itemId,
            'check_in' => gmdate('Y-m-d', strtotime("+{$day} days")),
            'adults'   => 1,
        ], $extra)), $trusted);
    }

    // ---- voucher access key

    public function test_voucher_token_is_bound_to_a_revocable_per_booking_key(): void
    {
        $tour = $this->createTour(100.0, 10);
        $res  = $this->book($tour, 30, ['customer_email' => 'v@example.com']);

        global $wpdb;
        $row = (object) $wpdb->bookings[$res['booking_id']];
        $this->assertNotEmpty($row->access_key);
        $this->assertEquals(32, strlen((string) $row->access_key));

        $legacy = BookingLookupShortcode::generateVoucherToken($row->id, 'v@example.com');
        $keyed  = BookingLookupShortcode::generateVoucherToken($row->id, 'v@example.com', $row->access_key);
        $this->assertNotEquals($legacy, $keyed, 'A keyed token must not equal the guessable email-derived one.');
        $this->assertEquals($keyed, BookingLookupShortcode::generateVoucherToken($row->id, 'other@example.com', $row->access_key), 'Keyed token must not depend on the email.');

        // The link handed to the customer uses the keyed token.
        $data = BookingLookupShortcode::formatLookupResponseData($row, []);
        parse_str((string) parse_url($data['voucher_url'], PHP_URL_QUERY), $q);
        $this->assertEquals($keyed, $q['token']);

        // Revoking rotates the key: the old link stops matching, the new one is issued automatically.
        $this->assertTrue($this->bookings->rotateAccessKey($row->id));
        $rotated = (object) $wpdb->bookings[$row->id];
        $this->assertNotEquals($row->access_key, $rotated->access_key);
        $this->assertNotEquals($keyed, BookingLookupShortcode::generateVoucherToken($row->id, 'v@example.com', $rotated->access_key));

        $this->assertFalse($this->bookings->rotateAccessKey(999999), 'Unknown booking cannot be rotated.');
    }

    public function test_legacy_bookings_without_a_key_keep_working(): void
    {
        $this->assertEquals(
            BookingLookupShortcode::generateVoucherToken(5, 'old@example.com'),
            BookingLookupShortcode::generateVoucherToken(5, 'old@example.com', null)
        );
        $this->assertEquals(
            BookingLookupShortcode::generateVoucherToken(5, 'old@example.com'),
            BookingLookupShortcode::generateVoucherToken(5, 'old@example.com', '')
        );
    }

    // ---- availability item-type guard

    public function test_update_availability_rejects_mismatched_item_type(): void
    {
        $tour = $this->createTour(100.0, 10);
        $date = gmdate('Y-m-d', strtotime('+30 days'));

        $res = $this->inventory->updateAvailability($tour, 'room', $date, $date, ['status' => 'blocked']);
        $this->assertFalse($res['success']);

        $page = (int) wp_insert_post(['post_title' => 'Plain page', 'post_type' => 'page', 'post_status' => 'publish']);
        $this->assertFalse($this->inventory->updateAvailability($page, 'tour', $date, $date, ['capacity' => 5])['success']);

        $this->assertTrue($this->inventory->updateAvailability($tour, 'tour', $date, $date, ['capacity' => 5])['success']);
    }

    // ---- tax rounding

    public function test_exclusive_tax_total_equals_sum_of_displayed_lines(): void
    {
        update_option('tourivo_settings', ['tax_enabled' => 1, 'tax_rate' => 10.0, 'tax_mode' => 'exclusive', 'tax_applies_to' => 'all']);
        $tour = $this->createTour(10.0, 10, ['_tourivo_child_price_type' => 'percent', '_tourivo_child_price_value' => '33.33']);

        $pricing = new PricingService($this->inventory);
        $quote   = $pricing->quote([
            'item_id' => $tour, 'item_type' => 'tour', 'check_in' => gmdate('Y-m-d', strtotime('+30 days')),
            'adults' => 1, 'children' => 1,
        ]);

        $this->assertTrue($quote['success']);
        $this->assertEquals(13.33, $quote['subtotal']);
        $this->assertEquals(1.33, $quote['tax']);
        $this->assertEquals(round($quote['subtotal'] + $quote['tax'], 2), $quote['total'], 'subtotal + tax must equal total to the cent.');
    }

    // ---- free bookings

    public function test_free_booking_requires_explicit_opt_in_and_is_settled(): void
    {
        $free   = $this->createTour(0.0, 10, ['_tourivo_allow_free_booking' => '1']);
        $broken = $this->createTour(0.0, 10); // price meta missing/zero without opt-in = pricing failure

        $ok = $this->book($free, 30);
        $this->assertTrue($ok['success'], $ok['message'] ?? '');

        global $wpdb;
        $this->assertEquals('paid', $wpdb->bookings[$ok['booking_id']]['payment_status']);
        $this->assertEquals(0.0, (float) $wpdb->bookings[$ok['booking_id']]['total_amount']);

        $bad = $this->book($broken, 30, ['customer_email' => 'b@example.com']);
        $this->assertFalse($bad['success'], 'A zero total without opt-in must still be refused.');
    }

    public function test_free_bookings_are_exempt_from_unpaid_expiry(): void
    {
        update_option('tourivo_settings', ['pending_expiry_hours' => 1]);
        $free = $this->createTour(0.0, 10, ['_tourivo_allow_free_booking' => '1']);
        $res  = $this->book($free, 30);

        global $wpdb;
        $wpdb->bookings[$res['booking_id']]['created_at'] = gmdate('Y-m-d H:i:s', time() - 10 * 3600);

        $this->assertEquals(0, $this->bookings->expireStaleBookings()['pending']);
        $this->assertEquals('pending', $wpdb->bookings[$res['booking_id']]['booking_status']);
    }

    // ---- seats per departure

    public function test_daily_capacity_is_independent_from_group_size(): void
    {
        $tour = $this->createTour(100.0, 4, ['_tourivo_daily_capacity' => 10]);
        $this->assertEquals(10, $this->inventory->getDefaultCapacity($tour, 'tour'));

        $this->assertTrue($this->book($tour, 30, ['adults' => 4, 'customer_email' => 'a@example.com'])['success']);
        $this->assertTrue($this->book($tour, 30, ['adults' => 4, 'customer_email' => 'b@example.com'])['success']);
        $this->assertTrue($this->book($tour, 30, ['adults' => 2, 'customer_email' => 'c@example.com'])['success']);
        $this->assertFalse($this->book($tour, 30, ['adults' => 1, 'customer_email' => 'd@example.com'])['success'], 'Departure is full.');
        $this->assertFalse($this->book($tour, 31, ['adults' => 5, 'customer_email' => 'e@example.com'])['success'], 'Group-size limit still applies per booking.');
    }

    public function test_without_daily_capacity_group_size_remains_the_fallback(): void
    {
        $tour = $this->createTour(100.0, 3);
        $this->assertEquals(3, $this->inventory->getDefaultCapacity($tour, 'tour'));
    }

    // ---- multisite

    public function test_network_activation_provisions_every_site_and_restores_context(): void
    {
        $GLOBALS['tourivo_mock_is_multisite'] = true;
        $GLOBALS['tourivo_mock_sites']        = [2, 5, 9];

        Installer::activate(true);

        $this->assertEquals(
            ['switch:2', 'restore', 'switch:5', 'restore', 'switch:9', 'restore'],
            $GLOBALS['tourivo_mock_blog_log']
        );
    }

    public function test_single_site_activation_does_not_switch_blogs(): void
    {
        Installer::activate(false);
        $this->assertEquals([], $GLOBALS['tourivo_mock_blog_log']);
    }

    // ---- number format settings

    public function test_number_format_settings_are_clamped_and_whitelisted(): void
    {
        $_POST = ['number_of_decimals' => '9', 'decimal_separator' => ';', 'thousand_separator' => 'x', 'currency_position' => 'middle'];
        SettingsPage::saveSettings();
        $saved = get_option('tourivo_settings');

        $this->assertEquals(4, $saved['number_of_decimals']);
        $this->assertEquals('.', $saved['decimal_separator']);
        $this->assertEquals(',', $saved['thousand_separator']);
        $this->assertEquals('left', $saved['currency_position']);

        $_POST = ['number_of_decimals' => '0', 'decimal_separator' => ',', 'thousand_separator' => '.'];
        SettingsPage::saveSettings();
        $saved = get_option('tourivo_settings');
        $this->assertEquals(0, $saved['number_of_decimals']);
        $this->assertEquals(',', $saved['decimal_separator']);
        $this->assertEquals('.', $saved['thousand_separator']);
    }

    // ---- DOM sinks

    public function test_scripts_do_not_inject_server_messages_through_innerhtml(): void
    {
        $dir = dirname(__DIR__, 2) . '/assets/js/';
        $offenders = [];

        foreach (['admin.js', 'admin-metabox.js', 'frontend-booking.js'] as $file) {
            foreach (file($dir . $file) ?: [] as $n => $line) {
                if (preg_match('/innerHTML\s*=.*\$\{[^}]*(\.message|\.title|response\.data|data\.data)/', $line)) {
                    $offenders[] = $file . ':' . ($n + 1);
                }
            }
        }

        $this->assertEquals([], $offenders, 'Server-provided text must use textContent, found: ' . implode(', ', $offenders));
    }
}
