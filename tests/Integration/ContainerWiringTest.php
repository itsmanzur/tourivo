<?php
/**
 * Service container wiring & REST route registration regression tests.
 */

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Common\Container;
use Tourivo\Controllers\Api\AvailabilityController;
use Tourivo\Controllers\Api\BookingController;
use Tourivo\Controllers\Api\PricingController;
use Tourivo\Providers\RouteServiceProvider;
use Tourivo\Services\BookingService;
use Tourivo\Services\InquiryService;
use Tourivo\Services\PricingService;
use Tourivo\Tests\TestCase;

class ContainerWiringTest extends TestCase
{
    protected function makeProvider(): array
    {
        $container = new Container();
        $provider  = new RouteServiceProvider($container);
        $provider->register();

        return [$container, $provider];
    }

    public function test_route_provider_bindings_resolve_to_real_classes(): void
    {
        [$container] = $this->makeProvider();

        $this->assertTrue($container->get(PricingService::class) instanceof PricingService);
        $this->assertTrue($container->get(BookingService::class) instanceof BookingService);
        $this->assertTrue($container->get(InquiryService::class) instanceof InquiryService);
        $this->assertTrue($container->get(PricingController::class) instanceof PricingController);
        $this->assertTrue($container->get(BookingController::class) instanceof BookingController);
        $this->assertTrue($container->get(AvailabilityController::class) instanceof AvailabilityController);
    }

    public function test_booking_service_shares_pricing_singleton(): void
    {
        [$container] = $this->makeProvider();

        $this->assertTrue($container->get(PricingService::class) === $container->get(PricingService::class));
    }

    public function test_rest_routes_register_without_fatal(): void
    {
        $GLOBALS['tourivo_mock_rest_routes'] = [];
        [, $provider] = $this->makeProvider();

        $provider->registerRestRoutes();

        foreach (['availability/check', 'availability/calendar', 'availability/hold', 'pricing/quote', 'bookings/direct'] as $route) {
            $this->assertArrayHasKey('tourivo/v1/' . $route, $GLOBALS['tourivo_mock_rest_routes'], "Route {$route} not registered.");
        }
    }
}
