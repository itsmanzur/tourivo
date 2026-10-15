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

if (function_exists('tourivo_load_plugin_textdomain')) {
    tourivo_load_plugin_textdomain();
}

$tourivoDestinations = get_terms([
    'taxonomy'   => 'tourivo_destination',
    'hide_empty' => false,
]);

$tourivoSelectedDest = '';
$tourivoQueriedPlace = get_queried_object();
if ($tourivoQueriedPlace instanceof \WP_Term && $tourivoQueriedPlace->taxonomy === 'tourivo_destination') {
    $tourivoSelectedDest = $tourivoQueriedPlace->slug;
} elseif (isset($_GET['tourivo_destination'])) {
    $tourivoSelectedDest = sanitize_text_field(wp_unslash((string) $_GET['tourivo_destination']));
} elseif (
    $tourivoQueriedPlace instanceof \WP_Post
    && in_array($tourivoQueriedPlace->post_type, ['tourivo_tour', 'tourivo_hotel'], true)
) {
    $tourivoPlaceTerms = get_the_terms($tourivoQueriedPlace, 'tourivo_destination');
    if (is_array($tourivoPlaceTerms) && isset($tourivoPlaceTerms[0]) && $tourivoPlaceTerms[0] instanceof \WP_Term) {
        $tourivoSelectedDest = $tourivoPlaceTerms[0]->slug;
    }
}
$tourivoSelectedDate = '';
if (isset($_GET['tourivo_date'])) {
    $tourivoRawDate = sanitize_text_field(wp_unslash((string) $_GET['tourivo_date']));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tourivoRawDate)) {
        $tourivoSelectedDate = $tourivoRawDate;
    }
}
$tourivoSelectedGuests = isset($_GET['tourivo_guests']) ? max(1, min(50, absint(wp_unslash((string) $_GET['tourivo_guests'])))) : 2;
$tourivoBanglaDigits = (function_exists('tourivo_is_bengali') && tourivo_is_bengali())
    || (bool) \Tourivo\Config\Config::get('use_bangla_digits', false);

$tourivoDateLabel = __('Any day', 'tourivo');
if ($tourivoSelectedDate !== '') {
    $tourivoStamp = strtotime($tourivoSelectedDate . ' 12:00:00') ?: 0;
    $tourivoMonths = [
        __('January', 'tourivo'), __('February', 'tourivo'), __('March', 'tourivo'),
        __('April', 'tourivo'), __('May', 'tourivo'), __('June', 'tourivo'),
        __('July', 'tourivo'), __('August', 'tourivo'), __('September', 'tourivo'),
        __('October', 'tourivo'), __('November', 'tourivo'), __('December', 'tourivo'),
    ];
    if ($tourivoStamp > 0) {
        $tourivoDateLabel = (int) wp_date('j', $tourivoStamp) . ' ' . ($tourivoMonths[(int) wp_date('n', $tourivoStamp) - 1] ?? '');
        if ($tourivoBanglaDigits && function_exists('tourivo_bn_number')) {
            $tourivoDateLabel = tourivo_bn_number($tourivoDateLabel);
        }
    }
}
?>

<div class="tourivo-search-bar-wrap">
    <form class="tourivo-search-form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <div class="search-field destination-field">
            <label><span class="dashicons dashicons-location"></span> <?php esc_html_e('Where to?', 'tourivo'); ?></label>
            <select name="tourivo_destination">
                <option value=""><?php esc_html_e('All Destinations', 'tourivo'); ?></option>
                <?php if (!empty($tourivoDestinations) && !is_wp_error($tourivoDestinations)) : foreach ($tourivoDestinations as $tourivoDest) : ?>
                    <option value="<?php echo esc_attr($tourivoDest->slug); ?>" <?php selected($tourivoSelectedDest, $tourivoDest->slug); ?>><?php echo esc_html($tourivoDest->name); ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>

        <div class="search-field date-field tourivo-search-date">
            <label><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('When?', 'tourivo'); ?></label>
            <button type="button" class="tourivo-search-date-btn" aria-expanded="false" aria-haspopup="dialog">
                <span class="tourivo-search-date-label"><?php echo esc_html($tourivoDateLabel); ?></span>
            </button>
            <input type="date" class="tourivo-search-native" name="tourivo_date" value="<?php echo esc_attr($tourivoSelectedDate); ?>" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>">
            <div class="tourivo-search-cal" hidden data-today="<?php echo esc_attr(wp_date('Y-m-d')); ?>">
                <div class="tourivo-cal" role="dialog" aria-label="<?php esc_attr_e('When?', 'tourivo'); ?>">
                    <div class="tourivo-cal-nav">
                        <button type="button" class="tourivo-cal-prev" aria-label="<?php esc_attr_e('Previous month', 'tourivo'); ?>">&lsaquo;</button>
                        <strong class="tourivo-cal-title"></strong>
                        <button type="button" class="tourivo-cal-next" aria-label="<?php esc_attr_e('Next month', 'tourivo'); ?>">&rsaquo;</button>
                    </div>
                    <div class="tourivo-cal-weekdays"></div>
                    <div class="tourivo-cal-grid"></div>
                    <p class="tourivo-search-cal-hint"><?php esc_html_e('Each date shows open tours, then open hotels.', 'tourivo'); ?></p>
                </div>
            </div>
        </div>

        <div class="search-field guests-field">
            <label><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Travelers', 'tourivo'); ?></label>
            <div class="tourivo-guest-stepper">
                <button type="button" class="tourivo-guest-minus" aria-label="<?php esc_attr_e('Fewer travelers', 'tourivo'); ?>">−</button>
                <?php if ($tourivoBanglaDigits) : ?>
                    <input type="text" class="tourivo-guest-digits" inputmode="numeric" autocomplete="off" value="<?php echo esc_attr(function_exists('tourivo_bn_number') ? tourivo_bn_number((string) $tourivoSelectedGuests) : (string) $tourivoSelectedGuests); ?>">
                <?php else : ?>
                    <input type="number" name="tourivo_guests" min="1" max="50" value="<?php echo esc_attr((string) $tourivoSelectedGuests); ?>">
                <?php endif; ?>
                <button type="button" class="tourivo-guest-plus" aria-label="<?php esc_attr_e('More travelers', 'tourivo'); ?>">+</button>
            </div>
            <?php if ($tourivoBanglaDigits) : ?>
                <input type="hidden" name="tourivo_guests" value="<?php echo esc_attr((string) $tourivoSelectedGuests); ?>">
            <?php endif; ?>
        </div>

        <input type="hidden" name="post_type" value="tourivo_tour">

        <div class="search-btn-field">
            <button type="submit" class="tourivo-btn tourivo-btn-search">
                <span class="dashicons dashicons-search"></span> <?php esc_html_e('Search', 'tourivo'); ?>
            </button>
        </div>
    </form>
</div>
