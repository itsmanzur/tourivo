<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Providers\TemplateServiceProvider;
use Tourivo\Tests\TestCase;

/**
 * Class TravelHomepageOptInTest
 *
 * The homepage, footer and menu changes must only be registered when the site owner opts in.
 */
class TravelHomepageOptInTest extends TestCase
{
    private const SITE_WIDE_CALLBACKS = [
        'guestNavigationFallback', 'guestFooterNavigation', 'watchFooterScope', 'replaceThemeFooterCredit',
        'fillFooterTagline', 'prependHomeSearch', 'hideHomeBlog', 'hideClassicHomePosts',
        'prependHomeSearchToContent', 'renderHomeSearch',
    ];

    /**
     * Boot a fresh provider and list the method names it registered on WordPress hooks.
     *
     * @return array<int, string>
     */
    private function registeredCallbacks(): array
    {
        $GLOBALS['tourivo_mock_filters'] = [];
        $GLOBALS['tourivo_mock_action_callbacks'] = [];

        $provider = new TemplateServiceProvider(Container::getInstance());
        $provider->boot();

        $names = [];
        foreach ((array) $GLOBALS['tourivo_mock_filters'] as $callbacks) {
            foreach ((array) $callbacks as $cb) {
                if (is_array($cb) && ($cb[0] ?? null) === $provider) {
                    $names[] = (string) $cb[1];
                }
            }
        }
        foreach ((array) $GLOBALS['tourivo_mock_action_callbacks'] as $entries) {
            foreach ((array) $entries as $entry) {
                $cb = $entry['callback'] ?? null;
                if (is_array($cb) && ($cb[0] ?? null) === $provider) {
                    $names[] = (string) $cb[1];
                }
            }
        }

        return $names;
    }

    public function test_site_wide_changes_are_off_by_default(): void
    {
        update_option('tourivo_settings', []);
        $names = $this->registeredCallbacks();

        foreach (self::SITE_WIDE_CALLBACKS as $callback) {
            $this->assertNotContains($callback, $names, "{$callback} must not run unless the owner opts in.");
        }
        $this->assertContains('prependSearchSummary', $names, 'Tourivo listing pages keep their search summary.');
        $this->assertContains('filterTourSearch', $names);
    }

    public function test_site_wide_changes_register_when_enabled(): void
    {
        update_option('tourivo_settings', ['travel_homepage' => 1]);
        $names = $this->registeredCallbacks();

        foreach (self::SITE_WIDE_CALLBACKS as $callback) {
            $this->assertContains($callback, $names, "{$callback} should run once the owner opts in.");
        }

        update_option('tourivo_settings', []);
    }
}
