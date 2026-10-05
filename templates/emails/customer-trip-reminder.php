<?php
/**
 * Customer: Trip Reminder Email Template Part
 *
 * @package Tourivo
 * @var array  $booking
 * @var string $siteName
 * @var string $voucherUrl
 * @var string $lookupUrl
 * @var string $additionalContent
 */

if (!defined('ABSPATH')) {
    exit;
}

$tourivoBooking = isset($booking) && is_array($booking) ? $booking : [];
$tourivoVoucher = isset($voucherUrl) && is_string($voucherUrl) ? $voucherUrl : '';
$tourivoLookup  = isset($lookupUrl) && is_string($lookupUrl) ? $lookupUrl : '';
$tourivoExtra   = isset($additionalContent) && is_string($additionalContent) ? $additionalContent : '';
?>
<p style="font-size: 15px; margin-top: 0;">
    <?php
    echo esc_html(
        sprintf(
            /* translators: %s: Customer Name */
            __('Hello %s,', 'tourivo'),
            $tourivoBooking['customer_name'] ?? __('Traveler', 'tourivo')
        )
    );
    ?>
</p>
<p style="color: #475569; font-size: 14px;">
    <?php esc_html_e('This is a friendly reminder that your upcoming trip / reservation is approaching very soon! Please ensure you have all your travel documents ready.', 'tourivo'); ?>
</p>

<div class="tourivo-highlight-box">
    <strong>📅 <?php esc_html_e('Trip Reminder', 'tourivo'); ?></strong><br>
    <?php
    echo esc_html(
        sprintf(
            /* translators: %s: Check-in date */
            __('Your reservation check-in date is on %s.', 'tourivo'),
            $tourivoBooking['check_in'] ?? ''
        )
    );
    ?>
</div>

<div class="tourivo-info-card">
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Booking Reference:', 'tourivo'); ?></span>
        <span class="tourivo-info-value" style="color: #0284c7;"><?php echo esc_html($tourivoBooking['booking_code'] ?? 'N/A'); ?></span>
    </div>
    <?php if (!empty($tourivoBooking['item_title'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Trip / Stay:', 'tourivo'); ?></span>
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
        <span class="tourivo-info-label"><?php esc_html_e('Travelers / Guests:', 'tourivo'); ?></span>
        <span class="tourivo-info-value">
            <?php echo esc_html((string)($tourivoBooking['adults'] ?? 1)); ?> <?php esc_html_e('Adult(s)', 'tourivo'); ?>
            <?php if (!empty($tourivoBooking['children'])) : ?>
                , <?php echo esc_html((string)$tourivoBooking['children']); ?> <?php esc_html_e('Child(ren)', 'tourivo'); ?>
            <?php endif; ?>
            <?php if (!empty($tourivoBooking['infants'])) : ?>
                , <?php echo esc_html((string)$tourivoBooking['infants']); ?> <?php esc_html_e('Infant(s)', 'tourivo'); ?>
            <?php endif; ?>
        </span>
    </div>
</div>

<?php if (!empty($tourivoExtra)) : ?>
    <div style="margin: 20px 0; font-size: 14px; color: #334155;">
        <?php echo wp_kses_post($tourivoExtra); ?>
    </div>
<?php endif; ?>

<div style="text-align: center; margin-top: 24px;">
    <?php if (!empty($tourivoVoucher)) : ?>
        <a href="<?php echo esc_url($tourivoVoucher); ?>" class="tourivo-btn" target="_blank" rel="noopener noreferrer">
            🎟️ <?php esc_html_e('Print Your Travel Voucher', 'tourivo'); ?>
        </a>
    <?php endif; ?>
</div>
