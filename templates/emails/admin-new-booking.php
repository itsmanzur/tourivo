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

$tourivoBooking = isset($booking) && is_array($booking) ? $booking : [];
$tourivoExtra   = isset($additionalContent) && is_string($additionalContent) ? $additionalContent : '';
$adminBookingsUrl = admin_url('admin.php?page=tourivo-bookings');
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
        <span class="tourivo-info-value" style="color: #0284c7;"><?php echo esc_html($tourivoBooking['booking_code'] ?? 'N/A'); ?></span>
    </div>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Customer Name:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['customer_name'] ?? 'N/A'); ?></span>
    </div>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Customer Email:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><a href="mailto:<?php echo esc_attr($tourivoBooking['customer_email'] ?? ''); ?>"><?php echo esc_html($tourivoBooking['customer_email'] ?? 'N/A'); ?></a></span>
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
            <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['check_in']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoBooking['check_out'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Check-out Date:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['check_out']); ?></span>
        </div>
    <?php endif; ?>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Travelers:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><?php echo esc_html((string)($tourivoBooking['adults'] ?? 1)); ?> Adults<?php echo !empty($tourivoBooking['children']) ? ', ' . esc_html((string)$tourivoBooking['children']) . ' Children' : ''; ?></span>
    </div>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Booking Status:', 'tourivo'); ?></span>
        <span class="tourivo-info-value" style="text-transform: capitalize;"><?php echo esc_html($tourivoBooking['booking_status'] ?? 'pending'); ?></span>
    </div>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Payment Status:', 'tourivo'); ?></span>
        <span class="tourivo-info-value" style="text-transform: capitalize;"><?php echo esc_html($tourivoBooking['payment_status'] ?? 'pending'); ?></span>
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
