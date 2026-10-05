<?php
/**
 * Customer: Booking Cancelled Email Template Part
 *
 * @package Tourivo
 * @var array  $booking
 * @var string $siteName
 * @var string $lookupUrl
 * @var string $additionalContent
 */

if (!defined('ABSPATH')) {
    exit;
}

$tourivoBooking = isset($booking) && is_array($booking) ? $booking : [];
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
    <?php esc_html_e('Your reservation has been cancelled. Below are the details of the cancelled booking for your reference:', 'tourivo'); ?>
</p>

<div class="tourivo-warning-box" style="background: #fef2f2; border-color: #fecaca; color: #991b1b;">
    <strong>❌ <?php esc_html_e('Booking Status: Cancelled', 'tourivo'); ?></strong><br>
    <?php esc_html_e('This reservation has been cancelled and any reserved seats or rooms have been released.', 'tourivo'); ?>
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
            <span class="tourivo-info-label"><?php esc_html_e('Scheduled Check-in:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['check_in']); ?></span>
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

<p style="color: #64748b; font-size: 13.5px; margin-top: 20px;">
    <?php esc_html_e('If you have any questions or believe this cancellation was made in error, please feel free to reply directly to this email.', 'tourivo'); ?>
</p>
