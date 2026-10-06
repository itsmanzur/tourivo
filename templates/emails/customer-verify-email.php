<?php
/**
 * Customer: Confirm Your Email Template Part
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
$tourivoVerify  = isset($tourivoBooking['verify_url']) ? (string) $tourivoBooking['verify_url'] : '';
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
    <?php
    echo esc_html(
        sprintf(
            /* translators: 1: Item title, 2: Booking code */
            __('We received a booking request for %1$s (reference #%2$s). Please confirm that this email address is yours to secure the reservation.', 'tourivo'),
            $tourivoBooking['item_title'] ?? '',
            $tourivoBooking['booking_code'] ?? ''
        )
    );
    ?>
</p>

<?php if ($tourivoVerify !== '') : ?>
    <p style="text-align: center; margin: 24px 0;">
        <a href="<?php echo esc_url($tourivoVerify); ?>" style="display: inline-block; background: #0d9488; color: #ffffff; padding: 12px 26px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px;">
            <?php esc_html_e('Confirm My Email', 'tourivo'); ?>
        </a>
    </p>
<?php endif; ?>

<div class="tourivo-warning-box">
    <strong>⏳ <?php esc_html_e('Action required', 'tourivo'); ?></strong><br>
    <?php esc_html_e('Unconfirmed bookings are released automatically after a short time. If you did not make this request, simply ignore this email.', 'tourivo'); ?>
</div>

<?php if ($tourivoExtra !== '') : ?>
    <div style="margin-top: 16px; font-size: 14px; color: #475569;">
        <?php echo wp_kses_post($tourivoExtra); ?>
    </div>
<?php endif; ?>
