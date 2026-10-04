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
$tourivoDesc  = !empty($tourivoLookupDesc) ? $tourivoLookupDesc : __('Enter your booking reference code and email to check your reservation status and download your voucher.', 'tourivo');
$tourivoLookupToken = !empty($tourivoNonce) ? $tourivoNonce : wp_create_nonce('tourivo_lookup_nonce');
?>

<div class="tourivo-lookup-wrapper" id="tourivo-lookup-portal">
    <div class="tourivo-lookup-card">
        <div class="tourivo-lookup-header">
            <div class="tourivo-lookup-icon">🎫</div>
            <h3 class="tourivo-lookup-title"><?php echo esc_html($tourivoTitle); ?></h3>
            <p class="tourivo-lookup-desc"><?php echo esc_html($tourivoDesc); ?></p>
        </div>

        <form class="tourivo-lookup-form" id="tourivoLookupForm" onsubmit="return false;">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($tourivoLookupToken); ?>">

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

        <div id="tourivoLookupError" class="tourivo-lookup-alert error" style="display:none;"></div>

        <div id="tourivoLookupResult" class="tourivo-lookup-result" style="display:none;">
            <div class="tourivo-result-card">
                <div class="tourivo-result-header">
                    <div>
                        <span class="tourivo-result-label"><?php esc_html_e('Booking Reference', 'tourivo'); ?></span>
                        <h4 class="tourivo-result-code" id="resBookingCode">#TRV-0000</h4>
                        <small class="tourivo-result-date" id="resCreatedAt"></small>
                    </div>
                    <div class="tourivo-result-badges">
                        <span class="tourivo-badge" id="resBookingStatus"></span>
                        <span class="tourivo-badge" id="resPaymentStatus"></span>
                    </div>
                </div>

                <div class="tourivo-result-customer-info">
                    <p>👤 <strong><?php esc_html_e('Traveler:', 'tourivo'); ?></strong> <span id="resCustomerName"></span> (<span id="resCustomerEmail"></span>)</p>
                    <p>💳 <strong><?php esc_html_e('Payment Method:', 'tourivo'); ?></strong> <span id="resPaymentMethod"></span></p>
                </div>

                <div class="tourivo-result-items-box">
                    <h5 style="margin: 0 0 10px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;"><?php esc_html_e('Booked Package / Stays', 'tourivo'); ?></h5>
                    <div id="resItemsContainer"></div>
                </div>

                <div class="tourivo-result-footer">
                    <div class="tourivo-result-total">
                        <span><?php esc_html_e('Total Amount:', 'tourivo'); ?></span>
                        <strong id="resTotalAmount">$0.00</strong>
                    </div>
                    <div class="tourivo-result-actions">
                        <a href="#" id="resVoucherBtn" target="_blank" rel="noopener" class="tourivo-btn tourivo-btn-success">
                            🖨️ <?php esc_html_e('Print Booking Voucher', 'tourivo'); ?>
                        </a>
                    </div>
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
    transition: all 0.2s ease;
    background: #f8fafc;
}
.tourivo-lookup-input:focus {
    outline: none;
    border-color: #0d9488;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
}
.tourivo-lookup-actions {
    text-align: center;
}
.tourivo-lookup-submit {
    padding: 12px 28px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    background: #0d9488;
    color: #ffffff;
    border: none;
    transition: background 0.2s ease;
}
.tourivo-lookup-submit:hover {
    background: #0f766e;
}
.tourivo-lookup-alert {
    margin-top: 20px;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    text-align: center;
}
.tourivo-lookup-alert.error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.tourivo-lookup-result {
    margin-top: 28px;
    animation: tourivoFadeIn 0.3s ease-in-out;
}
@keyframes tourivoFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.tourivo-result-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
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
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
    font-weight: 600;
}
.tourivo-result-code {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 2px 0 0;
}
.tourivo-result-date {
    font-size: 12px;
    color: #64748b;
}
.tourivo-result-badges {
    display: flex;
    gap: 8px;
}
.tourivo-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #ffffff;
    display: inline-block;
}
.tourivo-result-customer-info {
    font-size: 14px;
    color: #475569;
    margin-bottom: 16px;
    line-height: 1.6;
}
.tourivo-result-customer-info p {
    margin: 0 0 4px;
}
.tourivo-result-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #f1f5f9;
    padding-top: 16px;
    margin-top: 16px;
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
.tourivo-btn-success {
    background: #10b981;
    color: #ffffff;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
}
.tourivo-btn-success:hover {
    background: #059669;
    color: #ffffff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('tourivoLookupForm');
    var btn = document.getElementById('tourivoLookupBtn');
    var errorBox = document.getElementById('tourivoLookupError');
    var resultBox = document.getElementById('tourivoLookupResult');

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

        var formData = new FormData();
        formData.append('action', 'tourivo_lookup_booking');
        formData.append('nonce', form.querySelector('input[name="nonce"]').value);
        formData.append('booking_code', code);
        formData.append('customer_email', email);

        var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.querySelector('.btn-text').style.display = 'inline';
            btn.querySelector('.btn-spinner').style.display = 'none';

            if (data.success && data.data) {
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

        document.getElementById('resCustomerName').textContent = b.customer_name;
        document.getElementById('resCustomerEmail').textContent = b.customer_email;
        document.getElementById('resPaymentMethod').textContent = b.payment_method;
        document.getElementById('resTotalAmount').textContent = b.total_amount;
        document.getElementById('resItemsContainer').innerHTML = b.items_html;

        var voucherBtn = document.getElementById('resVoucherBtn');
        if (voucherBtn && b.voucher_url) {
            voucherBtn.href = b.voucher_url;
        }

        resultBox.style.display = 'block';
    }
});
</script>
