<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InquiryService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PricingService;
use Tourivo\Support\BookingItems;
use Tourivo\Support\LegacyHooks;
use Tourivo\Tests\TestCase;

/**
 * Class HookCompatTest
 *
 * 1. Listeners on the pre-1.5.0 hook names (tourivo/booking_*, tourivo/inquiry_created) still run, once per
 *    event, through a bridge that is only attached when such a listener exists.
 * 2. The 'tourivo/send_email' filter is asked once per email (in enqueue), never per attempt, and not for
 *    test emails.
 */
class HookCompatTest extends TestCase
{
    protected EmailService $emailService;
    protected BookingService $bookingService;
    protected InquiryService $inquiryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabaseTables();

        $GLOBALS['tourivo_mock_sent_emails']      = [];
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        $GLOBALS['tourivo_mock_filters']          = [];
        $GLOBALS['tourivo_mock_action_callbacks'] = [];
        $GLOBALS['tourivo_mock_actions']          = [];
        $GLOBALS['tourivo_mock_deprecated_hooks'] = [];
        update_option('tourivo_settings', []);
        LegacyHooks::reset();

        $inventory            = new InventoryService(new InventoryRepository());
        $pricing              = new PricingService($inventory);
        $this->emailService   = new EmailService();
        $this->bookingService = new BookingService($inventory, $this->emailService, $pricing);
        $this->inquiryService = new InquiryService($this->emailService);

        $container = Container::getInstance();
        $container->instance(InventoryService::class, $inventory);
        $container->instance(EmailService::class, $this->emailService);
        $container->instance(BookingService::class, $this->bookingService);
        $container->instance(PricingService::class, $pricing);

