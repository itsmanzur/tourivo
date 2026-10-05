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
        $this->container->singleton(\Tourivo\Services\WebhookService::class, fn () => new \Tourivo\Services\WebhookService());
    }

    public function boot(): void
    {
        // Initialize Review Service
        new ReviewService();

        // Initialize Webhook Dispatcher
        $webhookService = $this->container->get(\Tourivo\Services\WebhookService::class);
        $webhookService->register();

        // Register WP-CLI command if CLI environment
        if (defined('WP_CLI') && WP_CLI && class_exists('\WP_CLI')) {
            \WP_CLI::add_command('tourivo', \Tourivo\Cli\TourivoCli::class);
        }

        // Currency code filter
        add_filter('tourivo/currency_code', static function (string $default = 'USD'): string {
            return (string) Config::get('currency', $default);
        });

        // Register custom image sizes or core setups
        $this->addAction('after_setup_theme', [$this, 'setupThemeSupport']);
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
