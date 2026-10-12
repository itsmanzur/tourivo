<?php
/**
 * Tourivo Booking Confirmation & Thank You Page Template
 *
 * @package Tourivo
 * @var object $booking
 * @var array<object> $items
 * @var string $voucherUrl
 * @var string $icsUrl
 * @var string $lookupUrl
 * @var string $offlinePaymentInstructions
 * @var bool   $enableDataLayer
 * @var string $primaryColor
 */

if (!defined('ABSPATH')) {
    exit;
}

$brandColor    = !empty($primaryColor) ? $primaryColor : '#0d9488';
$bookingStatus = (string) $booking->booking_status;
$paymentStatus = (string) $booking->payment_status;

$statusBadgeBg = ($bookingStatus === 'confirmed' || $bookingStatus === 'completed') ? '#dcfce7' : '#fef3c7';
$statusBadgeColor = ($bookingStatus === 'confirmed' || $bookingStatus === 'completed') ? '#15803d' : '#b45309';

$paymentBadgeBg = ($paymentStatus === 'paid') ? '#dcfce7' : '#f1f5f9';
$paymentBadgeColor = ($paymentStatus === 'paid') ? '#15803d' : '#475569';
?>

<!-- Robots & Referrer Protection -->
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">

<div class="tourivo-thankyou-wrapper" style="--trv-primary: <?php echo esc_attr($brandColor); ?>;">
    <!-- Header Banner -->
    <div class="tourivo-thankyou-header">
        <div class="tourivo-thankyou-icon" aria-hidden="true">✓</div>
        <h1 class="tourivo-thankyou-title"><?php esc_html_e('Thank You! Your Reservation is Placed', 'tourivo'); ?></h1>
        <p class="tourivo-thankyou-subtitle">
            <?php esc_html_e('A confirmation summary has been dispatched to your email address.', 'tourivo'); ?>
        </p>
    </div>

    <!-- Booking Overview Bar -->
    <div class="tourivo-thankyou-overview-bar">
        <div>
            <span class="tourivo-overview-label"><?php esc_html_e('Booking Reference', 'tourivo'); ?></span>
            <strong class="tourivo-overview-code">#<?php echo esc_html($booking->booking_code); ?></strong>
        </div>
        <div>
            <span class="tourivo-overview-label"><?php esc_html_e('Reservation Status', 'tourivo'); ?></span>
            <span class="tourivo-badge" style="background: <?php echo esc_attr($statusBadgeBg); ?>; color: <?php echo esc_attr($statusBadgeColor); ?>;">
                <?php echo esc_html(ucfirst($bookingStatus)); ?>
            </span>
        </div>
        <div>
            <span class="tourivo-overview-label"><?php esc_html_e('Payment Status', 'tourivo'); ?></span>
            <span class="tourivo-badge" style="background: <?php echo esc_attr($paymentBadgeBg); ?>; color: <?php echo esc_attr($paymentBadgeColor); ?>;">
                <?php echo esc_html(strtoupper((string) $booking->payment_method) . ' &bull; ' . ucfirst($paymentStatus)); ?>
            </span>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="tourivo-thankyou-grid">
        <!-- Left: Reservation Details & Items -->
        <div class="tourivo-thankyou-card">
            <h2 class="tourivo-card-title"><?php esc_html_e('Reservation Summary', 'tourivo'); ?></h2>

            <div class="tourivo-thankyou-items">
                <?php if (!empty($items)) : foreach ($items as $item) : 
                    $checkInFormatted  = !empty($item->check_in) ? gmdate('M d, Y', strtotime((string) $item->check_in)) : '—';
                    $checkOutFormatted = !empty($item->check_out) ? gmdate('M d, Y', strtotime((string) $item->check_out)) : '';
                    $dateRange = $checkOutFormatted ? "{$checkInFormatted} → {$checkOutFormatted}" : $checkInFormatted;
                    $typeLabel = ($item->item_type === 'room' || $item->item_type === 'hotel_room') ? __('Hotel Stay', 'tourivo') : __('Tour Experience', 'tourivo');
                ?>
                    <div class="tourivo-item-card">
                        <div class="tourivo-item-head">
                            <div>
                                <h3 class="tourivo-item-title"><?php echo esc_html($item->item_title); ?></h3>
                                <span class="tourivo-item-type-badge"><?php echo esc_html($typeLabel); ?></span>
                            </div>
                            <div class="tourivo-item-price">
                                <?php echo esc_html(\Tourivo\Support\Money::format((float) $item->total_price)); ?>
                            </div>
                        </div>
                        <div class="tourivo-item-meta">
                            <span>📅 <strong><?php esc_html_e('Dates:', 'tourivo'); ?></strong> <?php echo esc_html($dateRange); ?></span>
                            <?php if (!empty($item->time_slot) && $item->time_slot !== 'all_day') : ?>
                                <span>⏰ <strong><?php esc_html_e('Time Slot:', 'tourivo'); ?></strong> <?php echo esc_html($item->time_slot); ?></span>
                            <?php endif; ?>
                            <span>👥 <strong><?php esc_html_e('Party:', 'tourivo'); ?></strong> <?php
                                $partyParts = [];
                                if (!empty($item->adults_count)) {
                                    /* translators: %d: Number of adults */
                                    $partyParts[] = sprintf(_n('%d Adult', '%d Adults', (int) $item->adults_count, 'tourivo'), (int) $item->adults_count);
                                }
                                if (!empty($item->children_count)) {
                                    /* translators: %d: Number of children */
                                    $partyParts[] = sprintf(_n('%d Child', '%d Children', (int) $item->children_count, 'tourivo'), (int) $item->children_count);
                                }
                                if (!empty($item->infants_count)) {
                                    /* translators: %d: Number of infants */
                                    $partyParts[] = sprintf(_n('%d Infant', '%d Infants', (int) $item->infants_count, 'tourivo'), (int) $item->infants_count);
                                }
                                echo esc_html(!empty($partyParts) ? implode(', ', $partyParts) : sprintf(__('%d Travelers', 'tourivo'), (int) $item->quantity));
                            ?></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Customer Details -->
            <div class="tourivo-customer-details">
                <h3 class="tourivo-section-subtitle"><?php esc_html_e('Primary Booker Information', 'tourivo'); ?></h3>
                <p>👤 <strong><?php esc_html_e('Name:', 'tourivo'); ?></strong> <?php echo esc_html($booking->customer_name); ?></p>
                <p>✉️ <strong><?php esc_html_e('Email:', 'tourivo'); ?></strong> <?php echo esc_html($booking->customer_email); ?></p>
                <?php if (!empty($booking->customer_phone)) : ?>
                    <p>📞 <strong><?php esc_html_e('Phone:', 'tourivo'); ?></strong> <?php echo esc_html($booking->customer_phone); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Pricing Breakdown & Payment Instructions -->
        <div>
            <!-- Financial Breakdown Card -->
            <div class="tourivo-thankyou-card">
                <h2 class="tourivo-card-title"><?php esc_html_e('Payment Summary', 'tourivo'); ?></h2>

                <div class="tourivo-price-breakdown">
                    <?php if ((float) ($booking->tax_amount ?? 0) > 0 || (float) ($booking->discount_amount ?? 0) > 0) : ?>
                        <?php 
                        $subtotal = (float) $booking->total_amount - (float) ($booking->tax_amount ?? 0) + (float) ($booking->discount_amount ?? 0);
                        ?>
                        <div class="tourivo-breakdown-row">
                            <span><?php esc_html_e('Subtotal:', 'tourivo'); ?></span>
                            <span><?php echo esc_html(\Tourivo\Support\Money::format($subtotal)); ?></span>
                        </div>
                        <?php if ((float) ($booking->discount_amount ?? 0) > 0) : ?>
                            <div class="tourivo-breakdown-row discount">
                                <span><?php esc_html_e('Discount:', 'tourivo'); ?></span>
                                <span>-<?php echo esc_html(\Tourivo\Support\Money::format((float) $booking->discount_amount)); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ((float) ($booking->tax_amount ?? 0) > 0) : ?>
                            <div class="tourivo-breakdown-row">
                                <span><?php esc_html_e('Tax / VAT:', 'tourivo'); ?></span>
                                <span>+<?php echo esc_html(\Tourivo\Support\Money::format((float) $booking->tax_amount)); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="tourivo-breakdown-row total">
                        <span><?php esc_html_e('Total Amount:', 'tourivo'); ?></span>
                        <strong><?php echo esc_html(\Tourivo\Support\Money::format((float) $booking->total_amount)); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Offline Payment Details & Transaction Reference Submission -->
            <?php
            $isPaid = ($paymentStatus === 'paid');
            $paymentMethod = (string) ($booking->payment_method ?? 'offline');
            $paymentService = \Tourivo\Common\Container::getInstance()->has(\Tourivo\Services\PaymentService::class)
                ? \Tourivo\Common\Container::getInstance()->get(\Tourivo\Services\PaymentService::class)
                : new \Tourivo\Services\PaymentService();
            ?>
            <?php if (!$isPaid) : ?>
                <div class="tourivo-offline-instructions-card">
                    <?php if ($paymentMethod === 'bank_transfer' || empty($paymentMethod) || $paymentMethod === 'offline') : ?>
                        <?php echo $paymentService->getBankDetailsHtml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>

                    <?php if ($paymentMethod === 'cash_on_arrival') : ?>
                        <div class="tourivo-cash-card">
                            <h4 style="margin: 0 0 8px; color: #1e293b;">💵 <?php esc_html_e('Cash on Arrival / Pay at Check-in', 'tourivo'); ?></h4>
                            <p style="margin: 0; color: #475569; font-size: 13.5px; line-height: 1.5;">
                                <?php
                                $cashNote = (string) \Tourivo\Config\Config::get('cash_on_arrival_instructions', '');
                                echo !empty($cashNote)
                                    ? nl2br(esc_html($cashNote))
                                    : esc_html__('Your payment will be collected in cash or card upon arrival at our reception / tour counter.', 'tourivo');
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Transaction Reference Submission Form -->
                    <div class="tourivo-submit-reference-box" style="margin-top: 18px; padding-top: 16px; border-top: 1px dashed #cbd5e1;">
                        <h4 style="margin: 0 0 6px; font-size: 14.5px; color: #0f172a;">📝 <?php esc_html_e('Already completed your transfer or deposit?', 'tourivo'); ?></h4>
                        <p style="margin: 0 0 12px; font-size: 13px; color: #64748b;">
                            <?php esc_html_e('Submit your deposit slip, bank transaction reference, or mobile banking TrxID below for fast verification.', 'tourivo'); ?>
                        </p>

                        <?php if (!empty($booking->transaction_id)) : ?>
                            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #166534;">
                                <strong>✓ <?php esc_html_e('Submitted Reference:', 'tourivo'); ?></strong>
                                <code><?php echo esc_html($booking->transaction_id); ?></code>
                                <span style="font-size: 12px; margin-left: 6px; color: #15803d;"><?php esc_html_e('(Under review by accounts team)', 'tourivo'); ?></span>
                            </div>
                        <?php else : ?>
                            <form id="tourivo-submit-ref-form" style="display: flex; flex-direction: column; gap: 8px;">
                                <input type="hidden" name="action" value="tourivo_submit_payment_reference">
                                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('tourivo_frontend_nonce')); ?>">
                                <input type="hidden" name="booking_code" value="<?php echo esc_attr($booking->booking_code); ?>">
                                <?php if (!empty($token)) : ?>
                                    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                                <?php endif; ?>
                                <input type="hidden" name="customer_email" value="<?php echo esc_attr($booking->customer_email); ?>">

                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <input type="text" name="transaction_id" id="tourivo-ref-input" required placeholder="<?php esc_attr_e('Transaction Reference / Slip # / TrxID *', 'tourivo'); ?>" style="flex: 1; min-width: 200px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px;">
                                    <button type="submit" id="tourivo-submit-ref-btn" class="button button-primary" style="background: var(--trv-primary, #0d9488); border-color: var(--trv-primary, #0d9488); color: #fff; padding: 8px 18px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                                        <?php esc_html_e('Submit Reference', 'tourivo'); ?>
                                    </button>
                                </div>
                                <div id="tourivo-ref-alert" style="display: none; padding: 8px 12px; border-radius: 6px; font-size: 13px; margin-top: 4px;"></div>
                            </form>
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var form = document.getElementById('tourivo-submit-ref-form');
                                var btn = document.getElementById('tourivo-submit-ref-btn');
                                var alertEl = document.getElementById('tourivo-ref-alert');
                                if (!form) return;

                                form.addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    btn.disabled = true;
                                    btn.textContent = 'Submitting...';
                                    alertEl.style.display = 'none';

                                    var fd = new FormData(form);
                                    var ajaxEndpoint = (window.tourivoData && window.tourivoData.ajaxUrl) ? window.tourivoData.ajaxUrl : <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

                                    fetch(ajaxEndpoint, {
                                        method: 'POST',
                                        body: fd
                                    })
                                    .then(function(r) { return r.json(); })
                                    .then(function(res) {
                                        btn.disabled = false;
                                        btn.textContent = <?php echo wp_json_encode(__('Submit Reference', 'tourivo')); ?>;
                                        alertEl.style.display = 'block';

                                        if (res && res.success) {
                                            alertEl.style.background = '#dcfce7';
                                            alertEl.style.color = '#15803d';
                                            alertEl.textContent = res.data.message || 'Reference submitted successfully!';
                                            setTimeout(function() { window.location.reload(); }, 1200);
                                        } else {
                                            alertEl.style.background = '#fee2e2';
                                            alertEl.style.color = '#991b1b';
                                            alertEl.textContent = (res && res.data && res.data.message) ? res.data.message : 'Submission failed. Please try again.';
                                        }
                                    })
                                    .catch(function() {
                                        btn.disabled = false;
                                        btn.textContent = <?php echo wp_json_encode(__('Submit Reference', 'tourivo')); ?>;
                                        alertEl.style.display = 'block';
                                        alertEl.style.background = '#fee2e2';
                                        alertEl.style.color = '#991b1b';
                                        alertEl.textContent = 'Connection error. Please try again.';
                                    });
                                });
                            });
                            </script>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons Card -->
            <div class="tourivo-thankyou-card tourivo-actions-card">
                <a href="<?php echo esc_url($voucherUrl); ?>" target="_blank" rel="noopener" class="tourivo-btn tourivo-btn-voucher">
                    🖨️ <?php esc_html_e('Print Booking Voucher', 'tourivo'); ?>
                </a>

                <a href="<?php echo esc_url($icsUrl); ?>" class="tourivo-btn tourivo-btn-calendar">
                    📅 <?php esc_html_e('Add to Calendar (.ics)', 'tourivo'); ?>
                </a>

                <a href="<?php echo esc_url($lookupUrl); ?>" class="tourivo-btn tourivo-btn-track">
                    🔍 <?php esc_html_e('Track My Booking', 'tourivo'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- What Happens Next Guide -->
    <div class="tourivo-next-steps-section">
        <h2 class="tourivo-next-steps-title"><?php esc_html_e('What Happens Next?', 'tourivo'); ?></h2>
        <div class="tourivo-steps-grid">
            <div class="tourivo-step-box">
                <div class="tourivo-step-num">1</div>
                <h4><?php esc_html_e('Check Your Email', 'tourivo'); ?></h4>
                <p><?php esc_html_e('A full breakdown with printable access and reference details has been delivered to your inbox.', 'tourivo'); ?></p>
            </div>
            <div class="tourivo-step-box">
                <div class="tourivo-step-num">2</div>
                <h4><?php esc_html_e('Save Your Voucher', 'tourivo'); ?></h4>
                <p><?php esc_html_e('Download or print your official voucher to present upon arrival or departure check-in.', 'tourivo'); ?></p>
            </div>
            <div class="tourivo-step-box">
                <div class="tourivo-step-num">3</div>
                <h4><?php esc_html_e('Need Help or Changes?', 'tourivo'); ?></h4>
                <p><?php esc_html_e('Use our online lookup portal anytime to check live status or submit trip inquiries.', 'tourivo'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Conversion Tracking (DataLayer) -->
<?php if (!empty($enableDataLayer)) : ?>
<script>
(function() {
    var bCode = <?php echo wp_json_encode((string) $booking->booking_code); ?>;
    var bVal  = <?php echo (float) $booking->total_amount; ?>;
    var bCurr = <?php echo wp_json_encode((string) $booking->currency); ?>;
    var sKey  = 'tourivo_tracked_' + bCode;

    if (!window.sessionStorage || !window.sessionStorage.getItem(sKey)) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'tourivo_booking',
            booking_id: bCode,
            value: bVal,
            currency: bCurr
        });
        if (window.sessionStorage) {
            window.sessionStorage.setItem(sKey, '1');
        }
    }
})();
</script>
<?php endif; ?>

