<?php
/**
 * Admin: New Booking Notification Email Template Part
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

$tourivoBooking = isset($booking) && is_array($booking) ? $booking : [];
$tourivoExtra   = isset($additionalContent) && is_string($additionalContent) ? $additionalContent : '';
$adminBookingsUrl = admin_url('admin.php?page=tourivo-bookings');
$tourivoDate = static function (string $value): string {
    if ($value === '') {
        return '';
    }

    return function_exists('tourivo_format_date') ? tourivo_format_date($value) : $value;
};
$tourivoStatus = static function (string $value): string {
    $labels = [
        'pending'   => __('Pending', 'tourivo'),
        'confirmed' => __('Confirmed', 'tourivo'),
        'cancelled' => __('Cancelled', 'tourivo'),
        'paid'      => __('Paid', 'tourivo'),
        'unpaid'    => __('Unpaid', 'tourivo'),
        'partial'   => __('Partial', 'tourivo'),
        'refunded'  => __('Refunded', 'tourivo'),
    ];

    return $labels[strtolower($value)] ?? $value;
};
?>
<p style="font-size: 15px; margin-top: 0;">
    <strong><?php esc_html_e('Hello Administrator,', 'tourivo'); ?></strong>
</p>
<p style="color: #475569; font-size: 14px;">
    <?php esc_html_e('A new reservation has just been submitted on your website. Here are the booking details:', 'tourivo'); ?>
</p>

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
    <?php if (!empty($tourivoBooking['customer_phone'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Customer Phone:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['customer_phone']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoBooking['item_title'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Item / Tour:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['item_title']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoBooking['check_in'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Check-in Date:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoDate((string) $tourivoBooking['check_in'])); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoBooking['check_out'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Check-out Date:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoDate((string) $tourivoBooking['check_out'])); ?></span>
        </div>
    <?php endif; ?>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Travelers:', 'tourivo'); ?></span>
        <span class="tourivo-info-value">
            <?php echo esc_html((string) ($tourivoBooking['adults'] ?? 1)); ?> <?php esc_html_e('Adult(s)', 'tourivo'); ?>
            <?php if (!empty($tourivoBooking['children'])) : ?>
                , <?php echo esc_html((string) $tourivoBooking['children']); ?> <?php esc_html_e('Child(ren)', 'tourivo'); ?>
            <?php endif; ?>
            <?php if (!empty($tourivoBooking['infants'])) : ?>
                , <?php echo esc_html((string) $tourivoBooking['infants']); ?> <?php esc_html_e('Infant(s)', 'tourivo'); ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Booking Status:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><?php echo esc_html($tourivoStatus((string) ($tourivoBooking['booking_status'] ?? 'pending'))); ?></span>
    </div>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Payment Status:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><?php echo esc_html($tourivoStatus((string) ($tourivoBooking['payment_status'] ?? 'pending'))); ?></span>
    </div>
    <div class="tourivo-info-row" style="font-size: 15px;">
        <span class="tourivo-info-label"><?php esc_html_e('Total Amount:', 'tourivo'); ?></span>
        <span class="tourivo-info-value" style="color: #0f172a;"><?php echo esc_html($tourivoBooking['total_amount'] ?? '$0.00'); ?></span>
    </div>
    <?php if (!empty($tourivoBooking['customer_notes'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Customer Notes:', 'tourivo'); ?></span>
            <span class="tourivo-info-value" style="font-weight: normal;"><?php echo esc_html($tourivoBooking['customer_notes']); ?></span>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($tourivoExtra)) : ?>
    <div style="margin: 20px 0; font-size: 14px; color: #334155;">
        <?php echo wp_kses_post($tourivoExtra); ?>
    </div>
<?php endif; ?>

<div style="text-align: center; margin-top: 24px;">
    <a href="<?php echo esc_url($adminBookingsUrl); ?>" class="tourivo-btn">
        📋 <?php esc_html_e('Manage Bookings in Admin', 'tourivo'); ?>
    </a>
</div>
