<?php
/**
 * Tourivo Search Bar Template
 *
 * @package Tourivo
 * @var array<\WP_Term> $destinations
 */

if (!defined('ABSPATH')) {
    exit;
}

$tourivoDestinations = get_terms([
    'taxonomy'   => 'tourivo_destination',
    'hide_empty' => false,
]);
?>

<div class="tourivo-search-bar-wrap">
    <form class="tourivo-search-form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <div class="search-field destination-field">
            <label><span class="dashicons dashicons-location"></span> <?php esc_html_e('Where to?', 'tourivo'); ?></label>
            <select name="tourivo_destination">
                <option value=""><?php esc_html_e('All Destinations', 'tourivo'); ?></option>
                <?php if (!empty($tourivoDestinations) && !is_wp_error($tourivoDestinations)) : foreach ($tourivoDestinations as $tourivoDest) : ?>
                    <option value="<?php echo esc_attr($tourivoDest->slug); ?>"><?php echo esc_html($tourivoDest->name); ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>

        <div class="search-field date-field">
            <label><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('When?', 'tourivo'); ?></label>
            <input type="date" name="tourivo_date" min="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
        </div>

        <div class="search-field guests-field">
            <label><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Travelers', 'tourivo'); ?></label>
            <input type="number" name="tourivo_guests" min="1" value="2" placeholder="2 Guests">
        </div>

        <input type="hidden" name="post_type" value="tourivo_tour">

        <div class="search-btn-field">
            <button type="submit" class="tourivo-btn tourivo-btn-search">
                <span class="dashicons dashicons-search"></span> <?php esc_html_e('Search', 'tourivo'); ?>
            </button>
        </div>
    </form>
</div>
