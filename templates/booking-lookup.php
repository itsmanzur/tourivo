<?php
/**
 * Tourivo Booking Lookup & Tracking Template
 *
 * @package Tourivo
 * @var string $tourivoLookupTitle
 * @var string $tourivoLookupDesc
 * @var string $tourivoNonce
 */

if (!defined('ABSPATH')) {
    exit;
}

$tourivoTitle = !empty($tourivoLookupTitle) ? $tourivoLookupTitle : __('Track Your Booking', 'tourivo');
$tourivoDesc  = !empty($tourivoLookupDesc) ? $tourivoLookupDesc : __('Enter your booking reference code and email to check your reservation status, download your voucher, or request cancellation.', 'tourivo');
$tourivoLookupToken = !empty($tourivoNonce) ? $tourivoNonce : wp_create_nonce('tourivo_lookup_nonce');
?>

<div class="tourivo-lookup-wrapper" id="tourivo-lookup-portal">
    <div class="tourivo-lookup-card">
        <div class="tourivo-lookup-header">
            <div class="tourivo-lookup-icon" aria-hidden="true">🎫</div>
            <h2 class="tourivo-lookup-title"><?php echo esc_html($tourivoTitle); ?></h2>
            <p class="tourivo-lookup-desc"><?php echo esc_html($tourivoDesc); ?></p>
        </div>

        <form class="tourivo-lookup-form" id="tourivoLookupForm" onsubmit="return false;">
            <input type="hidden" name="nonce" id="trv_lookup_nonce" value="<?php echo esc_attr($tourivoLookupToken); ?>">
            <!-- Honeypot anti-spam -->
            <input type="text" name="trv_hp_check" id="trv_hp_check" style="display:none;" tabindex="-1" autocomplete="off">

            <div class="tourivo-lookup-grid">
                <div class="tourivo-lookup-field">
                    <label for="trv_lookup_code"><strong><?php esc_html_e('Booking Reference Code', 'tourivo'); ?></strong> <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="trv_lookup_code" name="booking_code" class="tourivo-lookup-input" placeholder="<?php esc_attr_e('e.g. TRV-A1B2C3D4', 'tourivo'); ?>" required autocomplete="off">
                </div>

                <div class="tourivo-lookup-field">
                    <label for="trv_lookup_email"><strong><?php esc_html_e('Customer Email Address', 'tourivo'); ?></strong> <span style="color:#ef4444;">*</span></label>
                    <input type="email" id="trv_lookup_email" name="customer_email" class="tourivo-lookup-input" placeholder="<?php esc_attr_e('your-email@domain.com', 'tourivo'); ?>" required>
                </div>
            </div>

            <div class="tourivo-lookup-actions">
                <button type="submit" class="tourivo-btn tourivo-btn-primary tourivo-lookup-submit" id="tourivoLookupBtn">
                    <span class="btn-text">🔍 <?php esc_html_e('Track Reservation', 'tourivo'); ?></span>
                    <span class="btn-spinner" style="display:none;">⏳ <?php esc_html_e('Searching...', 'tourivo'); ?></span>
                </button>
            </div>
        </form>

        <div id="tourivoLookupError" class="tourivo-lookup-alert error" style="display:none;" aria-live="polite"></div>

        <div id="tourivoLookupResult" class="tourivo-lookup-result" style="display:none;" aria-live="polite">
            <div class="tourivo-result-card">
                <div class="tourivo-result-header">
                    <div>
                        <span class="tourivo-result-label"><?php esc_html_e('Booking Reference', 'tourivo'); ?></span>
                        <h3 class="tourivo-result-code" id="resBookingCode">#TRV-0000</h3>
                        <small class="tourivo-result-date" id="resCreatedAt"></small>
                    </div>
                    <div class="tourivo-result-badges">
                        <span class="tourivo-badge" id="resBookingStatus"></span>
                        <span class="tourivo-badge" id="resPaymentStatus"></span>
                        <span class="tourivo-badge" id="resCancelRequestedBadge" style="display:none; background:#fee2e2; color:#991b1b;">
                            🚨 <?php esc_html_e('Cancel Requested', 'tourivo'); ?>
                        </span>
                    </div>
                </div>

                <div class="tourivo-result-customer-info">
                    <p>👤 <strong><?php esc_html_e('Traveler:', 'tourivo'); ?></strong> <span id="resCustomerName"></span> (<span id="resCustomerEmail"></span>)</p>
                    <p>💳 <strong><?php esc_html_e('Payment Method:', 'tourivo'); ?></strong> <span id="resPaymentMethod"></span></p>
                </div>

                <div class="tourivo-result-items-box">
                    <h4 style="margin: 0 0 10px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;"><?php esc_html_e('Booked Package / Stays', 'tourivo'); ?></h4>
                    <div id="resItemsContainer"></div>
                </div>

                <div class="tourivo-result-footer">
                    <div class="tourivo-result-total">
                        <span><?php esc_html_e('Total Amount:', 'tourivo'); ?></span>
                        <strong id="resTotalAmount">$0.00</strong>
                    </div>
                    <div class="tourivo-result-actions" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <a href="#" id="resVoucherBtn" target="_blank" rel="noopener" class="tourivo-btn tourivo-btn-success">
                            🖨️ <?php esc_html_e('Print Booking Voucher', 'tourivo'); ?>
                        </a>
                        <button type="button" id="resCancelBtn" class="tourivo-btn tourivo-btn-cancel" style="display:none;">
                            🚨 <?php esc_html_e('Cancel Booking', 'tourivo'); ?>
                        </button>
                    </div>
                </div>

                <!-- Cancellation Request Box -->
                <div id="tourivoCancelBox" style="display:none; margin-top:20px; padding:16px; background:#fff1f2; border:1px solid #fecdd3; border-radius:8px;">
                    <h4 style="margin:0 0 8px; color:#9f1239; font-size:15px; font-weight:700;">
                        <?php esc_html_e('Submit Cancellation Request', 'tourivo'); ?>
                    </h4>
                    <p style="margin:0 0 12px; font-size:13px; color:#881337;">
                        <?php esc_html_e('Are you sure you wish to cancel this reservation? Please provide a reason below:', 'tourivo'); ?>
                    </p>
                    <textarea id="trv_cancel_reason" rows="3" class="tourivo-lookup-input" placeholder="<?php esc_attr_e('Reason for cancellation (optional)...', 'tourivo'); ?>" style="margin-bottom:12px; font-size:13px;"></textarea>
                    <div style="display:flex; gap:10px;">
                        <button type="button" id="tourivoConfirmCancelBtn" class="tourivo-btn tourivo-btn-danger">
                            <?php esc_html_e('Confirm Cancellation', 'tourivo'); ?>
                        </button>
                        <button type="button" id="tourivoDismissCancelBtn" class="tourivo-btn" style="background:#f1f5f9; color:#334155;">
                            <?php esc_html_e('Keep My Booking', 'tourivo'); ?>
                        </button>
                    </div>
                    <div id="tourivoCancelAlert" style="display:none; margin-top:10px; font-size:13px; font-weight:600;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tourivo-lookup-wrapper {
    max-width: 760px;
    margin: 30px auto;
    font-family: inherit;
}
.tourivo-lookup-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08), 0 4px 6px -2px rgba(15, 23, 42, 0.03);
    border: 1px solid #e2e8f0;
}
.tourivo-lookup-header {
    text-align: center;
    margin-bottom: 24px;
}
.tourivo-lookup-icon {
    font-size: 42px;
    line-height: 1;
    margin-bottom: 8px;
}
.tourivo-lookup-title {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 8px;
}
.tourivo-lookup-desc {
    font-size: 14px;
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}
.tourivo-lookup-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 600px) {
    .tourivo-lookup-grid {
        grid-template-columns: 1fr;
    }
}
.tourivo-lookup-field label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    color: #334155;
}
.tourivo-lookup-input {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 15px;
    transition: border-color 0.2s;
}
.tourivo-lookup-input:focus {
    outline: none;
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
}
.tourivo-lookup-actions {
    text-align: center;
}
.tourivo-btn {
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    padding: 12px 24px;
    border-radius: 8px;
    transition: all 0.2s;
}
.tourivo-btn-primary {
    background: #0d9488;
    color: #ffffff;
}
.tourivo-btn-primary:hover {
    background: #0f766e;
}
.tourivo-btn-success {
    background: #10b981;
    color: #ffffff;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.tourivo-btn-success:hover {
    background: #059669;
    color: #ffffff;
}
.tourivo-btn-cancel {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}
.tourivo-btn-cancel:hover {
    background: #fecdd3;
}
.tourivo-btn-danger {
    background: #e11d48;
    color: #ffffff;
}
.tourivo-btn-danger:hover {
    background: #be123c;
}
.tourivo-lookup-alert {
    padding: 14px;
    border-radius: 8px;
    margin-top: 20px;
    font-size: 14px;
    line-height: 1.5;
}
.tourivo-lookup-alert.error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fee2e2;
}
.tourivo-lookup-result {
    margin-top: 28px;
}
.tourivo-result-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
}
.tourivo-result-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 16px;
    margin-bottom: 16px;
}
.tourivo-result-label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
}
.tourivo-result-code {
    font-size: 20px;
    color: #0f172a;
    margin: 2px 0 4px;
}
.tourivo-result-date {
    color: #64748b;
}
.tourivo-result-badges {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.tourivo-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
}
.tourivo-result-customer-info {
    font-size: 14px;
    color: #334155;
    margin-bottom: 16px;
}
.tourivo-result-customer-info p {
    margin: 4px 0;
}
.tourivo-result-items-box {
    margin-bottom: 20px;
}
.tourivo-result-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #f1f5f9;
    padding-top: 16px;
    margin-top: 16px;
    flex-wrap: wrap;
    gap: 12px;
}
.tourivo-result-total span {
    font-size: 13px;
    color: #64748b;
    display: block;
}
.tourivo-result-total strong {
    font-size: 22px;
    color: #0f172a;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('tourivoLookupForm');
    var btn = document.getElementById('tourivoLookupBtn');
    var errorBox = document.getElementById('tourivoLookupError');
    var resultBox = document.getElementById('tourivoLookupResult');
    var cancelBtn = document.getElementById('resCancelBtn');
    var cancelBox = document.getElementById('tourivoCancelBox');
    var confirmCancelBtn = document.getElementById('tourivoConfirmCancelBtn');
    var dismissCancelBtn = document.getElementById('tourivoDismissCancelBtn');
    var cancelAlert = document.getElementById('tourivoCancelAlert');

    var currentBooking = null;
    var trvAjaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

    // POST to admin-ajax with the lookup nonce. A cached page may carry a stale nonce, so on a
    // `nonce_expired` reply fetch a fresh one once and transparently retry.
    function trvPost(formData, retried) {
        formData.set('nonce', document.getElementById('trv_lookup_nonce').value);

        return fetch(trvAjaxUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (retried || !data || data.success || !data.data || data.data.code !== 'nonce_expired') {
                return data;
            }

            var nonceRequest = new FormData();
            nonceRequest.append('action', 'tourivo_get_lookup_nonce');

            return fetch(trvAjaxUrl, { method: 'POST', body: nonceRequest })
                .then(function(res) { return res.json(); })
                .then(function(fresh) {
                    if (fresh && fresh.success && fresh.data && fresh.data.nonce) {
                        document.getElementById('trv_lookup_nonce').value = fresh.data.nonce;
                        return trvPost(formData, true);
                    }
                    return data;
                });
        });
    }

    if (!form || !btn) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var codeInput = document.getElementById('trv_lookup_code');
        var emailInput = document.getElementById('trv_lookup_email');

        var code = codeInput ? codeInput.value.trim() : '';
        var email = emailInput ? emailInput.value.trim() : '';

        if (!code || !email) {
            showError('<?php echo esc_js(__('Please enter both your Booking Reference Code and Email Address.', 'tourivo')); ?>');
            return;
        }

        // UI Loading state
        btn.disabled = true;
        btn.querySelector('.btn-text').style.display = 'none';
        btn.querySelector('.btn-spinner').style.display = 'inline';
        errorBox.style.display = 'none';
        resultBox.style.display = 'none';
        if (cancelBox) cancelBox.style.display = 'none';

        var formData = new FormData();
        formData.append('action', 'tourivo_lookup_booking');
        formData.append('nonce', document.getElementById('trv_lookup_nonce').value);
        formData.append('trv_hp_check', document.getElementById('trv_hp_check').value);
        formData.append('booking_code', code);
        formData.append('customer_email', email);

        trvPost(formData)
        .then(function(data) {
            btn.disabled = false;
            btn.querySelector('.btn-text').style.display = 'inline';
            btn.querySelector('.btn-spinner').style.display = 'none';

            if (data.success && data.data) {
                currentBooking = data.data;
                renderResult(data.data);
            } else {
                showError((data.data && data.data.message) ? data.data.message : '<?php echo esc_js(__('Could not retrieve reservation details.', 'tourivo')); ?>');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.querySelector('.btn-text').style.display = 'inline';
            btn.querySelector('.btn-spinner').style.display = 'none';
            showError('<?php echo esc_js(__('Connection error. Please try again.', 'tourivo')); ?>');
        });
    });

    if (cancelBtn && cancelBox) {
        cancelBtn.addEventListener('click', function() {
            cancelBox.style.display = cancelBox.style.display === 'none' ? 'block' : 'none';
            if (cancelAlert) cancelAlert.style.display = 'none';
        });
    }

    if (dismissCancelBtn && cancelBox) {
        dismissCancelBtn.addEventListener('click', function() {
            cancelBox.style.display = 'none';
        });
    }

    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', function() {
            if (!currentBooking) return;

            var reasonInput = document.getElementById('trv_cancel_reason');
            var reason = reasonInput ? reasonInput.value.trim() : '';

            confirmCancelBtn.disabled = true;
            confirmCancelBtn.textContent = '<?php echo esc_js(__('Processing...', 'tourivo')); ?>';
            if (cancelAlert) cancelAlert.style.display = 'none';

            var formData = new FormData();
            formData.append('action', 'tourivo_request_booking_cancellation');
            formData.append('booking_code', currentBooking.booking_code);
            formData.append('customer_email', currentBooking.customer_email);
            formData.append('reason', reason);
            formData.append('trv_hp_check', document.getElementById('trv_hp_check').value);

            trvPost(formData)
            .then(function(data) {
                confirmCancelBtn.disabled = false;
                confirmCancelBtn.textContent = '<?php echo esc_js(__('Confirm Cancellation', 'tourivo')); ?>';

                if (cancelAlert) {
                    cancelAlert.style.display = 'block';
                    if (data.success) {
                        cancelAlert.style.color = '#15803d';
                        cancelAlert.textContent = '✓ ' + (data.data.message || '<?php echo esc_js(__('Request submitted successfully.', 'tourivo')); ?>');

                        if (data.data.mode === 'cancelled') {
                            var statusBadge = document.getElementById('resBookingStatus');
                            if (statusBadge) {
                                statusBadge.textContent = 'Cancelled';
                                statusBadge.style.backgroundColor = '#ef4444';
                            }
                            if (cancelBtn) cancelBtn.style.display = 'none';
                        } else {
                            var reqBadge = document.getElementById('resCancelRequestedBadge');
                            if (reqBadge) reqBadge.style.display = 'inline-block';
                            if (cancelBtn) cancelBtn.disabled = true;
                        }
                    } else {
                        cancelAlert.style.color = '#b91c1c';
                        cancelAlert.textContent = '✕ ' + ((data.data && data.data.message) ? data.data.message : '<?php echo esc_js(__('Failed to submit cancellation request.', 'tourivo')); ?>');
                    }
                }
            })
            .catch(function() {
                confirmCancelBtn.disabled = false;
                confirmCancelBtn.textContent = '<?php echo esc_js(__('Confirm Cancellation', 'tourivo')); ?>';
                if (cancelAlert) {
                    cancelAlert.style.display = 'block';
                    cancelAlert.style.color = '#b91c1c';
                    cancelAlert.textContent = '✕ <?php echo esc_js(__('Connection error. Please try again.', 'tourivo')); ?>';
                }
            });
        });
    }

    function showError(msg) {
        if (!errorBox) return;
        errorBox.textContent = msg;
        errorBox.style.display = 'block';
    }

    function renderResult(b) {
        document.getElementById('resBookingCode').textContent = '#' + b.booking_code;
        document.getElementById('resCreatedAt').textContent = '<?php echo esc_js(__('Booked on:', 'tourivo')); ?> ' + b.created_at;
        
        var statusBadge = document.getElementById('resBookingStatus');
        statusBadge.textContent = b.booking_status;
        statusBadge.style.backgroundColor = b.status_color;

        var payBadge = document.getElementById('resPaymentStatus');
        payBadge.textContent = b.payment_status;
        payBadge.style.backgroundColor = b.payment_color;

        var reqBadge = document.getElementById('resCancelRequestedBadge');
        if (reqBadge) {
            reqBadge.style.display = b.cancel_requested ? 'inline-block' : 'none';
        }

        document.getElementById('resCustomerName').textContent = b.customer_name;
        document.getElementById('resCustomerEmail').textContent = b.customer_email;
        document.getElementById('resPaymentMethod').textContent = b.payment_method;
        document.getElementById('resTotalAmount').textContent = b.total_amount;
        document.getElementById('resItemsContainer').innerHTML = b.items_html;

        var voucherBtn = document.getElementById('resVoucherBtn');
        if (voucherBtn && b.voucher_url) {
            voucherBtn.href = b.voucher_url;
        }

        if (cancelBtn) {
            cancelBtn.style.display = (b.allow_cancel && b.booking_status !== 'Cancelled') ? 'inline-block' : 'none';
            if (b.cancel_requested) {
                cancelBtn.disabled = true;
            }
        }

        resultBox.style.display = 'block';
    }
});
</script>
