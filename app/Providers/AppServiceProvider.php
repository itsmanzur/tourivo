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
        $this->container->singleton(\Tourivo\Services\EmailService::class, fn () => new \Tourivo\Services\EmailService());
    }

    public function boot(): void
    {
        // Initialize Review Service
        new ReviewService();

        // Initialize Webhook Dispatcher
        $webhookService = $this->container->get(\Tourivo\Services\WebhookService::class);
        $webhookService->register();

        // Initialize Email Dispatcher & Cron
        $emailService = $this->container->get(\Tourivo\Services\EmailService::class);
        $emailService->register();

        // Initialize Privacy Tools & GDPR Compliance
        \Tourivo\Support\Privacy::register();

        // Register daily retention cron hook
        add_action('tourivo_daily_privacy_retention', [\Tourivo\Services\PrivacyService::class, 'runDailyRetention']);
        if (!wp_next_scheduled('tourivo_daily_privacy_retention')) {
            wp_schedule_event(time(), 'daily', 'tourivo_daily_privacy_retention');
        }

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
