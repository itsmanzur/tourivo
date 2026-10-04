<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\TourPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TemplateServiceProvider
 *
 * Handles template overrides for single Tour & Hotel posts, and enqueues frontend styles & scripts.
 *
 * @package Tourivo\Providers
 */
class TemplateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings
    }

    public function boot(): void
    {
        // Initialize SEO Service (Schema.org JSON-LD and OpenGraph)
        new \Tourivo\Services\SeoService();

        // Template loader hooks
        $this->addFilter('template_include', [$this, 'loadPostTemplates']);

        // Frontend assets
        $this->addAction('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // Live Filter AJAX
        $this->addAction('wp_ajax_tourivo_filter_items', [$this, 'handleFilterItems']);
        $this->addAction('wp_ajax_nopriv_tourivo_filter_items', [$this, 'handleFilterItems']);

        // Wishlist AJAX
        $this->addAction('wp_ajax_tourivo_get_wishlist_items', [$this, 'handleGetWishlistItems']);
        $this->addAction('wp_ajax_nopriv_tourivo_get_wishlist_items', [$this, 'handleGetWishlistItems']);
        $this->addAction('wp_ajax_tourivo_sync_wishlist', [$this, 'handleSyncWishlist']);
        $this->addAction('wp_ajax_nopriv_tourivo_sync_wishlist', [$this, 'handleSyncWishlist']);
    }

    /**
     * Handle AJAX live filtering of tours and hotels.
     *
     * @return void
     */
    public function handleFilterItems(): void
    {
        check_ajax_referer('tourivo_frontend_nonce', 'nonce');

        $params = [
            'type'        => isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : 'tour',
            's'           => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
            'destination' => isset($_GET['destination']) ? sanitize_text_field(wp_unslash($_GET['destination'])) : '',
            'activity'    => isset($_GET['activity']) ? sanitize_text_field(wp_unslash($_GET['activity'])) : '',
            'duration'    => isset($_GET['duration']) ? sanitize_text_field(wp_unslash($_GET['duration'])) : '',
            'star_rating' => isset($_GET['star_rating']) ? absint(wp_unslash($_GET['star_rating'])) : 0,
            'max_price'   => isset($_GET['max_price']) ? (float) sanitize_text_field(wp_unslash($_GET['max_price'])) : 0.0,
            'orderby'     => isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'newest',
            'count'       => isset($_GET['count']) ? max(1, absint(wp_unslash($_GET['count']))) : 9,
            'columns'     => isset($_GET['columns']) ? max(1, min(4, absint(wp_unslash($_GET['columns'])))) : 3,
        ];

        $html = \Tourivo\Shortcodes\FilterSearchShortcode::queryAndRender($params);

        wp_send_json_success([
            'html' => $html,
        ]);
    }

    /**
     * Handle AJAX fetching of saved wishlist cards.
     *
     * @return void
     */
    public function handleGetWishlistItems(): void
    {
        check_ajax_referer('tourivo_frontend_nonce', 'nonce');

        $rawIds = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', wp_unslash($_POST['ids'])) : [];
        $ids = array_values(array_filter($rawIds, fn($id) => $id > 0));

        if (empty($ids)) {
            wp_send_json_success([
                'html'  => '',
                'count' => 0,
            ]);
        }

        $html = \Tourivo\Shortcodes\WishlistShortcode::renderCardsHtml($ids);

        wp_send_json_success([
            'html'  => $html,
            'count' => count($ids),
        ]);
    }

    /**
     * Handle syncing wishlist to user account for logged-in users.
     *
     * @return void
     */
    public function handleSyncWishlist(): void
    {
        check_ajax_referer('tourivo_frontend_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_success(['synced' => false]);
        }

        $userId = get_current_user_id();
        $rawIds = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', wp_unslash($_POST['ids'])) : [];
        $ids = array_values(array_unique(array_filter($rawIds, fn($id) => $id > 0)));

        update_user_meta($userId, '_tourivo_wishlist', $ids);

        wp_send_json_success(['synced' => true, 'ids' => $ids]);
    }


    /**
     * Intercept template loader for custom post types.
     *
     * @param string $template
     * @return string
     */
    public function loadPostTemplates(string $template): string
    {
        if (is_singular(TourPostType::POST_TYPE)) {
            $themeTemplate = locate_template(['tourivo/single-tour.php']);
            if (!empty($themeTemplate)) {
                return $themeTemplate;
            }
            $pluginTemplate = untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/single-tour.php';
            if (file_exists($pluginTemplate)) {
                return $pluginTemplate;
            }
        }

        if (is_singular(HotelPostType::POST_TYPE)) {
            $themeTemplate = locate_template(['tourivo/single-hotel.php']);
            if (!empty($themeTemplate)) {
                return $themeTemplate;
            }
            $pluginTemplate = untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/single-hotel.php';
            if (file_exists($pluginTemplate)) {
                return $pluginTemplate;
            }
        }

        return $template;
    }

    /**
     * Enqueue frontend CSS and JavaScript with localized REST API config.
     *
     * @return void
     */
    public function enqueueFrontendAssets(): void
    {
        wp_enqueue_style(
            'dashicons'
        );

        wp_enqueue_style(
            'tourivo-frontend',
            TOURIVO_PLUGIN_URL . 'assets/css/frontend.css',
            ['dashicons'],
            TOURIVO_VERSION
        );

        $primaryColor = (string) \Tourivo\Config\Config::get('primary_color', '#0d9488');
        $primaryHover = (string) \Tourivo\Config\Config::get('primary_hover', '#0f766e');
        $accentColor  = (string) \Tourivo\Config\Config::get('accent_color', '#f59e0b');
        $borderRadius = (string) \Tourivo\Config\Config::get('border_radius', '8px');
        $btnTextColor = (string) \Tourivo\Config\Config::get('button_text_color', '#ffffff');

        $customCss = ":root {
            --trv-primary: {$primaryColor};
            --trv-primary-hover: {$primaryHover};
            --trv-accent: {$accentColor};
            --trv-radius: {$borderRadius};
            --trv-btn-color: {$btnTextColor};
        }";
        wp_add_inline_style('tourivo-frontend', $customCss);

        wp_enqueue_script(
            'tourivo-frontend',
            TOURIVO_PLUGIN_URL . 'assets/js/frontend-booking.js',
            [],
            TOURIVO_VERSION,
            true
        );

        wp_localize_script('tourivo-frontend', 'tourivoData', [
            'restUrl'        => esc_url_raw(rest_url()),
            'ajaxUrl'        => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'frontendNonce'  => wp_create_nonce('tourivo_frontend_nonce'),
            'currencySymbol' => (string) apply_filters('tourivo/currency_symbol', '$'),
            'currencies'     => \Tourivo\Shortcodes\CurrencySwitcherShortcode::getCurrencies(),
            'i18n'           => [
                'processing' => __('Processing...', 'tourivo'),
                'success'    => __('Booking successful!', 'tourivo'),
            ],
        ]);
    }
}

