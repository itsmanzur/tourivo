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
    /**
     * How many footer template parts or patterns are currently rendering.
     */
    private int $footerScope = 0;

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
        $this->addFilter('get_the_archive_title', [$this, 'cleanArchiveTitle'], 10, 3);
        $this->addFilter('document_title_parts', [$this, 'cleanArchiveDocumentTitle']);
        $this->addFilter('block_core_navigation_render_fallback', [$this, 'guestNavigationFallback']);
        $this->addFilter('block_core_navigation_render_inner_blocks', [$this, 'guestFooterNavigation']);
        $this->addFilter('pre_render_block', [$this, 'watchFooterScope'], 20, 2);
        $this->addFilter('render_block', [$this, 'replaceThemeFooterCredit'], 12, 2);
        $this->addFilter('render_block', [$this, 'fillFooterTagline'], 13, 2);

        // Frontend assets
        $this->addAction('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        $this->addAction('pre_get_posts', [$this, 'filterTourSearch']);
        $this->addFilter('render_block', [$this, 'prependSearchSummary'], 10, 2);
        $this->addFilter('render_block', [$this, 'appendDestinationChips'], 10, 2);
        $this->addFilter('tourivo/place_search', [$this, 'listingSearchMarkup']);
        $this->addFilter('tourivo/related_place', [$this, 'relatedPlaceMarkup']);
        $this->addFilter('render_block', [$this, 'prependHomeSearch'], 9, 2);
        $this->addFilter('render_block', [$this, 'hideHomeBlog'], 11, 2);
        $this->addAction('pre_get_posts', [$this, 'hideClassicHomePosts']);
        $this->addFilter('the_content', [$this, 'prependHomeSearchToContent']);
        $this->addAction('loop_start', [$this, 'renderTourSearchSummary']);
        $this->addAction('loop_start', [$this, 'renderHomeSearch']);

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
     * Replace "Archives: Tours & Packages" with a short public heading.
     *
     * @param string $title
     * @param string $originalTitle
     * @param string $prefix
     */
    public function cleanArchiveTitle(string $title, string $originalTitle = '', string $prefix = ''): string
    {
        $name = $this->archivePublicName();

        return $name === null ? $title : esc_html($name);
    }

    /**
     * Keep the browser tab title in step with the archive heading.
     *
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function cleanArchiveDocumentTitle(array $parts): array
    {
        $name = $this->archivePublicName();
        if ($name !== null) {
            $parts['title'] = $name;
        }

        return $parts;
    }

    /**
     * Short public heading for tour, hotel, and destination archives.
     */
    private function archivePublicName(): ?string
    {
        if (is_admin()) {
            return null;
        }
        if (is_tax('tourivo_destination')) {
            $name = single_term_title('', false);

            return is_string($name) && $name !== '' ? $name : null;
        }
        if (!is_post_type_archive(['tourivo_tour', 'tourivo_hotel'])) {
            return null;
        }
        if (function_exists('tourivo_load_plugin_textdomain')) {
            tourivo_load_plugin_textdomain();
        }
        if (is_post_type_archive('tourivo_tour')) {
            return _x('Tours', 'public archive title', 'tourivo');
        }

        return _x('Hotels', 'public archive title', 'tourivo');
    }

    /**
     * Replace the automatic list of every page with the guest links.
     * A menu the owner has already built is left as they saved it.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    public function guestNavigationFallback(array $blocks): array
    {
        if (!$this->isAutomaticPageList($blocks)) {
            return $blocks;
        }

        return $this->guestNavigationLinks();
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    private function isAutomaticPageList(array $blocks): bool
    {
        $named = array_values(array_filter($blocks, static function ($block): bool {
            return is_array($block) && ($block['blockName'] ?? '') !== '';
        }));
        if ($named === []) {
            return false;
        }
        foreach ($named as $block) {
            if (($block['blockName'] ?? '') !== 'core/page-list') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function guestNavigationLinks(): array
    {
        if (function_exists('tourivo_load_plugin_textdomain')) {
            tourivo_load_plugin_textdomain();
        }

        $tours = get_post_type_archive_link('tourivo_tour');
        $hotels = get_post_type_archive_link('tourivo_hotel');
        $track = get_page_by_path('track-booking');
        $trackUrl = $track instanceof \WP_Post ? get_permalink($track) : home_url('/track-booking/');
        $items = [
            [_x('Tours', 'public archive title', 'tourivo'), is_string($tours) && $tours !== '' ? $tours : home_url('/tours/')],
            [_x('Hotels', 'public archive title', 'tourivo'), is_string($hotels) && $hotels !== '' ? $hotels : home_url('/hotels/')],
            [__('Track My Booking', 'tourivo'), is_string($trackUrl) && $trackUrl !== '' ? $trackUrl : home_url('/track-booking/')],
        ];

        $blocks = [];
        foreach ($items as [$label, $url]) {
            $blocks[] = [
                'blockName'    => 'core/navigation-link',
                'attrs'        => [
                    'label'          => $label,
                    'url'            => $url,
                    'kind'           => 'custom',
                    'isTopLevelLink' => true,
                ],
                'innerBlocks'  => [],
                'innerHTML'    => '',
                'innerContent' => [],
            ];
        }

        return $blocks;
    }

    /**
     * Swap the theme's placeholder footer links for guest links.
     * A footer menu with real addresses is left as saved.
     */
    public function guestFooterNavigation(\WP_Block_List $innerBlocks): \WP_Block_List
    {
        if (is_admin()) {
            return $innerBlocks;
        }

        $labels = [];
        foreach ($innerBlocks as $block) {
            if (!$block instanceof \WP_Block || $block->name !== 'core/navigation-link') {
                return $innerBlocks;
            }
            $url = (string) ($block->attributes['url'] ?? '');
            if ($url !== '' && $url !== '#') {
                return $innerBlocks;
            }
            $labels[] = (string) ($block->attributes['label'] ?? '');
        }
        if ($labels === [] || !$this->isThemePlaceholderMenu($labels)) {
            return $innerBlocks;
        }

        $story = $this->themePlaceholderLabels(['Blog', 'About', 'FAQs', 'Authors']);
        $featured = $this->themePlaceholderLabels(['Events', 'Shop', 'Patterns', 'Themes']);
        $column = 'all';
        if (array_intersect($labels, $story) !== [] && array_intersect($labels, $featured) === []) {
            $column = 'places';
        } elseif (array_intersect($labels, $featured) !== [] && array_intersect($labels, $story) === []) {
            $column = 'plan';
        }

        return new \WP_Block_List($this->footerNavigationLinks($column));
    }

    /**
     * @param array<int, string> $labels
     */
    private function isThemePlaceholderMenu(array $labels): bool
    {
        $allowed = $this->themePlaceholderLabels(['Blog', 'About', 'FAQs', 'Authors', 'Events', 'Shop', 'Patterns', 'Themes']);
        foreach ($labels as $label) {
            if (!in_array($label, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $sources
     * @return array<int, string>
     */
    private function themePlaceholderLabels(array $sources): array
    {
        $labels = [];
        foreach ($sources as $source) {
            $labels[] = $source;
            $labels[] = __($source, 'twentytwentyfive');
        }

        return array_values(array_unique($labels));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function footerNavigationLinks(string $column): array
    {
        $links = $this->guestNavigationLinks();
        $search = [
            'blockName'    => 'core/navigation-link',
            'attrs'        => [
                'label'          => __('Search trips', 'tourivo'),
                'url'            => home_url('/'),
                'kind'           => 'custom',
                'isTopLevelLink' => true,
            ],
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ];

        if ($column === 'places') {
            return array_slice($links, 0, 2);
        }
        if ($column === 'plan') {
            return [$links[2] ?? $search, $search];
        }

        return array_merge($links, [$search]);
    }

    /**
     * Remember when a footer template part or footer pattern starts rendering.
     *
     * @param mixed $preRender
     * @param mixed $block
     * @return mixed
     */
    public function watchFooterScope($preRender, $block)
    {
        if (is_admin() || $preRender !== null || !is_array($block)) {
            return $preRender;
        }
        if ($this->isFooterScopeBlock($block)) {
            $this->footerScope++;
        }

        return $preRender;
    }

    /**
     * Show a short intro under the site name when the footer tagline is empty.
     *
     * @param array<string, mixed> $block
     */
    public function fillFooterTagline(string $content, array $block): string
    {
        $blockName = (string) ($block['blockName'] ?? '');
        if (!is_admin() && $blockName === 'core/site-tagline' && $this->footerScope > 0 && trim(wp_strip_all_tags($content)) === '') {
            $content = $this->footerIntroMarkup();
        }
        if ($this->footerScope > 0 && $this->isFooterScopeBlock($block)) {
            $this->footerScope--;
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function isFooterScopeBlock(array $block): bool
    {
        $name = (string) ($block['blockName'] ?? '');
        $slug = (string) ($block['attrs']['slug'] ?? '');
        if ($name === 'core/template-part' && $slug === 'footer') {
            return true;
        }

        return $name === 'core/pattern' && str_contains($slug, 'footer');
    }

    private function footerIntroMarkup(): string
    {
        if (function_exists('tourivo_load_plugin_textdomain')) {
            tourivo_load_plugin_textdomain();
        }

        return '<p class="wp-block-site-tagline has-small-font-size">'
            . esc_html(__('Book tours and hotels', 'tourivo'))
            . '</p>';
    }

    /**
     * Replace the theme demo credit in the footer with the site name.
     *
     * @param array<string, mixed> $block
     */
    public function replaceThemeFooterCredit(string $content, array $block): string
    {
        if (is_admin()) {
            return $content;
        }

        $blockName = (string) ($block['blockName'] ?? '');
        $inFooterPart = $blockName === 'core/template-part'
            && (string) ($block['attrs']['slug'] ?? '') === 'footer';
        if ($blockName !== 'core/paragraph' && !$inFooterPart) {
            return $content;
        }

        return $this->swapFooterCreditHtml($content);
    }

    /**
     * Swap theme credit paragraphs inside already rendered footer HTML.
     */
    private function swapFooterCreditHtml(string $content): string
    {
        $name = get_bloginfo('name');
        if (!is_string($name) || trim($name) === '') {
            return $content;
        }

        $year = function_exists('wp_date') ? wp_date('Y') : gmdate('Y');
        $banglaDigits = (function_exists('tourivo_is_bengali') && tourivo_is_bengali())
            || (bool) \Tourivo\Config\Config::get('use_bangla_digits', false);
        if ($banglaDigits && function_exists('tourivo_bn_number')) {
            $year = tourivo_bn_number($year);
        }

        $credit = '<p class="has-small-font-size">© ' . esc_html($year)
            . ' <a href="' . esc_url(home_url('/')) . '">' . esc_html($name) . '</a></p>';
        $updated = $content;
        foreach ($this->themeCreditNames() as $themeName) {
            $pattern = '/<p\b[^>]*>\s*' . preg_quote($themeName, '/') . '\s*<\/p>/u';
            $next = preg_replace($pattern, $credit, $updated, 1, $count);
            if (is_string($next) && $count > 0) {
                $updated = $next;
                break;
            }
        }

        $withoutWordPress = preg_replace_callback(
            '/<p\b[^>]*>.*?<\/p>/su',
            function (array $match): string {
                $text = trim(wp_strip_all_tags($match[0]));
                $text = preg_replace('/\s+/u', ' ', $text);
                if (!is_string($text) || !in_array($text, $this->themeWordPressCredits(), true)) {
                    return $match[0];
                }
                if (!str_contains($match[0], 'wordpress.org')) {
                    return $match[0];
                }

                return '';
            },
            $updated
        );

        return is_string($withoutWordPress) ? $withoutWordPress : $updated;
    }

    /**
     * @return array<int, string>
     */
    private function themeCreditNames(): array
    {
        return array_values(array_unique([
            'Twenty Twenty-Five',
            __('Twenty Twenty-Five', 'twentytwentyfive'),
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function themeWordPressCredits(): array
    {
        return array_values(array_unique([
            'Designed with WordPress',
            sprintf(__('Designed with %s', 'twentytwentyfive'), 'WordPress'),
        ]));
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
        if (function_exists('tourivo_load_plugin_textdomain')) {
            tourivo_load_plugin_textdomain();
        }

        wp_enqueue_style(
            'dashicons'
        );

        $frontCss = TOURIVO_PLUGIN_DIR . 'assets/css/frontend.css';
        $frontJs  = TOURIVO_PLUGIN_DIR . 'assets/js/frontend-booking.js';
        $cssVer   = file_exists($frontCss) ? (string) filemtime($frontCss) : TOURIVO_VERSION;
        $jsVer    = file_exists($frontJs) ? (string) filemtime($frontJs) : TOURIVO_VERSION;

        wp_enqueue_style(
            'tourivo-frontend',
            TOURIVO_PLUGIN_URL . 'assets/css/frontend.css',
            ['dashicons'],
            $cssVer
        );

        if (function_exists('tourivo_is_bengali') && tourivo_is_bengali()) {
            wp_enqueue_style(
                'tourivo-bengali-font',
                TOURIVO_PLUGIN_URL . 'assets/css/tourivo-bengali-font.css',
                [],
                TOURIVO_VERSION
            );
        }

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
            $jsVer,
            true
        );

        if (function_exists('tourivo_load_plugin_textdomain')) {
            tourivo_load_plugin_textdomain();
        }

        wp_localize_script('tourivo-frontend', 'tourivoData', [
            'restUrl'         => esc_url_raw(rest_url()),
            'ajaxUrl'         => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'           => wp_create_nonce('wp_rest'),
            'frontendNonce'   => wp_create_nonce('tourivo_frontend_nonce'),
            'currencySymbol'  => (string) apply_filters('tourivo/currency_symbol', (string) \Tourivo\Config\Config::get('currency_symbol', '$')),
            'money'           => [
                'symbol'   => (string) apply_filters('tourivo/currency_symbol', (string) \Tourivo\Config\Config::get('currency_symbol', '$')),
                'position' => (string) apply_filters('tourivo/currency_position', (string) \Tourivo\Config\Config::get('currency_position', 'left')),
                'decimals' => (int) apply_filters('tourivo/number_of_decimals', (int) \Tourivo\Config\Config::get('number_of_decimals', 2)),
                'decimal'  => (string) apply_filters('tourivo/decimal_separator', (string) \Tourivo\Config\Config::get('decimal_separator', '.')),
                'thousand' => (string) apply_filters('tourivo/thousand_separator', (string) \Tourivo\Config\Config::get('thousand_separator', ',')),
                'bangla'   => (bool) apply_filters('tourivo/use_bangla_digits', (bool) \Tourivo\Config\Config::get('use_bangla_digits', false)),
            ],
            'currencies'      => \Tourivo\Shortcodes\CurrencySwitcherShortcode::getCurrencies(),
            'isBengali'       => function_exists('tourivo_is_bengali') && tourivo_is_bengali(),
            'useBanglaDigits' => (bool) \Tourivo\Config\Config::get('use_bangla_digits', false),
            'i18n'            => [
                'processing'      => __('Processing...', 'tourivo'),
                'success'         => __('Booking successful!', 'tourivo'),
                'savedToWishlist' => __('Saved to wishlist', 'tourivo'),
                'saveToWishlist'  => __('Save to wishlist', 'tourivo'),
                'submitting'      => __('Submitting...', 'tourivo'),
                'night'           => __('night', 'tourivo'),
                'person'          => __('person', 'tourivo'),
                'calculating'     => __('Calculating the price...', 'tourivo'),
            ],
            'calendar'        => [
                'weekdays'     => [
                    __('Sun', 'tourivo'),
                    __('Mon', 'tourivo'),
                    __('Tue', 'tourivo'),
                    __('Wed', 'tourivo'),
                    __('Thu', 'tourivo'),
                    __('Fri', 'tourivo'),
                    __('Sat', 'tourivo'),
                ],
                'months'       => [
                    __('January', 'tourivo'),
                    __('February', 'tourivo'),
                    __('March', 'tourivo'),
                    __('April', 'tourivo'),
                    __('May', 'tourivo'),
                    __('June', 'tourivo'),
                    __('July', 'tourivo'),
                    __('August', 'tourivo'),
                    __('September', 'tourivo'),
                    __('October', 'tourivo'),
                    __('November', 'tourivo'),
                    __('December', 'tourivo'),
                ],
                'soldOut'      => __('Sold out', 'tourivo'),
                'left'         => __('%d left', 'tourivo'),
                'anyDay'       => __('Any day', 'tourivo'),
                'toursOpen'    => __('%d tours open', 'tourivo'),
                'hotelsOpen'   => __('%d hotels open', 'tourivo'),
                'datesHint'    => __('Each date shows open tours, then open hotels.', 'tourivo'),
                'loading'      => __('Loading dates...', 'tourivo'),
                'pickCheckout' => __('Now choose a check-out date.', 'tourivo'),
                'closedRange'  => __('Those nights are not fully open. Choose another check-out.', 'tourivo'),
                'range'        => __('Check-in: %1$s | Check-out: %2$s', 'tourivo'),
            ],
        ]);
    }

    /**
     * Limit the tour archive to the destination and the day that still has seats.
     *
     * @param \WP_Query $query
     */
    public function filterTourSearch(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        $postType = $query->get('post_type');
        $isTourArchive = $postType === 'tourivo_tour'
            || (is_array($postType) && in_array('tourivo_tour', $postType, true))
            || $query->is_post_type_archive('tourivo_tour');
        if (!$isTourArchive) {
            return;
        }

        $destination = isset($_GET['tourivo_destination']) ? sanitize_title(wp_unslash((string) $_GET['tourivo_destination'])) : '';
        $date = isset($_GET['tourivo_date']) ? sanitize_text_field(wp_unslash((string) $_GET['tourivo_date'])) : '';
        $guests = isset($_GET['tourivo_guests']) ? max(1, min(50, absint(wp_unslash((string) $_GET['tourivo_guests'])))) : 1;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }

        if ($destination !== '') {
            $tax = $query->get('tax_query');
            $tax = is_array($tax) ? $tax : [];
            $tax[] = [
                'taxonomy' => 'tourivo_destination',
                'field'    => 'slug',
                'terms'    => $destination,
            ];
            $query->set('tax_query', $tax);
        }

        if ($date === '') {
            return;
        }

        $today = wp_date('Y-m-d');
        if ($date < $today) {
            $query->set('post__in', [0]);
            return;
        }

        $service = \Tourivo\Common\Container::getInstance()->get(\Tourivo\Services\InventoryService::class);
        if (!$service instanceof \Tourivo\Services\InventoryService) {
            return;
        }

        $ids = $service->openTourIds($date, $guests, $destination);
        $query->set('post__in', $ids !== [] ? $ids : [0]);
    }

    /**
     * Tell the visitor how many tours matched the date they picked.
     */
    public function renderTourSearchSummary(): void
    {
        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            return;
        }

        if (is_tax('tourivo_destination') || is_post_type_archive(['tourivo_tour', 'tourivo_hotel'])) {
            echo $this->destinationSearchMarkup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo $this->searchSummaryMarkup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Drop the blog heading and post list when the homepage is the posts index.
     *
     * @param string               $content
     * @param array<string, mixed> $block
     */
    public function hideHomeBlog(string $content, array $block): string
    {
        if (is_admin() || !is_front_page() || !is_home()) {
            return $content;
        }

        $name = (string) ($block['blockName'] ?? '');
        if ($name === 'core/query' && $this->isHomePostQuery($block)) {
            return '';
        }
        if ($name === 'core/heading' && $this->isHomeBlogHeading($block)) {
            return '';
        }

        return $content;
    }

    /**
     * Classic themes print the post list from the main query. Leave block themes alone.
     */
    public function hideClassicHomePosts(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            return;
        }
        if (!$query->is_front_page() || !$query->is_home()) {
            return;
        }

        $query->set('post__in', [0]);
    }

    /**
     * @param array<string, mixed> $block
     */
    private function isHomePostQuery(array $block): bool
    {
        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        $query = isset($attrs['query']) && is_array($attrs['query']) ? $attrs['query'] : [];
        $postType = $query['postType'] ?? 'post';
        if (is_array($postType)) {
            return in_array('post', $postType, true);
        }

        return $postType === '' || $postType === 'post';
    }

    /**
     * @param array<string, mixed> $block
     */
    private function isHomeBlogHeading(array $block): bool
    {
        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        if ((int) ($attrs['level'] ?? 2) !== 1) {
            return false;
        }

        $text = trim(wp_strip_all_tags((string) ($block['innerHTML'] ?? '')));
        $label = __('Blog', 'twentytwentyfive');

        return $text !== '' && ($text === $label || $text === 'Blog');
    }

    /**
     * Place the search bar at the top of the homepage.
     *
     * @param string               $content
     * @param array<string, mixed> $block
     */
    public function prependHomeSearch(string $content, array $block): string
    {
        $tag = '';
        if (isset($block['attrs']) && is_array($block['attrs'])) {
            $tag = (string) ($block['attrs']['tagName'] ?? '');
        }
        if (($block['blockName'] ?? '') !== 'core/group' || $tag !== 'main') {
            return $content;
        }
        if (!is_front_page()) {
            return $content;
        }

        $markup = $this->homeSearchMarkup();
        if ($markup === '') {
            return $content;
        }

        $updated = preg_replace('/(<main\b[^>]*>)/', '$1' . $markup, $content, 1, $count);

        return $count === 1 ? $updated : $markup . $content;
    }

    /**
     * Place the search bar above a static homepage's content.
     */
    public function prependHomeSearchToContent(string $content): string
    {
        if (!is_front_page() || !is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        return $this->homeSearchMarkup() . $content;
    }

    /**
     * Place the search bar above the post list on a classic theme homepage.
     */
    public function renderHomeSearch(): void
    {
        if ((function_exists('wp_is_block_theme') && wp_is_block_theme()) || !is_front_page() || is_admin()) {
            return;
        }

        $query = $GLOBALS['wp_query'] ?? null;
        if (!$query instanceof \WP_Query || !$query->is_main_query()) {
            return;
        }

        echo $this->homeSearchMarkup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * @return string
     */
    private function homeSearchMarkup(): string
    {
        static $done = false;
        if ($done || is_admin()) {
            return '';
        }

        if (is_singular()) {
            $post = get_post();
            $content = $post instanceof \WP_Post ? (string) $post->post_content : '';
            if (str_contains($content, 'tourivo_search_bar') || str_contains($content, 'tourivo/search-bar')) {
                return '';
            }
        }

        $done = true;
        if (function_exists('tourivo_load_plugin_textdomain')) {
            tourivo_load_plugin_textdomain();
        }

        $html = \Tourivo\Shortcodes\SearchBarShortcode::render();
        $tours = $this->homeOpenToursMarkup();
        $hotels = $this->homeHotelsMarkup();
        $welcome = '<p class="tourivo-home-welcome">' . esc_html(__('Find your next tour or hotel.', 'tourivo')) . '</p>';
        $markup = '<div class="tourivo-home-search">' . $welcome . $html . $this->homeDestinationMarkup() . '</div>' . $tours . $hotels;

        return $markup;
    }

    /**
     * Destination buttons under the homepage search bar.
     */
    private function homeDestinationMarkup(): string
    {
        $terms = get_terms([
            'taxonomy'   => 'tourivo_destination',
            'hide_empty' => true,
            'number'     => 8,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);
        if (!is_array($terms) || $terms === []) {
            return '';
        }

        $links = '';
        $currentId = 0;
        $queried = get_queried_object();
        if ($queried instanceof \WP_Term && $queried->taxonomy === 'tourivo_destination') {
            $currentId = (int) $queried->term_id;
        } elseif (
            $queried instanceof \WP_Post
            && in_array($queried->post_type, ['tourivo_tour', 'tourivo_hotel'], true)
        ) {
            $placeTerms = get_the_terms($queried, 'tourivo_destination');
            if (is_array($placeTerms) && isset($placeTerms[0]) && $placeTerms[0] instanceof \WP_Term) {
                $currentId = (int) $placeTerms[0]->term_id;
            }
        }
        $kept = ['post_type' => 'tourivo_tour'];
        if (isset($_GET['tourivo_date'])) {
            $date = sanitize_text_field(wp_unslash((string) $_GET['tourivo_date']));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $kept['tourivo_date'] = $date;
            }
        }
        if (isset($_GET['tourivo_guests'])) {
            $kept['tourivo_guests'] = (string) max(1, min(50, absint(wp_unslash((string) $_GET['tourivo_guests']))));
        }
        foreach ($terms as $term) {
            if (!$term instanceof \WP_Term || $term->name === '') {
                continue;
            }
            $url = get_term_link($term);
            if (!is_string($url) || $url === '') {
                continue;
            }
            $url = add_query_arg($kept, $url);
            $current = $currentId === (int) $term->term_id;
            $links .= '<a href="' . esc_url($url) . '"'
                . ($current ? ' class="is-current" aria-current="page"' : '')
                . '>' . esc_html($term->name) . '</a>';
        }
        if ($links === '') {
            return '';
        }

        return '<nav class="tourivo-home-places" aria-label="' . esc_attr__('Destinations', 'tourivo') . '">' . $links . '</nav>';
    }

    /**
     * Cards for tours that still have a seat on an upcoming day.
     *
     * @return string
     */
    private function homeOpenToursMarkup(): string
    {
        $service = \Tourivo\Common\Container::getInstance()->get(\Tourivo\Services\InventoryService::class);
        if (!$service instanceof \Tourivo\Services\InventoryService) {
            return '';
        }

        $ids = $service->upcomingOpenTourIds(45, 1);
        if ($ids === []) {
            return '';
        }

        $ids = get_posts([
            'post_type'      => 'tourivo_tour',
            'post_status'    => 'publish',
            'post__in'       => $ids,
            'posts_per_page' => 6,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        if (!is_array($ids) || $ids === []) {
            return '';
        }

        $archive = get_post_type_archive_link('tourivo_tour');
        if (!is_string($archive) || $archive === '') {
            $archive = home_url('/?post_type=tourivo_tour');
        }

        ob_start();
        echo '<section class="tourivo-home-tours">';
        echo '<div class="tourivo-home-tours-head">';
        echo '<h2>' . esc_html__('Tours you can book', 'tourivo') . '</h2>';
        echo '<a href="' . esc_url($archive) . '">' . esc_html__('See all tours', 'tourivo') . '</a>';
        echo '</div>';
        echo '<div class="tourivo-grid" style="--trv-grid-cols: 3;">';
        foreach ($ids as $id) {
            $tour = new \Tourivo\Models\Tour((int) $id);
            if ($tour->getPost() === null) {
                continue;
            }
            tourivo_get_template('cards/tour-card.php', ['tour' => $tour]);
        }
        echo '</div></section>';

        return ob_get_clean() ?: '';
    }

    /**
     * Recent hotels under the open tours on the homepage.
     *
     * @return string
     */
    private function homeHotelsMarkup(): string
    {
        $ids = get_posts([
            'post_type'      => 'tourivo_hotel',
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        if (!is_array($ids) || $ids === []) {
            return '';
        }

        $archive = get_post_type_archive_link('tourivo_hotel');
        if (!is_string($archive) || $archive === '') {
            $archive = home_url('/?post_type=tourivo_hotel');
        }

        ob_start();
        echo '<section class="tourivo-home-hotels">';
        echo '<div class="tourivo-home-tours-head">';
        echo '<h2>' . esc_html__('Places to stay', 'tourivo') . '</h2>';
        echo '<a href="' . esc_url($archive) . '">' . esc_html__('See all hotels', 'tourivo') . '</a>';
        echo '</div>';
        echo '<div class="tourivo-grid" style="--trv-grid-cols: 3;">';
        foreach ($ids as $id) {
            $hotel = new \Tourivo\Models\Hotel((int) $id);
            if ($hotel->getPost() === null) {
                continue;
            }
            tourivo_get_template('cards/hotel-card.php', ['hotel' => $hotel]);
        }
        echo '</div></section>';

        return ob_get_clean() ?: '';
    }

    /**
     * Show the destination buttons under a destination archive title.
     *
     * @param array<string, mixed> $block
     */
    public function appendDestinationChips(string $content, array $block): string
    {
        if (is_admin() || ($block['blockName'] ?? '') !== 'core/query-title') {
            return $content;
        }
        if (!is_tax('tourivo_destination') && !is_post_type_archive(['tourivo_tour', 'tourivo_hotel'])) {
            return $content;
        }
        $type = 'archive';
        if (isset($block['attrs']) && is_array($block['attrs'])) {
            $type = (string) ($block['attrs']['type'] ?? 'archive');
        }
        if ($type !== 'archive') {
            return $content;
        }

        return $content . $this->destinationSearchMarkup();
    }

    /**
     * Search bar and destination buttons for a destination archive.
     */
    private function destinationSearchMarkup(): string
    {
        $search = \Tourivo\Shortcodes\SearchBarShortcode::render();
        $chips = $this->homeDestinationMarkup();
        if ($search === '' && $chips === '') {
            return '';
        }

        return '<div class="tourivo-destination-search">' . $search . $chips . '</div>';
    }

    /**
     * Search bar shown above a single tour or hotel.
     */
    public function listingSearchMarkup(string $markup = ''): string
    {
        return $this->destinationSearchMarkup();
    }

    /**
     * Other tours and hotels that share this listing's destination.
     */
    public function relatedPlaceMarkup(string $markup = ''): string
    {
        if ($markup !== '' || is_admin() || !is_singular(['tourivo_tour', 'tourivo_hotel'])) {
            return $markup;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post) {
            return '';
        }

        $terms = get_the_terms($post, 'tourivo_destination');
        if (!is_array($terms) || !isset($terms[0]) || !$terms[0] instanceof \WP_Term) {
            return '';
        }

        return $this->relatedPlaceCards($post, $terms[0]);
    }

    /**
     * Cards for the rest of one destination. The open listing's own type comes first.
     */
    private function relatedPlaceCards(\WP_Post $post, \WP_Term $term): string
    {
        $tourIds = $this->relatedIds('tourivo_tour', (int) $post->ID, (int) $term->term_id);
        $hotelIds = $this->relatedIds('tourivo_hotel', (int) $post->ID, (int) $term->term_id);
        if ($tourIds === [] && $hotelIds === []) {
            return '';
        }

        $url = get_term_link($term);
        if (!is_string($url) || $url === '') {
            $url = home_url('/');
        }
        $url = add_query_arg('post_type', 'tourivo_tour', $url);

        $sections = $post->post_type === 'tourivo_hotel'
            ? ['hotel' => $hotelIds, 'tour' => $tourIds]
            : ['tour' => $tourIds, 'hotel' => $hotelIds];

        ob_start();
        echo '<div class="tourivo-related-place">';
        foreach ($sections as $kind => $ids) {
            if ($ids === []) {
                continue;
            }
            $isTour = $kind === 'tour';
            $heading = $isTour
                ? sprintf(
                    /* translators: %s: destination name */
                    __('More tours in %s', 'tourivo'),
                    $term->name
                )
                : sprintf(
                    /* translators: %s: destination name */
                    __('Places to stay in %s', 'tourivo'),
                    $term->name
                );
            $seeAll = $isTour
                ? __('See all tours', 'tourivo')
                : __('See all hotels', 'tourivo');
            $sectionClass = $isTour ? 'tourivo-home-tours' : 'tourivo-home-hotels';
            echo '<section class="' . esc_attr($sectionClass) . '">';
            echo '<div class="tourivo-home-tours-head">';
            echo '<h2>' . esc_html($heading) . '</h2>';
            echo '<a href="' . esc_url($url) . '">' . esc_html($seeAll) . '</a>';
            echo '</div>';
            echo '<div class="tourivo-grid" style="--trv-grid-cols: 3;">';
            foreach ($ids as $id) {
                if ($isTour) {
                    $item = new \Tourivo\Models\Tour((int) $id);
                    if ($item->getPost() === null) {
                        continue;
                    }
                    tourivo_get_template('cards/tour-card.php', ['tour' => $item]);
                } else {
                    $item = new \Tourivo\Models\Hotel((int) $id);
                    if ($item->getPost() === null) {
                        continue;
                    }
                    tourivo_get_template('cards/hotel-card.php', ['hotel' => $item]);
                }
            }
            echo '</div></section>';
        }
        echo '</div>';

        return ob_get_clean() ?: '';
    }

    /**
     * Published listings in one destination, leaving out the page that is open.
     *
     * @return array<int, int>
     */
    private function relatedIds(string $postType, int $currentId, int $termId): array
    {
        $ids = get_posts([
            'post_type'        => $postType,
            'post_status'      => 'publish',
            'posts_per_page'   => 6,
            'post__not_in'     => [$currentId],
            'orderby'          => 'date',
            'order'            => 'DESC',
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => true,
            'tax_query'        => [
                [
                    'taxonomy' => 'tourivo_destination',
                    'field'    => 'term_id',
                    'terms'    => [$termId],
                ],
            ],
        ]);
        if (!is_array($ids)) {
            return [];
        }

        return array_map('intval', $ids);
    }

    /**
     * Place the same note above the archive list in a block theme.
     *
     * @param string               $content
     * @param array<string, mixed> $block
     */
    public function prependSearchSummary(string $content, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'core/query') {
            return $content;
        }
        if (!function_exists('wp_is_block_theme') || !wp_is_block_theme()) {
            return $content;
        }

        if ($this->isMainListingQuery($block)) {
            $cards = '';
            if ($this->isTourListing()) {
                $cards = $this->searchTourCardsMarkup($this->isTourSearch());
            } elseif ($this->isHotelListing()) {
                $cards = $this->archiveHotelCardsMarkup();
            }
            if ($cards !== '') {
                $content = $this->replacePostTemplateList($content, $cards);
            }
        }

        return $this->searchSummaryMarkup() . $content;
    }

    /**
     * Replace the theme post list inside a search query with tour cards.
     */
    private function replacePostTemplateList(string $content, string $cards): string
    {
        $marker = strpos($content, 'wp-block-post-template');
        if ($marker === false) {
            return $content;
        }

        $ulStart = strrpos(substr($content, 0, $marker), '<ul');
        if ($ulStart === false) {
            return $content;
        }

        $offset = $ulStart;
        $depth = 0;
        $length = strlen($content);
        while ($offset < $length) {
            $nextUl = strpos($content, '<ul', $offset);
            $nextEnd = strpos($content, '</ul>', $offset);
            if ($nextEnd === false) {
                return $content;
            }
            if ($nextUl !== false && $nextUl < $nextEnd) {
                $depth++;
                $offset = $nextUl + 3;
                continue;
            }
            $depth--;
            $offset = $nextEnd + 5;
            if ($depth === 0) {
                return substr($content, 0, $ulStart) . $cards . substr($content, $offset);
            }
        }

        return $content;
    }

    /**
     * @return string
     */
    private function searchSummaryMarkup(): string
    {
        static $done = false;
        if ($done || is_admin()) {
            return '';
        }

        $postType = get_query_var('post_type');
        $isTour = $postType === 'tourivo_tour'
            || (is_array($postType) && in_array('tourivo_tour', $postType, true))
            || is_post_type_archive('tourivo_tour');
        if (!$isTour) {
            return '';
        }

        $destination = $this->searchedDestination();
        $date = isset($_GET['tourivo_date']) ? sanitize_text_field(wp_unslash((string) $_GET['tourivo_date'])) : '';
        $guests = isset($_GET['tourivo_guests']) ? max(1, min(50, absint(wp_unslash((string) $_GET['tourivo_guests'])))) : 1;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }
        $searched = $destination !== '' || isset($_GET['tourivo_date']) || isset($_GET['tourivo_guests']);
        if ($date === '' && $destination === '' && !$searched) {
            return '';
        }

        $done = true;
        if (function_exists('tourivo_load_plugin_textdomain')) {
            tourivo_load_plugin_textdomain();
        }

        $count = isset($GLOBALS['wp_query']) && $GLOBALS['wp_query'] instanceof \WP_Query
            ? (int) $GLOBALS['wp_query']->found_posts
            : 0;
        $place = '';
        if ($destination !== '') {
            $term = get_term_by('slug', $destination, 'tourivo_destination');
            $place = ($term instanceof \WP_Term) ? $term->name : '';
        }

        $hotelIds = [];
        $service = \Tourivo\Common\Container::getInstance()->get(\Tourivo\Services\InventoryService::class);
        if ($service instanceof \Tourivo\Services\InventoryService) {
            $hotelIds = $service->matchingHotelIds($date, $guests, $destination);
        }
        $hotelCount = count($hotelIds);

        if ($date !== '') {
            $stamp = strtotime($date . ' 12:00:00') ?: 0;
            $months = [
                __('January', 'tourivo'), __('February', 'tourivo'), __('March', 'tourivo'),
                __('April', 'tourivo'), __('May', 'tourivo'), __('June', 'tourivo'),
                __('July', 'tourivo'), __('August', 'tourivo'), __('September', 'tourivo'),
                __('October', 'tourivo'), __('November', 'tourivo'), __('December', 'tourivo'),
            ];
            $pretty = $stamp > 0
                ? ((int) wp_date('j', $stamp)) . ' ' . ($months[(int) wp_date('n', $stamp) - 1] ?? '')
                : $date;
            $tourBit = sprintf(_n('%d tour', '%d tours', $count, 'tourivo'), $count);
            $hotelBit = sprintf(_n('%d hotel', '%d hotels', $hotelCount, 'tourivo'), $hotelCount);
            if ($count > 0 && $hotelCount > 0) {
                $message = sprintf(__('%1$s and %2$s are open on %3$s for %4$d travelers.', 'tourivo'), $tourBit, $hotelBit, $pretty, $guests);
            } elseif ($count > 0) {
                $tourPattern = $guests === 1
                    ? _n('%1$d tour is open on %2$s for %3$d traveler.', '%1$d tours are open on %2$s for %3$d traveler.', $count, 'tourivo')
                    : _n('%1$d tour is open on %2$s for %3$d travelers.', '%1$d tours are open on %2$s for %3$d travelers.', $count, 'tourivo');
                $message = sprintf($tourPattern, $count, $pretty, $guests);
            } elseif ($hotelCount > 0) {
                $message = sprintf(_n('%1$s is open on %2$s for %3$d travelers.', '%1$s are open on %2$s for %3$d travelers.', $hotelCount, 'tourivo'), $hotelBit, $pretty, $guests);
            } else {
                $message = __('No tours or hotels are open on that day. Try another date.', 'tourivo');
            }
            if ($place !== '') {
                $message .= ' ' . sprintf(__('Destination: %s', 'tourivo'), $place);
            }
        } elseif ($destination !== '') {
            $label = $place !== '' ? $place : $destination;
            $message = $hotelCount > 0
                ? sprintf(__('Tours and hotels in %s.', 'tourivo'), $label)
                : sprintf(__('Tours in %s.', 'tourivo'), $label);
        } else {
            $message = '';
        }

        if ($message !== '' && function_exists('tourivo_bn_number')) {
            $banglaDigits = (function_exists('tourivo_is_bengali') && tourivo_is_bengali())
                || (bool) \Tourivo\Config\Config::get('use_bangla_digits', false);
            if ($banglaDigits) {
                $message = tourivo_bn_number($message);
            }
        }

        $summary = $message === '' ? '' : '<div class="tourivo-search-summary">' . esc_html($message) . '</div>';

        return $summary . $this->searchHotelCardsMarkup($hotelIds);
    }

    /**
     * True on the tour archive and on a destination or search that lists tours.
     */
    private function isTourListing(): bool
    {
        if (is_admin()) {
            return false;
        }

        $postType = get_query_var('post_type');

        return $postType === 'tourivo_tour'
            || (is_array($postType) && in_array('tourivo_tour', $postType, true))
            || is_post_type_archive('tourivo_tour')
            || $this->searchedDestination() !== '';
    }

    /**
     * The archive query inherits the main loop. Leave other queries on the page alone.
     *
     * @param array<string, mixed> $block
     */
    private function isMainListingQuery(array $block): bool
    {
        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        $query = isset($attrs['query']) && is_array($attrs['query']) ? $attrs['query'] : [];
        if (!empty($query['inherit'])) {
            return true;
        }

        $postType = $query['postType'] ?? '';
        if (is_array($postType)) {
            return in_array('tourivo_tour', $postType, true) || in_array('tourivo_hotel', $postType, true);
        }

        return $postType === 'tourivo_tour' || $postType === 'tourivo_hotel';
    }

    /**
     * True on the hotel archive, when that page is not already a tour search.
     */
    private function isHotelListing(): bool
    {
        if (is_admin() || $this->isTourListing()) {
            return false;
        }

        $postType = get_query_var('post_type');

        return $postType === 'tourivo_hotel'
            || (is_array($postType) && in_array('tourivo_hotel', $postType, true))
            || is_post_type_archive('tourivo_hotel');
    }

    /**
     * True when the tour archive is showing a search, not the plain tour list.
     */
    private function isTourSearch(): bool
    {
        if (is_admin()) {
            return false;
        }

        $postType = get_query_var('post_type');
        $isTour = $postType === 'tourivo_tour'
            || (is_array($postType) && in_array('tourivo_tour', $postType, true))
            || is_post_type_archive('tourivo_tour');
        if (!$isTour) {
            return false;
        }

        return $this->searchedDestination() !== ''
            || isset($_GET['tourivo_date'])
            || isset($_GET['tourivo_guests']);
    }

    /**
     * Tour cards for the current page of the archive or search results.
     *
     * @param bool $withHeading Search pages name the list. The archive already has a title.
     */
    private function searchTourCardsMarkup(bool $withHeading = true): string
    {
        $query = $GLOBALS['wp_query'] ?? null;
        if (!$query instanceof \WP_Query || $query->posts === []) {
            return '';
        }

        $archive = get_post_type_archive_link('tourivo_tour');
        if (!is_string($archive) || $archive === '') {
            $archive = home_url('/?post_type=tourivo_tour');
        }

        ob_start();
        $sectionClass = $withHeading ? 'tourivo-home-tours tourivo-search-tours' : 'tourivo-home-tours tourivo-archive-tours';
        echo '<section class="' . esc_attr($sectionClass) . '">';
        if ($withHeading) {
            echo '<div class="tourivo-home-tours-head">';
            echo '<h2>' . esc_html__('Tours you can book', 'tourivo') . '</h2>';
            echo '<a href="' . esc_url($archive) . '">' . esc_html__('See all tours', 'tourivo') . '</a>';
            echo '</div>';
        }
        echo '<div class="tourivo-grid" style="--trv-grid-cols: 3;">';
        foreach ($query->posts as $post) {
            if (!$post instanceof \WP_Post || $post->post_type !== 'tourivo_tour') {
                continue;
            }
            $tour = new \Tourivo\Models\Tour($post);
            tourivo_get_template('cards/tour-card.php', ['tour' => $tour]);
        }
        echo '</div></section>';

        return ob_get_clean() ?: '';
    }

    /**
     * Hotel cards for the current page of the hotel archive.
     */
    private function archiveHotelCardsMarkup(): string
    {
        $query = $GLOBALS['wp_query'] ?? null;
        if (!$query instanceof \WP_Query || $query->posts === []) {
            return '';
        }

        ob_start();
        echo '<section class="tourivo-home-hotels tourivo-archive-hotels">';
        echo '<div class="tourivo-grid" style="--trv-grid-cols: 3;">';
        $shown = 0;
        foreach ($query->posts as $post) {
            if (!$post instanceof \WP_Post || $post->post_type !== 'tourivo_hotel') {
                continue;
            }
            $hotel = new \Tourivo\Models\Hotel($post);
            tourivo_get_template('cards/hotel-card.php', ['hotel' => $hotel]);
            $shown++;
        }
        echo '</div></section>';
        $markup = ob_get_clean() ?: '';

        return $shown > 0 ? $markup : '';
    }

    /**
     * Destination from the search form, or from the destination archive the form redirects to.
     */
    private function searchedDestination(): string
    {
        $term = get_queried_object();
        if ($term instanceof \WP_Term && $term->taxonomy === 'tourivo_destination') {
            return (string) $term->slug;
        }

        $fromQuery = get_query_var('tourivo_destination');
        if (is_array($fromQuery)) {
            $fromQuery = reset($fromQuery);
        }
        if (is_string($fromQuery) && $fromQuery !== '') {
            return $fromQuery;
        }

        if (isset($_GET['tourivo_destination'])) {
            return sanitize_text_field(wp_unslash((string) $_GET['tourivo_destination']));
        }

        return '';
    }

    /**
     * Hotel cards for a search. The tour list stays in the archive query below this.
     *
     * @param array<int> $ids
     */
    private function searchHotelCardsMarkup(array $ids): string
    {
        $ids = array_slice($ids, 0, 12);
        if ($ids === []) {
            return '';
        }

        $archive = get_post_type_archive_link('tourivo_hotel');
        if (!is_string($archive) || $archive === '') {
            $archive = home_url('/?post_type=tourivo_hotel');
        }

        ob_start();
        echo '<section class="tourivo-home-hotels tourivo-search-hotels">';
        echo '<div class="tourivo-home-tours-head">';
        echo '<h2>' . esc_html__('Places to stay', 'tourivo') . '</h2>';
        echo '<a href="' . esc_url($archive) . '">' . esc_html__('See all hotels', 'tourivo') . '</a>';
        echo '</div>';
        echo '<div class="tourivo-grid" style="--trv-grid-cols: 3;">';
        foreach ($ids as $id) {
            $hotel = new \Tourivo\Models\Hotel((int) $id);
            if ($hotel->getPost() === null) {
                continue;
            }
            tourivo_get_template('cards/hotel-card.php', ['hotel' => $hotel]);
        }
        echo '</div></section>';

        return ob_get_clean() ?: '';
    }
}

