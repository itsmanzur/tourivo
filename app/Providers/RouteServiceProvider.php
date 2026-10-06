<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\Controllers\Api\AvailabilityController;
use Tourivo\Controllers\Api\BookingController;
use Tourivo\Controllers\Api\PricingController;
use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InquiryService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PricingService;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RouteServiceProvider
 *
 * Registers REST API endpoints and background cron handlers.
 *
 * @package Tourivo\Providers
 */
class RouteServiceProvider extends ServiceProvider
{
    public const REST_NAMESPACE = 'tourivo/v1';

    public function register(): void
    {
        $this->container->singleton(InventoryRepository::class, fn () => new InventoryRepository());
        $this->container->singleton(
            InventoryService::class,
            fn ($c) => new InventoryService($c->get(InventoryRepository::class))
        );
        $this->container->singleton(EmailService::class, fn () => new EmailService());
        $this->container->singleton(
            PricingService::class,
            fn ($c) => new PricingService($c->get(InventoryService::class))
        );
        $this->container->singleton(
            InquiryService::class,
            fn ($c) => new InquiryService($c->get(EmailService::class))
        );
        $this->container->singleton(
            BookingService::class,
            fn ($c) => new BookingService(
                $c->get(InventoryService::class),
                $c->get(EmailService::class),
                $c->get(PricingService::class)
            )
        );
        $this->container->singleton(
            PricingController::class,
            fn ($c) => new PricingController($c, $c->get(PricingService::class))
        );
        $this->container->singleton(
            AvailabilityController::class,
            fn ($c) => new AvailabilityController($c, $c->get(InventoryService::class))
        );
        $this->container->singleton(
            BookingController::class,
            fn ($c) => new BookingController($c, $c->get(BookingService::class))
        );
    }

    public function boot(): void
    {
        $this->addAction('rest_api_init', [$this, 'registerRestRoutes']);
        $this->addAction('tourivo_cleanup_expired_holds', [$this, 'cleanupExpiredHolds']);
        $this->addAction('tourivo_expire_stale_bookings', [$this, 'expireStaleBookings']);

        $this->addFilter('cron_schedules', [$this, 'registerCronSchedules']);

        // Schedule cron if not already scheduled (re-schedule legacy hourly installs to the 5-minute cadence)
        if (function_exists('wp_get_schedule') && wp_get_schedule('tourivo_cleanup_expired_holds') === 'hourly') {
            wp_clear_scheduled_hook('tourivo_cleanup_expired_holds');
        }
        if (!wp_next_scheduled('tourivo_cleanup_expired_holds')) {
            wp_schedule_event(time(), 'tourivo_five_minutes', 'tourivo_cleanup_expired_holds');
        }
        if (!wp_next_scheduled('tourivo_expire_stale_bookings')) {
            wp_schedule_event(time(), 'tourivo_five_minutes', 'tourivo_expire_stale_bookings');
        }
    }

    /**
     * Register the short cron interval used for releasing expired checkout holds.
     *
     * @param array<string, array<string, mixed>> $schedules
     * @return array<string, array<string, mixed>>
     */
    public function registerCronSchedules(array $schedules): array
    {
        $schedules['tourivo_five_minutes'] = [
            'interval' => 300,
            'display'  => __('Every 5 minutes (Tourivo)', 'tourivo'),
        ];

        return $schedules;
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function registerRestRoutes(): void
    {
        /** @var AvailabilityController $controller */
        $controller = $this->container->get(AvailabilityController::class);

        // GET /wp-json/tourivo/v1/availability/check
        register_rest_route(self::REST_NAMESPACE, '/availability/check', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$controller, 'check'],
            'permission_callback' => '__return_true',
        ]);

        // GET /wp-json/tourivo/v1/availability/calendar
        register_rest_route(self::REST_NAMESPACE, '/availability/calendar', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$controller, 'calendar'],
            'permission_callback' => '__return_true',
        ]);

        // POST /wp-json/tourivo/v1/availability/hold
        register_rest_route(self::REST_NAMESPACE, '/availability/hold', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$controller, 'hold'],
            'permission_callback' => '__return_true',
        ]);

        /** @var PricingController $pricingController */
        $pricingController = $this->container->get(PricingController::class);

        // GET /wp-json/tourivo/v1/pricing/quote
        register_rest_route(self::REST_NAMESPACE, '/pricing/quote', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$pricingController, 'quote'],
            'permission_callback' => '__return_true',
        ]);

        /** @var BookingController $bookingController */
        $bookingController = $this->container->get(BookingController::class);

        // POST /wp-json/tourivo/v1/bookings/direct
        register_rest_route(self::REST_NAMESPACE, '/bookings/direct', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$bookingController, 'directCheckout'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Cancel unverified / expired pending bookings and release their inventory.
     *
     * @return void
     */
    public function expireStaleBookings(): void
    {
        /** @var BookingService $bookings */
        $bookings = $this->container->get(BookingService::class);
        $bookings->expireStaleBookings();
    }

    /**
     * Cleanup expired checkout holds.
     *
     * @return void
     */
    public function cleanupExpiredHolds(): void
    {
        /** @var InventoryService $inventory */
        $inventory = $this->container->get(InventoryService::class);
        $inventory->releaseExpiredHolds();
    }
}
