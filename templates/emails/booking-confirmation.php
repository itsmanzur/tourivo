<?php
/**
 * Tourivo Booking Confirmation Responsive Email Template
 *
 * @package Tourivo
 * @var array $booking
 * @var string $siteName
 * @var bool $isTest
 */

if (!defined('ABSPATH')) {
    exit;
}

$tourivoIsTest   = isset($isTest) ? (bool) $isTest : false;
$tourivoBooking  = isset($booking) && is_array($booking) ? $booking : [];
$tourivoSiteName = isset($siteName) && is_string($siteName) ? $siteName : get_bloginfo('name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Booking Confirmation', 'tourivo'); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; margin: 0; padding: 20px; background-color: #f1f5f9; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .header { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .header p { margin: 6px 0 0 0; opacity: 0.9; font-size: 14px; }
        .content { padding: 28px 24px; }
        .info-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 20px 0; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748b; font-weight: 600; }
        .info-value { color: #0f172a; font-weight: 700; text-align: right; }
        .total-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-top: 20px; text-align: center; }
        .total-box .total-label { font-size: 13px; color: #15803d; font-weight: 600; text-transform: uppercase; }
        .total-box .total-val { font-size: 26px; color: #166534; font-weight: 800; }
        .test-badge { background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-block; margin-bottom: 12px; }
        .footer { text-align: center; font-size: 12px; color: #64748b; padding: 20px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .footer a { color: #0284c7; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php if ($tourivoIsTest) : ?>
                <div><span class="test-badge"><?php esc_html_e('TEST NOTIFICATION', 'tourivo'); ?></span></div>
            <?php endif; ?>
            <h1>🎉 <?php esc_html_e('Booking Confirmed!', 'tourivo'); ?></h1>
            <p><?php echo esc_html($tourivoSiteName); ?> &bull; <?php esc_html_e('Travel & Tours Reservation', 'tourivo'); ?></p>
        </div>

        <div class="content">
            <p style="font-size: 15px; margin-top: 0;">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: Customer Name */
                        __('Hello %s,', 'tourivo'),
                        $tourivoBooking['customer_name'] ?? 'Traveler'
                    )
                );
                ?>
            </p>
            <p style="color: #475569; font-size: 14px;">
                <?php esc_html_e('Thank you for booking with us! We have successfully received your reservation details below:', 'tourivo'); ?>
            </p>

            <div class="info-card">
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Booking Code:', 'tourivo'); ?></span>
                    <span class="info-value" style="color: #0284c7;"><?php echo esc_html($tourivoBooking['booking_code'] ?? 'N/A'); ?></span>
                </div>
                <?php if (!empty($tourivoBooking['item_title'])) : ?>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Trip / Stay:', 'tourivo'); ?></span>
                        <span class="info-value"><?php echo esc_html($tourivoBooking['item_title']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($tourivoBooking['check_in'])) : ?>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Check-in Date:', 'tourivo'); ?></span>
                        <span class="info-value"><?php echo esc_html($tourivoBooking['check_in']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($tourivoBooking['check_out'])) : ?>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Check-out Date:', 'tourivo'); ?></span>
                        <span class="info-value"><?php echo esc_html($tourivoBooking['check_out']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Travelers / Guests:', 'tourivo'); ?></span>
                    <span class="info-value">
                        <?php echo esc_html((string)($tourivoBooking['adults'] ?? 1)); ?> <?php esc_html_e('Adult(s)', 'tourivo'); ?>
                        <?php if (!empty($tourivoBooking['children'])) : ?>
                            , <?php echo esc_html((string)$tourivoBooking['children']); ?> <?php esc_html_e('Child(ren)', 'tourivo'); ?>
                        <?php endif; ?>
                        <?php if (!empty($tourivoBooking['infants'])) : ?>
                            , <?php echo esc_html((string)$tourivoBooking['infants']); ?> <?php esc_html_e('Infant(s)', 'tourivo'); ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($tourivoBooking['customer_phone'])) : ?>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Contact Phone:', 'tourivo'); ?></span>
                        <span class="info-value"><?php echo esc_html($tourivoBooking['customer_phone']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($tourivoBooking['customer_notes'])) : ?>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Special Notes:', 'tourivo'); ?></span>
                        <span class="info-value" style="font-weight: 500;"><?php echo esc_html($tourivoBooking['customer_notes']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($tourivoBooking['raw_discount']) && (float)$tourivoBooking['raw_discount'] > 0) : ?>
                    <div class="info-row" style="color: #15803d;">
                        <span class="info-label"><?php esc_html_e('Discount:', 'tourivo'); ?></span>
                        <span class="info-value">-<?php echo esc_html($tourivoBooking['discount_amount']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($tourivoBooking['raw_tax']) && (float)$tourivoBooking['raw_tax'] > 0) : ?>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Tax / VAT:', 'tourivo'); ?></span>
                        <span class="info-value"><?php echo esc_html($tourivoBooking['tax_amount']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="total-box">
                <span class="total-label"><?php esc_html_e('Grand Total Amount', 'tourivo'); ?></span>
                <div class="total-val"><?php echo esc_html($tourivoBooking['total_amount'] ?? '$0.00'); ?></div>
            </div>

            <p style="color: #64748b; font-size: 13px; margin-top: 24px; text-align: center;">
                <?php esc_html_e('If you have questions about your itinerary or need to make adjustments, please reply directly to this email.', 'tourivo'); ?>
            </p>
        </div>

        <div class="footer">
            <p style="margin: 0 0 6px 0;">&copy; <?php echo esc_html(gmdate('Y')); ?> <strong><?php echo esc_html($tourivoSiteName); ?></strong>. <?php esc_html_e('All rights reserved.', 'tourivo'); ?></p>
            <p style="margin: 0;"><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_url(home_url('/')); ?></a></p>
        </div>
    </div>
</body>
</html>
