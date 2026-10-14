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
        $this->addAction('pre_get_posts', [$this, 'filterTourSearch']);
        $this->addFilter('render_block', [$this, 'prependSearchSummary'], 10, 2);
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
        $markup = ($html === '' ? '' : '<div class="tourivo-home-search">' . $html . '</div>') . $tours . $hotels;

        return $markup;
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

        return $this->searchSummaryMarkup() . $content;
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
                $message = sprintf(__('%1$d tours are open on %2$s for %3$d travelers.', 'tourivo'), $count, $pretty, $guests);
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

        $summary = $message === '' ? '' : '<div class="tourivo-search-summary">' . esc_html($message) . '</div>';

        return $summary . $this->searchHotelCardsMarkup($hotelIds);
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

