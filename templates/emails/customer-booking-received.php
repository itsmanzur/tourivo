<?php
/**
 * Customer: Booking Received (Pending) Email Template Part
 *
 * @package Tourivo
 * @var array  $booking
 * @var string $siteName
 * @var string $voucherUrl
 * @var string $lookupUrl
 * @var string $offlinePaymentInstructions
 * @var string $additionalContent
 */

if (!defined('ABSPATH')) {
    exit;
}

$tourivoBooking = isset($booking) && is_array($booking) ? $booking : [];
$tourivoLookup  = isset($lookupUrl) && is_string($lookupUrl) ? $lookupUrl : '';
$tourivoPayment = isset($offlinePaymentInstructions) && is_string($offlinePaymentInstructions) ? $offlinePaymentInstructions : '';
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
    <?php esc_html_e('Thank you for choosing us! We have received your booking request. Our team is reviewing the availability and will confirm your reservation shortly.', 'tourivo'); ?>
</p>

<div class="tourivo-warning-box">
    <strong>⏳ <?php esc_html_e('Status: Pending Confirmation', 'tourivo'); ?></strong><br>
    <?php esc_html_e('Your reservation is currently on hold while we review your itinerary.', 'tourivo'); ?>
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
    <?php if (!empty($tourivoBooking['raw_discount']) && (float)$tourivoBooking['raw_discount'] > 0) : ?>
        <div class="tourivo-info-row" style="color: #15803d;">
            <span class="tourivo-info-label"><?php esc_html_e('Discount:', 'tourivo'); ?></span>
            <span class="tourivo-info-value">-<?php echo esc_html($tourivoBooking['discount_amount']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoBooking['raw_tax']) && (float)$tourivoBooking['raw_tax'] > 0) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Tax / VAT:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoBooking['tax_amount']); ?></span>
        </div>
    <?php endif; ?>
    <div class="tourivo-info-row" style="font-size: 15px;">
        <span class="tourivo-info-label"><?php esc_html_e('Total Amount:', 'tourivo'); ?></span>
        <span class="tourivo-info-value" style="color: #0f172a;"><?php echo esc_html($tourivoBooking['total_amount'] ?? '$0.00'); ?></span>
    </div>
</div>

<?php if (!empty($tourivoPayment) && (empty($tourivoBooking['payment_status']) || $tourivoBooking['payment_status'] !== 'paid')) : ?>
    <div class="tourivo-info-card" style="background: #eff6ff; border-color: #bfdbfe;">
        <h4 style="margin: 0 0 6px 0; color: #1e40af;"><?php esc_html_e('Payment Instructions', 'tourivo'); ?></h4>
        <div style="font-size: 13.5px; color: #1e3a8a; line-height: 1.5;">
            <?php echo nl2br(esc_html($tourivoPayment)); ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($tourivoExtra)) : ?>
    <div style="margin: 20px 0; font-size: 14px; color: #334155;">
        <?php echo wp_kses_post($tourivoExtra); ?>
    </div>
<?php endif; ?>

<?php if (!empty($tourivoLookup)) : ?>
    <div style="text-align: center; margin-top: 24px;">
        <a href="<?php echo esc_url($tourivoLookup); ?>" class="tourivo-btn">
            🔍 <?php esc_html_e('Track Your Booking Online', 'tourivo'); ?>
        </a>
    </div>
<?php endif; ?>
