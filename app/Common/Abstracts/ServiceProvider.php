<?php

declare(strict_types=1);

namespace Tourivo\Common\Abstracts;

use Tourivo\Common\Container;
use Tourivo\Common\Traits\Hookable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ServiceProvider
 *
 * Abstract base class for all Tourivo service providers.
 *
 * @package Tourivo\Common\Abstracts
 */
abstract class ServiceProvider
{
    use Hookable;

    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * ServiceProvider constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register any application services in the container.
     *
     * @return void
     */
    abstract public function register(): void;

    /**
     * Bootstrap any application services, hooks, or assets.
     *
     * @return void
     */
    abstract public function boot(): void;
}
