<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Blocks\BlockManager;
use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Shortcodes\BookingPanelShortcode;
use Tourivo\Shortcodes\CurrencySwitcherShortcode;
use Tourivo\Shortcodes\FilterSearchShortcode;
use Tourivo\Shortcodes\HotelGridShortcode;
use Tourivo\Shortcodes\SearchBarShortcode;
use Tourivo\Shortcodes\ThankYouShortcode;
use Tourivo\Shortcodes\TourGridShortcode;
use Tourivo\Shortcodes\WishlistShortcode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BlockServiceProvider
 *
 * Registers shortcodes and Gutenberg blocks.
 *
 * @package Tourivo\Providers
 */
class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings
    }

    public function boot(): void
    {
        // 1. Register Shortcodes
        BookingPanelShortcode::register();
        BookingLookupShortcode::register();
        ThankYouShortcode::register();
        TourGridShortcode::register();
        HotelGridShortcode::register();
        SearchBarShortcode::register();
        FilterSearchShortcode::register();
        WishlistShortcode::register();
        CurrencySwitcherShortcode::register();

        // 2. Register Gutenberg Blocks on init
        $this->addAction('init', [BlockManager::class, 'registerBlocks'], 20);
    }
}

