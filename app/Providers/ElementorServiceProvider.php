<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\Elementor\ElementorManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ElementorServiceProvider
 *
 * Hooks Tourivo into Elementor categories and widgets initialization.
 *
 * @package Tourivo\Providers
 */
class ElementorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings
    }

    public function boot(): void
    {
        // Elementor Category registration
        $this->addAction('elementor/elements/categories_registered', [ElementorManager::class, 'registerCategories']);

        // Elementor Widgets registration
        $this->addAction('elementor/widgets/register', [ElementorManager::class, 'registerWidgets']);
    }
}