<style>
.tourivo-thankyou-wrapper {
    max-width: 960px;
    margin: 30px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1e293b;
}
.tourivo-thankyou-header {
    text-align: center;
    margin-bottom: 28px;
}
.tourivo-thankyou-icon {
    width: 60px;
    height: 60px;
    background: #10b981;
    color: #ffffff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 12px;
    box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
}
.tourivo-thankyou-title {
    font-size: 26px;
    font-weight: 700;
    margin: 0 0 6px;
    color: #0f172a;
}
.tourivo-thankyou-subtitle {
    color: #64748b;
    font-size: 15px;
    margin: 0;
}
.tourivo-thankyou-overview-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 24px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.tourivo-overview-label {
    display: block;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-bottom: 2px;
}
.tourivo-overview-code {
    font-size: 18px;
    color: var(--trv-primary, #0d9488);
}
.tourivo-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
}
.tourivo-thankyou-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}
@media (max-width: 768px) {
    .tourivo-thankyou-grid {
        grid-template-columns: 1fr;
    }
}
.tourivo-thankyou-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.tourivo-card-title {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 16px;
    color: #0f172a;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 10px;
}
.tourivo-item-card {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 12px;
}
.tourivo-item-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}
.tourivo-item-title {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 4px;
    color: #0f172a;
}
.tourivo-item-type-badge {
    font-size: 11px;
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
}
.tourivo-item-price {
    font-weight: 700;
    color: #0f172a;
}
.tourivo-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 13px;
    color: #64748b;
}
.tourivo-customer-details {
    border-top: 1px solid #f1f5f9;
    padding-top: 14px;
    margin-top: 14px;
}
.tourivo-section-subtitle {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
    margin: 0 0 8px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.tourivo-customer-details p {
    margin: 4px 0;
    font-size: 14px;
    color: #334155;
}
.tourivo-price-breakdown {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.tourivo-breakdown-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #64748b;
}
.tourivo-breakdown-row.discount {
    color: #15803d;
}
.tourivo-breakdown-row.total {
    border-top: 1px solid #e2e8f0;
    padding-top: 10px;
    margin-top: 6px;
    font-size: 17px;
    color: #0f172a;
    font-weight: 700;
}
.tourivo-offline-instructions-card {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 20px;
}
.tourivo-instructions-title {
    font-size: 15px;
    font-weight: 700;
    color: #166534;
    margin: 0 0 6px;
}
.tourivo-instructions-desc {
    font-size: 13px;
    color: #15803d;
    margin: 0 0 10px;
}
.tourivo-instructions-content {
    background: #ffffff;
    border: 1px solid #86efac;
    border-radius: 8px;
    padding: 12px 14px;
    font-family: inherit;
    font-size: 13px;
    color: #1e293b;
    line-height: 1.6;
}
.tourivo-actions-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.tourivo-btn {
    display: block;
    text-align: center;
    padding: 11px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s;
    box-sizing: border-box;
}
.tourivo-btn-voucher {
    background: var(--trv-primary, #0d9488);
    color: #ffffff;
}
.tourivo-btn-voucher:hover {
    filter: brightness(0.9);
    color: #ffffff;
}
.tourivo-btn-calendar {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.tourivo-btn-calendar:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.tourivo-btn-track {
    background: transparent;
    color: var(--trv-primary, #0d9488);
    border: 1px solid var(--trv-primary, #0d9488);
}
.tourivo-btn-track:hover {
    background: #f0fdfa;
}
.tourivo-next-steps-section {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px 24px;
}
.tourivo-next-steps-title {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 20px;
    text-align: center;
}
.tourivo-steps-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
}
@media (max-width: 640px) {
    .tourivo-steps-grid {
        grid-template-columns: 1fr;
    }
}
.tourivo-step-box {
    text-align: center;
    padding: 12px;
}
.tourivo-step-num {
    width: 36px;
    height: 36px;
    background: #e0f2fe;
    color: #0369a1;
    border-radius: 50%;
    font-weight: 700;
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
}
.tourivo-step-box h4 {
    margin: 0 0 6px;
    font-size: 15px;
    color: #0f172a;
}
.tourivo-step-box p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
}
</style>
