<?php
/**
 * Admin: New Traveler Trip Inquiry Email Template Part
 *
 * @package Tourivo
 * @var array  $inquiry
 * @var string $siteName
 * @var string $additionalContent
 */

if (!defined('ABSPATH')) {
    exit;
}

$tourivoInquiry     = isset($inquiry) && is_array($inquiry) ? $inquiry : [];
$tourivoExtra       = isset($additionalContent) && is_string($additionalContent) ? $additionalContent : '';
$adminInquiriesUrl  = admin_url('admin.php?page=tourivo-inquiries');
?>
<p style="font-size: 15px; margin-top: 0;">
    <strong><?php esc_html_e('Hello Administrator,', 'tourivo'); ?></strong>
</p>
<p style="color: #475569; font-size: 14px;">
    <?php esc_html_e('You have received a new traveler trip inquiry submitted from your website:', 'tourivo'); ?>
</p>

<div class="tourivo-info-card">
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Traveler Name:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><?php echo esc_html($tourivoInquiry['name'] ?? 'N/A'); ?></span>
    </div>
    <div class="tourivo-info-row">
        <span class="tourivo-info-label"><?php esc_html_e('Email Address:', 'tourivo'); ?></span>
        <span class="tourivo-info-value"><a href="mailto:<?php echo esc_attr($tourivoInquiry['email'] ?? ''); ?>"><?php echo esc_html($tourivoInquiry['email'] ?? 'N/A'); ?></a></span>
    </div>
    <?php if (!empty($tourivoInquiry['phone'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Phone / WhatsApp:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoInquiry['phone']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoInquiry['item_title'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Trip / Package:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoInquiry['item_title']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoInquiry['travel_date'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Expected Travel Date:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html($tourivoInquiry['travel_date']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoInquiry['guests'])) : ?>
        <div class="tourivo-info-row">
            <span class="tourivo-info-label"><?php esc_html_e('Number of Travelers:', 'tourivo'); ?></span>
            <span class="tourivo-info-value"><?php echo esc_html((string)$tourivoInquiry['guests']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($tourivoInquiry['message'])) : ?>
        <div style="padding-top: 10px; margin-top: 8px; border-top: 1px solid #e2e8f0;">
            <span class="tourivo-info-label" style="display: block; margin-bottom: 4px;"><?php esc_html_e('Inquiry / Question:', 'tourivo'); ?></span>
            <div style="color: #334155; font-size: 13.5px; line-height: 1.5; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                <?php echo nl2br(esc_html($tourivoInquiry['message'])); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($tourivoExtra)) : ?>
    <div style="margin: 20px 0; font-size: 14px; color: #334155;">
        <?php echo wp_kses_post($tourivoExtra); ?>
    </div>
<?php endif; ?>

<div style="text-align: center; margin-top: 24px;">
    <a href="<?php echo esc_url($adminInquiriesUrl); ?>" class="tourivo-btn">
        💬 <?php esc_html_e('View & Reply in Inquiries Manager', 'tourivo'); ?>
    </a>
</div>
