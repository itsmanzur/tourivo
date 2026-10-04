<?php
/**
 * Tourivo Printable Booking Voucher & Invoice Template
 *
 * @package Tourivo
 * @var object $tourivoBooking
 * @var array $tourivoItems
 * @var string $tourivoCurrencySymbol
 * @var string $tourivoSiteName
 */

if (!defined('ABSPATH')) {
    exit;
}

use Tourivo\Support\Money;

$tourivoCurrency = !empty($tourivoCurrencySymbol) ? $tourivoCurrencySymbol : '$';
$tourivoSite     = !empty($tourivoSiteName) ? $tourivoSiteName : get_bloginfo('name');
$tourivoStatus   = ucfirst((string) $tourivoBooking->booking_status);
$tourivoPayment  = ucfirst((string) $tourivoBooking->payment_status);
$tourivoMethod   = ucwords(str_replace('_', ' ', (string) $tourivoBooking->payment_method));
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        /* translators: %s: Booking Reference Code */
        echo esc_html(sprintf(__('Booking Voucher #%s — %s', 'tourivo'), $tourivoBooking->booking_code, $tourivoSite)); 
    ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 40px 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .voucher-container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 40px;
            border: 1px solid #e2e8f0;
        }
        .voucher-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0d9488;
            padding-bottom: 24px;
            margin-bottom: 30px;
        }
        .agency-brand h1 {
            font-size: 26px;
            font-weight: 800;
            color: #0d9488;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        .agency-brand p {
            font-size: 13px;
            color: #64748b;
        }
        .voucher-meta {
            text-align: right;
        }
        .voucher-badge-title {
            display: inline-block;
            background: #f1f5f9;
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 4px 8px;
            border-radius: 4px;
            margin-bottom: 6px;
        }
        .voucher-code {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.05em;
        }
        .voucher-date {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
        }
        .info-block h3 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #0d9488;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .info-block p {
            margin-bottom: 4px;
            font-size: 14px;
        }
        .info-block strong {
            color: #334155;
        }
        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-confirmed, .status-completed, .status-paid {
            background: #dcfce7;
            color: #15803d;
        }
        .status-pending, .status-unpaid {
            background: #fef3c7;
            color: #b45309;
        }
        .status-cancelled, .status-failed {
            background: #fee2e2;
            color: #b91c1c;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table-items th {
            background: #f1f5f9;
            color: #475569;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 14px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .table-items td {
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            vertical-align: top;
        }
        .table-items tr:last-child td {
            border-bottom: 2px solid #0d9488;
        }
        .item-type-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            background: #f1f5f9;
            color: #475569;
            margin-top: 4px;
        }
        .financial-summary {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        .summary-box {
            width: 280px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            color: #64748b;
        }
        .summary-row.total {
            border-top: 2px solid #e2e8f0;
            padding-top: 10px;
            margin-top: 6px;
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
        }
        .voucher-footer {
            border-top: 1px dashed #cbd5e1;
            padding-top: 20px;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .voucher-footer strong {
            color: #64748b;
        }
        .action-bar {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
        }
        .btn-print {
            background: #0d9488;
            color: #ffffff;
        }
        .btn-print:hover {
            background: #0f766e;
        }
        .btn-close {
            background: #e2e8f0;
            color: #475569;
        }
        .btn-close:hover {
            background: #cbd5e1;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .voucher-container {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
            }
            .action-bar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <button class="btn btn-close" onclick="window.close();">← <?php esc_html_e('Close', 'tourivo'); ?></button>
        <button class="btn btn-print" onclick="window.print();">🖨️ <?php esc_html_e('Print Booking Voucher', 'tourivo'); ?></button>
    </div>

    <div class="voucher-container">
        <!-- Top Header -->
        <div class="voucher-top-bar">
            <div class="agency-brand">
                <h1><?php echo esc_html($tourivoSite); ?></h1>
                <p><?php esc_html_e('Official Travel & Accommodation Booking Confirmation Voucher', 'tourivo'); ?></p>
            </div>
            <div class="voucher-meta">
                <span class="voucher-badge-title"><?php esc_html_e('Booking Reference', 'tourivo'); ?></span>
                <div class="voucher-code">#<?php echo esc_html($tourivoBooking->booking_code); ?></div>
                <div class="voucher-date"><?php 
                    /* translators: %s: Date and time of booking */
                    echo esc_html(sprintf(__('Issued: %s', 'tourivo'), gmdate('M d, Y H:i', strtotime((string)$tourivoBooking->created_at)))); 
                ?></div>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-block">
                <h3>👤 <?php esc_html_e('Traveler Details', 'tourivo'); ?></h3>
                <p><strong><?php esc_html_e('Name:', 'tourivo'); ?></strong> <?php echo esc_html($tourivoBooking->customer_name); ?></p>
                <p><strong><?php esc_html_e('Email:', 'tourivo'); ?></strong> <?php echo esc_html($tourivoBooking->customer_email); ?></p>
                <?php if (!empty($tourivoBooking->customer_phone)) : ?>
                    <p><strong><?php esc_html_e('Phone:', 'tourivo'); ?></strong> <?php echo esc_html($tourivoBooking->customer_phone); ?></p>
                <?php endif; ?>
            </div>

            <div class="info-block">
                <h3>📋 <?php esc_html_e('Reservation Status', 'tourivo'); ?></h3>
                <p><strong><?php esc_html_e('Booking Status:', 'tourivo'); ?></strong> 
                    <span class="status-pill status-<?php echo esc_attr(strtolower($tourivoStatus)); ?>">
                        <?php echo esc_html($tourivoStatus); ?>
                    </span>
                </p>
                <p><strong><?php esc_html_e('Payment Status:', 'tourivo'); ?></strong> 
                    <span class="status-pill status-<?php echo esc_attr(strtolower($tourivoPayment)); ?>">
                        <?php echo esc_html($tourivoPayment); ?>
                    </span>
                </p>
                <p><strong><?php esc_html_e('Payment Method:', 'tourivo'); ?></strong> <?php echo esc_html($tourivoMethod); ?></p>
            </div>
        </div>

        <!-- Booked Line Items -->
        <table class="table-items">
            <thead>
                <tr>
                    <th><?php esc_html_e('Trip / Stay Description', 'tourivo'); ?></th>
                    <th><?php esc_html_e('Travel / Check-in Dates', 'tourivo'); ?></th>
                    <th style="text-align:center;"><?php esc_html_e('Qty / Pax', 'tourivo'); ?></th>
                    <th style="text-align:right;"><?php esc_html_e('Total', 'tourivo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tourivoItems)) : foreach ($tourivoItems as $tourivoItem) : 
                    $tourivoCheckIn  = !empty($tourivoItem->check_in) ? gmdate('M d, Y', strtotime((string)$tourivoItem->check_in)) : '—';
                    $tourivoCheckOut = !empty($tourivoItem->check_out) ? gmdate('M d, Y', strtotime((string)$tourivoItem->check_out)) : '';
                    $tourivoDates    = $tourivoCheckOut ? "{$tourivoCheckIn} → {$tourivoCheckOut}" : $tourivoCheckIn;
                    $tourivoTypeTag  = ($tourivoItem->item_type === 'room' || $tourivoItem->item_type === 'hotel_room') ? esc_html__('Hotel Room', 'tourivo') : esc_html__('Tour Package', 'tourivo');
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($tourivoItem->item_title); ?></strong>
                            <br><span class="item-type-tag"><?php echo esc_html($tourivoTypeTag); ?></span>
                        </td>
                        <td><?php echo esc_html($tourivoDates); ?></td>
                        <td style="text-align:center;"><?php echo esc_html((string)$tourivoItem->quantity); ?></td>
                        <td style="text-align:right; font-weight:600;">
                            <?php echo esc_html(Money::format((float) $tourivoItem->total_price, $tourivoCurrency)); ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <!-- Financial Summary -->
        <div class="financial-summary">
            <div class="summary-box">
                <div class="summary-row">
                    <span><?php esc_html_e('Subtotal:', 'tourivo'); ?></span>
                    <span><?php echo esc_html(Money::format((float) $tourivoBooking->total_amount, $tourivoCurrency)); ?></span>
                </div>
                <div class="summary-row total">
                    <span><?php esc_html_e('Total Paid / Due:', 'tourivo'); ?></span>
                    <span><?php echo esc_html(Money::format((float) $tourivoBooking->total_amount, $tourivoCurrency)); ?></span>
                </div>
            </div>
        </div>

        <!-- Voucher Footer & Instructions -->
        <div class="voucher-footer">
            <p><strong><?php esc_html_e('Important Instructions for Travelers:', 'tourivo'); ?></strong></p>
            <p>• <?php esc_html_e('Please present this voucher along with a valid government-issued photo ID during hotel check-in or tour boarding.', 'tourivo'); ?></p>
            <p>• <?php esc_html_e('For inquiries, date changes, or support, please contact our team with your Booking Reference Code.', 'tourivo'); ?></p>
            <p style="margin-top: 8px;"><em><?php esc_html_e('Thank you for traveling with us! Powered by Tourivo Travel Engine.', 'tourivo'); ?></em></p>
        </div>
    </div>

</body>
</html>
