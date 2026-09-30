<?php

declare(strict_types=1);

namespace Tourivo\Core;

use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\Common\Container;
use Tourivo\Common\Traits\Hookable;
use Tourivo\Common\Traits\Singleton;
use Tourivo\Providers\AdminServiceProvider;
use Tourivo\Providers\AppServiceProvider;
use Tourivo\Providers\BlockServiceProvider;
use Tourivo\Providers\DatabaseServiceProvider;
use Tourivo\Providers\ElementorServiceProvider;
use Tourivo\Providers\MetaBoxServiceProvider;
use Tourivo\Providers\PostTypeServiceProvider;
use Tourivo\Providers\RouteServiceProvider;
use Tourivo\Providers\TemplateServiceProvider;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Plugin
 *
 * Central orchestrator and bootstrap class for Tourivo.
 *
 * @package Tourivo\Core
 */
final class Plugin
{
    use Singleton;
    use Hookable;

    /**
     * Dependency injection container.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Registered service providers.
     *
     * @var array<class-string<ServiceProvider>, ServiceProvider>
     */
    protected array $providers = [];

    /**
     * Core service provider classes.
     *
     * @var array<class-string<ServiceProvider>>
     */
    protected array $defaultProviders = [
        DatabaseServiceProvider::class,
        PostTypeServiceProvider::class,
        MetaBoxServiceProvider::class,
        RouteServiceProvider::class,
        TemplateServiceProvider::class,
        BlockServiceProvider::class,
        ElementorServiceProvider::class,
        AppServiceProvider::class,
        AdminServiceProvider::class,
    ];

    /**
     * Initialize container.
     */
    protected function __construct()
    {
        $this->container = new Container();
        Container::setInstance($this->container);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);
    }

    /**
     * Get the DI container.
     *
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Bootstrap the plugin.
     *
     * @return void
     */
    public function boot(): void
    {
        // 1. Fire early hook
        do_action('tourivo/before_boot', $this);

        // 2. Allow third-parties / Pro add-on to register additional providers
        $providers = apply_filters('tourivo/service_providers', $this->defaultProviders);

        // 3. Register all providers
        foreach ($providers as $providerClass) {
            $this->registerProvider($providerClass);
        }

        // 4. Boot all providers
        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        // 5. Fire main ready hook
        do_action('tourivo/loaded', $this);
    }

    /**
     * Register a service provider.
     *
     * @param class-string<ServiceProvider> $providerClass
     * @return ServiceProvider
     */
    public function registerProvider(string $providerClass): ServiceProvider
    {
        if (isset($this->providers[$providerClass])) {
            return $this->providers[$providerClass];
        }

        /** @var ServiceProvider $provider */
        $provider = new $providerClass($this->container);
        $provider->register();

        $this->providers[$providerClass] = $provider;

        return $provider;
    }

    /**
     * Retrieve a loaded service provider by class.
     *
     * @param class-string<ServiceProvider> $providerClass
     * @return ServiceProvider|null
     */
    public function getProvider(string $providerClass): ?ServiceProvider
    {
        return $this->providers[$providerClass] ?? null;
    }
}
