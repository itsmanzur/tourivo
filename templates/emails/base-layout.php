<?php
/**
 * Tourivo Base Email Template Layout
 *
 * @package Tourivo
 * @var string $emailHeading
 * @var string $emailBodyContent
 * @var string $siteName
 * @var string $primaryColor
 * @var bool   $isTest
 */

if (!defined('ABSPATH')) {
    exit;
}

$tourivoSiteName     = isset($siteName) && is_string($siteName) ? $siteName : get_bloginfo('name');
$tourivoPrimaryColor = isset($primaryColor) && is_string($primaryColor) ? $primaryColor : '#0d9488';
$tourivoHeading      = isset($emailHeading) && is_string($emailHeading) ? $emailHeading : '';
$tourivoIsTest       = !empty($isTest);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($tourivoHeading ?: $tourivoSiteName); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Noto Sans Bengali', 'SolaimanLipi', 'Bangla';
            line-height: 1.6;
            color: #1e293b;
            margin: 0;
            padding: 20px;
            background-color: #f1f5f9;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        .tourivo-email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .tourivo-email-header {
            background-color: <?php echo esc_attr($tourivoPrimaryColor); ?>;
            color: #ffffff;
            padding: 28px 24px;
            text-align: center;
        }
        .tourivo-email-header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
        }
        .tourivo-email-header p {
            margin: 6px 0 0 0;
            opacity: 0.92;
            font-size: 13.5px;
            color: #ffffff;
        }
        .tourivo-email-content {
            padding: 28px 24px;
        }
        .tourivo-info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 18px;
            margin: 20px 0;
        }
        .tourivo-info-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
        }
        .tourivo-info-row:last-child {
            border-bottom: none;
        }
        .tourivo-info-label {
            color: #64748b;
            font-weight: 600;
        }
        .tourivo-info-value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }
        .tourivo-btn {
            display: inline-block;
            background-color: <?php echo esc_attr($tourivoPrimaryColor); ?>;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 14px;
            margin-top: 16px;
            text-align: center;
        }
        .tourivo-test-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 800;
            display: inline-block;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tourivo-email-footer {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            padding: 20px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .tourivo-email-footer a {
            color: <?php echo esc_attr($tourivoPrimaryColor); ?>;
            text-decoration: none;
        }
        .tourivo-highlight-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 14px 16px;
            margin: 16px 0;
            color: #166534;
            font-size: 13.5px;
        }
        .tourivo-warning-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 14px 16px;
            margin: 16px 0;
            color: #92400e;
            font-size: 13.5px;
        }
    </style>
</head>
<body>
    <div class="tourivo-email-wrapper">
        <div class="tourivo-email-header">
            <?php if ($tourivoIsTest) : ?>
                <div><span class="tourivo-test-badge"><?php esc_html_e('TEST NOTIFICATION', 'tourivo'); ?></span></div>
            <?php endif; ?>
            <h1><?php echo esc_html($tourivoHeading); ?></h1>
            <p><?php echo esc_html($tourivoSiteName); ?></p>
        </div>

        <div class="tourivo-email-content">
            <?php echo $emailBodyContent; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

        <div class="tourivo-email-footer">
            <p style="margin: 0 0 6px 0;">&copy; <?php echo esc_html(gmdate('Y')); ?> <strong><?php echo esc_html($tourivoSiteName); ?></strong>. <?php esc_html_e('All rights reserved.', 'tourivo'); ?></p>
            <p style="margin: 0;"><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_url(home_url('/')); ?></a></p>
        </div>
    </div>
</body>
</html>
