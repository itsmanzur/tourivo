<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\Config\Config;
use Tourivo\Services\ReviewService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AppServiceProvider
 *
 * Registers core application services, i18n textdomain loading, and core hooks.
 *
 * @package Tourivo\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core application bindings
    }

    public function boot(): void
    {
        // Initialize Review Service
        new ReviewService();

        // Currency code filter
        add_filter('tourivo/currency_code', static function (string $default = 'USD'): string {
            return (string) Config::get('currency', $default);
        });

        // Load plugin textdomain
        $this->addAction('init', [$this, 'loadTextdomain']);

        // Register custom image sizes or core setups
        $this->addAction('after_setup_theme', [$this, 'setupThemeSupport']);
    }


    /**
     * Load plugin translations.
     *
     * @return void
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'tourivo',
            false,
            dirname(plugin_basename(TOURIVO_PLUGIN_FILE)) . '/languages/'
        );
    }

    /**
     * Add thumbnail sizes for tours and hotels.
     *
     * @return void
     */
    public function setupThemeSupport(): void
    {
        add_image_size('tourivo-card', 600, 400, true);
        add_image_size('tourivo-gallery', 1200, 800, true);
        add_image_size('tourivo-thumbnail', 150, 150, true);
    }
}
