<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\RoomPostType;
use Tourivo\PostTypes\TourPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PostTypeServiceProvider
 *
 * Registers all Tourivo custom post types and taxonomies.
 *
 * @package Tourivo\Providers
 */
class PostTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings if needed
    }

    public function boot(): void
    {
        $this->addAction('init', [$this, 'registerPostTypesAndTaxonomies'], 5);
        $this->addAction('init', [$this, 'maybeFlushRewriteRules'], 99);
    }

    /**
     * Register post types and taxonomies.
     *
     * @return void
     */
    public function registerPostTypesAndTaxonomies(): void
    {
        TourPostType::register();
        HotelPostType::register();
        RoomPostType::register();
    }

    /**
     * Safely flush rewrite rules once after CPT registration if flagged during activation.
     *
     * @return void
     */
    public function maybeFlushRewriteRules(): void
    {
        if (get_option('tourivo_flush_rewrite')) {
            flush_rewrite_rules(false);
            delete_option('tourivo_flush_rewrite');
        }
    }
}