        $this->emailService->register();
    }

    protected function tearDown(): void
    {
        LegacyHooks::reset();
        parent::tearDown();
    }

    private function book(): array
    {
        return $this->bookingService->createBooking([
            'item_id'        => $this->createTour(100.0, 10),
            'check_in'       => gmdate('Y-m-d', strtotime('+10 days')),
            'adults'         => 2,
            'customer_name'  => 'Ayesha Rahman',
            'customer_email' => 'ayesha@example.com',
            'customer_phone' => '+8801711000111',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emailJobs(): array
    {
        return array_values(array_filter(
            $GLOBALS['tourivo_mock_scheduled_events'] ?? [],
            static fn ($e) => $e['hook'] === EmailService::QUEUE_HOOK
        ));
    }

    private function callbackCount(string $hook): int
    {
        return count($GLOBALS['tourivo_mock_filters'][$hook] ?? []) + count($GLOBALS['tourivo_mock_action_callbacks'][$hook] ?? []);
    }

    // ------------------------------------------------------------------ Legacy hook names

    public function test_old_action_listener_runs_once_when_the_new_action_fires(): void
    {
        $calls = [];
        add_action('tourivo/booking_created', function ($bookingId) use (&$calls): void {
            $calls[] = $bookingId;
        }, 10, 2);

        // Bridging twice (boot + wp_loaded) must not attach a second bridge.
        LegacyHooks::bridge();
        LegacyHooks::bridge();

        $res = $this->book();
        $this->assertTrue($res['success'], 'Booking must succeed.');

        $this->assertEquals([$res['booking_id']], $calls, 'The old-name listener runs exactly once with the booking ID.');
        $this->assertEquals(
            [['hook' => 'tourivo/booking_created', 'version' => '1.5.0', 'replacement' => 'tourivo/booking/created']],
            $GLOBALS['tourivo_mock_deprecated_hooks'],
            'A deprecation notice names the replacement.'
        );
    }

    public function test_old_filter_listener_still_changes_the_new_filter(): void
    {
        $seen = [];
        add_filter('tourivo/booking_title', function ($title, $bookingId = 0) use (&$seen) {
            $seen[] = $bookingId;
            return $title . ' (package)';
        }, 10, 2);
        LegacyHooks::bridge();

        $res   = $this->book();
        $title = BookingItems::title(BookingItems::forBooking((int) $res['booking_id']), (int) $res['booking_id']);

        $this->assertStringContainsString(' (package)', $title, 'The old filter still rewrites the title.');
        $this->assertEquals([(int) $res['booking_id']], array_values(array_unique($seen)), 'Extra filter arguments reach the old listener.');
    }

    public function test_nothing_is_bridged_and_nothing_is_reported_without_old_listeners(): void
    {
        $before = [];
        foreach (array_merge(LegacyHooks::ACTIONS, LegacyHooks::FILTERS) as $new) {
            $before[$new] = $this->callbackCount($new);
        }

        LegacyHooks::bridge();
        $this->book();

        foreach ($before as $new => $count) {
            $this->assertEquals($count, $this->callbackCount($new), "No bridge may be attached to {$new}.");
        }
        $this->assertEmpty($GLOBALS['tourivo_mock_deprecated_hooks'], 'Silent when nobody uses an old name.');
    }

    public function test_listener_added_after_boot_is_bridged_on_wp_loaded(): void
    {
        LegacyHooks::register(); // At boot: no old listener yet.

        $calls = 0;
        add_action('tourivo/booking_created', function () use (&$calls): void {
            $calls++;
        });
        do_action('wp_loaded');
        do_action('wp_loaded');

        $this->book();
        $this->assertEquals(1, $calls, 'A listener a theme adds after plugins_loaded still runs, once.');
    }

    public function test_inquiry_hook_is_renamed_and_the_old_name_still_works(): void
    {
        $calls = [];
        add_action('tourivo/inquiry_created', function ($inquiryId) use (&$calls): void {
            $calls[] = $inquiryId;
        }, 10, 2);
        LegacyHooks::bridge();

        $res = $this->inquiryService->createInquiry([
            'item_id'        => $this->createTour(100.0, 10),
            'customer_name'  => 'Karim Uddin',
            'customer_email' => 'karim@example.com',
            'message'        => 'Is the boat ride included?',
            'consent'        => 1,
        ]);
        $this->assertTrue($res['success'], 'Inquiry must succeed.');

        $fired = array_column($GLOBALS['tourivo_mock_actions'], 'hook');
        $this->assertTrue(in_array('tourivo/inquiry/created', $fired, true), 'Tourivo fires tourivo/inquiry/created.');
        $this->assertFalse(in_array('tourivo/inquiry_created', array_slice($fired, 0, (int) array_search('tourivo/inquiry/created', $fired, true)), true), 'The old name is not fired directly.');
        $this->assertEquals([$res['inquiry_id']], $calls, 'The old-name listener runs exactly once.');
    }

    public function test_every_renamed_hook_is_fired_under_its_new_name_and_never_the_old_one(): void
    {
        $root  = dirname(__DIR__, 2);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app'));
        $code  = '';
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'LegacyHooks.php') {
                $code .= file_get_contents($file->getPathname());
            }
        }
        foreach (glob($root . '/templates/*.php') ?: [] as $file) {
            $code .= file_get_contents($file);
        }

        foreach (LegacyHooks::ACTIONS as $old => $new) {
            $this->assertTrue((bool) preg_match('/do_action(_ref_array)?\(\s*\'' . preg_quote($new, '/') . '\'/', $code), "{$new} must be fired as an action.");
            $this->assertStringNotContainsString("'{$old}'", $code, "{$old} must not be used any more.");
        }
        foreach (LegacyHooks::FILTERS as $old => $new) {
            $this->assertTrue((bool) preg_match('/apply_filters(_ref_array)?\(\s*\'' . preg_quote($new, '/') . '\'/', $code), "{$new} must be applied as a filter.");
            $this->assertStringNotContainsString("'{$old}'", $code, "{$old} must not be used any more.");
        }
    }

    // ------------------------------------------------------------------ tourivo/send_email

    public function test_send_email_filter_is_asked_once_per_email_not_per_attempt(): void
    {
        $asked = [];
        add_filter('tourivo/send_email', function ($send, $emailType) use (&$asked) {
            $asked[] = $emailType;
            return $send;
        }, 10, 3);

        $this->book();
        $jobs = $this->emailJobs();
        $this->assertNotEmpty($jobs, 'The booking queues emails.');
        $this->assertCount(count($jobs), $asked, 'One question per queued email.');

        // The worker, and a retry of the same job, do not ask again.
        $GLOBALS['tourivo_mock_scheduled_events'] = [];
        foreach ($jobs as $job) {
            $this->emailService->dispatch(...$job['args']);
            $args    = $job['args'];
            $args[3] = 2;
            $this->emailService->dispatch(...$args);
        }
        $this->assertCount(count($jobs), $asked, 'dispatch() must not apply the filter again.');
        $this->assertNotEmpty($GLOBALS['tourivo_mock_sent_emails'], 'Approved emails are delivered.');
    }

    public function test_suppressed_email_is_not_queued_at_all(): void
    {
        add_filter('tourivo/send_email', static fn () => false, 10, 3);

        $this->book();
        $this->assertEmpty($this->emailJobs(), 'A suppressed email never reaches the queue.');
    }

    public function test_test_email_ignores_the_send_email_filter(): void
    {
        $asked = 0;
        add_filter('tourivo/send_email', function () use (&$asked) {
            $asked++;
            return false;
        }, 10, 3);

        $this->assertTrue($this->emailService->sendTestEmail('admin@example.com'), 'The test email is sent.');
        $this->assertEquals(0, $asked, 'The filter is not consulted for test emails.');
        $this->assertCount(1, $GLOBALS['tourivo_mock_sent_emails']);
    }
}
