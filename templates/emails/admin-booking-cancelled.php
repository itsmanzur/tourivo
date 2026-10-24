<?php
/**
 * Admin: Booking Cancelled Notification Email Template Part
 *
 * @package Tourivo
 * @var array  $booking
 * @var string $siteName
 * @var string $additionalContent
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template included inside a function; these variables are local.

$tourivoBooking   = isset($booking) && is_array($booking) ? $booking : [];
$tourivoExtra     = isset($additionalContent) && is_string($additionalContent) ? $additionalContent : '';
$adminBookingsUrl = admin_url('admin.php?page=tourivo-bookings');
$tourivoDate = static function (string $value): string {
    if ($value === '') {
        return '';
    }

    return function_exists('tourivo_format_date') ? tourivo_format_date($value) : $value;
};
?>
<p style="font-size: 15px; margin-top: 0;">
    <strong><?php esc_html_e('Hello Administrator,', 'tourivo'); ?></strong>
</p>
<p style="color: #475569; font-size: 14px;">
    <?php esc_html_e('A booking has been marked as Cancelled. The reserved inventory capacity has been automatically released back to the calendar.', 'tourivo'); ?>
</p>

<div class="tourivo-warning-box" style="background: #fef2f2; border-color: #fecaca; color: #991b1b;">
    <strong>❌ <?php esc_html_e('Booking Cancelled', 'tourivo'); ?></strong><br>
    <?php
    echo esc_html(
        sprintf(
            /* translators: 1: Booking Code, 2: Customer Name */
            __('Reservation #%1$s by %2$s is now cancelled.', 'tourivo'),
            $tourivoBooking['booking_code'] ?? __('N/A', 'tourivo'),
            $tourivoBooking['customer_name'] ?? __('Customer', 'tourivo')
        )
    );
    ?>
</div>

<div class="tourivo-info-card">
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Booking Code:', 'tourivo'); ?></span>
        <span class="tourivo-info-value" style="color: #0284c7;"><?php echo esc_html($tourivoBooking['booking_code'] ?? __('N/A', 'tourivo')); ?></span>
    </div>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Customer Name:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['customer_name'] ?? __('N/A', 'tourivo')); ?></span>
    </div>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Customer Email:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><a href="mailto:<?php echo esc_attr($tourivoBooking['customer_email'] ?? ''); ?>"><?php echo esc_html($tourivoBooking['customer_email'] ?? __('N/A', 'tourivo')); ?></a></span>
    </div>
    <?php if (!empty($tourivoBooking['item_title'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Item / Tour:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['item_title']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoBooking['check_in'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Scheduled Check-in:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoDate((string) $tourivoBooking['check_in'])); ?></span>
        </div>
    <?php endif; ?>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Total Amount:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['total_amount'] ?? '$0.00'); ?></span>
    </div>
</div>

<?php if (!empty($tourivoExtra)) : ?>
    <div style="margin: 20px 0; font-size: 14px; color: #334155;">
        <?php echo wp_kses_post($tourivoExtra); ?>
    </div>
<?php endif; ?>

<div style="text-align: center; margin-top: 24px;">
    <a href="<?php echo esc_url($adminBookingsUrl); ?>" class="tourivo-btn">
        📋 <?php esc_html_e('Open Bookings Panel', 'tourivo'); ?>
    </a>
</div>
