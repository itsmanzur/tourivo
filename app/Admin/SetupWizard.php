<?php

declare(strict_types=1);

namespace Tourivo\Admin;

use Tourivo\Config\Config;
use Tourivo\Database\Seeder;
use Tourivo\Shortcodes\CurrencySwitcherShortcode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SetupWizard
 *
 * Interactive Onboarding & Setup Wizard for Tourivo.
 * Guides site administrators through currency settings, core pages generation, and 1-click demo data import.
 *
 * @package Tourivo\Admin
 */
class SetupWizard
{
    /**
     * Render the setup wizard page.
     *
     * @return void
     */
    public static function render(): void
    {
        if (!current_user_can('manage_tourivo_settings')) {
            wp_die(esc_html__('You do not have permission to access the setup wizard.', 'tourivo'));
        }

        $currency       = Config::get('currency', 'USD');
        $currencySymbol = Config::get('currency_symbol', '$');
        $currencyPos    = Config::get('currency_position', 'left');
        $fromName       = Config::get('email_from_name', get_bloginfo('name'));
        $notifyEmail    = Config::get('email_notification_address', get_option('admin_email'));
        $currencies     = CurrencySwitcherShortcode::getCurrencies();
        $nonce          = wp_create_nonce('tourivo_wizard_nonce');
        ?>
        <div class="tourivo-wizard-body">
            <div class="tourivo-wizard-container">
                <!-- Wizard Logo Header -->
                <div class="tourivo-wizard-header">
                    <div class="tourivo-wizard-brand">
                        <span class="brand-icon">🌴</span>
                        <h1 class="brand-title">Tourivo</h1>
                    </div>
                    <p class="brand-tagline"><?php esc_html_e('Quick Setup & Configuration Wizard', 'tourivo'); ?></p>
                </div>

                <!-- Step Navigation Progress Bar -->
                <div class="tourivo-wizard-steps">
                    <div class="wizard-step active" id="step-nav-1">
                        <span class="step-num">1</span>
                        <span class="step-label"><?php esc_html_e('Store & Currency', 'tourivo'); ?></span>
                    </div>
                    <div class="wizard-step" id="step-nav-2">
                        <span class="step-num">2</span>
                        <span class="step-label"><?php esc_html_e('Core Pages', 'tourivo'); ?></span>
                    </div>
                    <div class="wizard-step" id="step-nav-3">
                        <span class="step-num">3</span>
                        <span class="step-label"><?php esc_html_e('Demo Content', 'tourivo'); ?></span>
                    </div>
                    <div class="wizard-step" id="step-nav-4">
                        <span class="step-num">4</span>
                        <span class="step-label"><?php esc_html_e('Ready!', 'tourivo'); ?></span>
                    </div>
                </div>

                <!-- Step 1: Store & Currency -->
                <div class="wizard-content-card" id="wizard-card-1">
                    <div class="card-intro">
                        <h2>⚙️ <?php esc_html_e('Agency & Currency Setup', 'tourivo'); ?></h2>
                        <p><?php esc_html_e('Configure your business name and default pricing currency.', 'tourivo'); ?></p>
                    </div>

                    <form id="wizardFormStep1" onsubmit="return false;">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

                        <div class="wizard-form-group">
                            <label for="wz_agency_name"><strong><?php esc_html_e('Agency / Site Name', 'tourivo'); ?></strong></label>
                            <input type="text" id="wz_agency_name" name="agency_name" class="wizard-input" value="<?php echo esc_attr($fromName); ?>" required>
                        </div>

                        <div class="wizard-form-group">
                            <label for="wz_notify_email"><strong><?php esc_html_e('Booking Notification Email', 'tourivo'); ?></strong></label>
                            <input type="email" id="wz_notify_email" name="notify_email" class="wizard-input" value="<?php echo esc_attr($notifyEmail); ?>" required>
                            <small class="wizard-hint"><?php esc_html_e('New booking alerts and customer inquiry notifications will be sent here.', 'tourivo'); ?></small>
                        </div>

                        <div class="wizard-form-row">
                            <div class="wizard-form-group">
                                <label for="wz_currency_code"><strong><?php esc_html_e('Base Currency', 'tourivo'); ?></strong></label>
                                <select id="wz_currency_code" name="currency_code" class="wizard-select">
                                    <?php foreach ($currencies as $code => $info) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" data-symbol="<?php echo esc_attr($info['symbol']); ?>" <?php selected($currency, $code); ?>>
                                            <?php echo esc_html($code . ' (' . $info['symbol'] . ') — ' . $info['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="wizard-form-group">
                                <label for="wz_currency_symbol"><strong><?php esc_html_e('Currency Symbol', 'tourivo'); ?></strong></label>
                                <input type="text" id="wz_currency_symbol" name="currency_symbol" class="wizard-input" value="<?php echo esc_attr($currencySymbol); ?>" required>
                            </div>

                            <div class="wizard-form-group">
                                <label for="wz_currency_pos"><strong><?php esc_html_e('Symbol Position', 'tourivo'); ?></strong></label>
                                <select id="wz_currency_pos" name="currency_position" class="wizard-select">
                                    <option value="left" <?php selected($currencyPos, 'left'); ?>><?php esc_html_e('Left ($100)', 'tourivo'); ?></option>
                                    <option value="right" <?php selected($currencyPos, 'right'); ?>><?php esc_html_e('Right (100$)', 'tourivo'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="wizard-footer">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=tourivo')); ?>" class="wizard-skip-link"><?php esc_html_e('Skip Setup', 'tourivo'); ?></a>
                            <button type="button" class="wizard-btn wizard-btn-primary" id="btnSaveStep1">
                                <?php esc_html_e('Continue to Pages →', 'tourivo'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Step 2: Core Default Pages -->
                <div class="wizard-content-card" id="wizard-card-2" style="display:none;">
                    <div class="card-intro">
                        <h2>📄 <?php esc_html_e('Create Essential Pages', 'tourivo'); ?></h2>
                        <p><?php esc_html_e('Select the default pages you would like Tourivo to automatically generate on your website.', 'tourivo'); ?></p>
                    </div>

                    <form id="wizardFormStep2" onsubmit="return false;">
                        <div class="wizard-checklist">
                            <label class="wizard-check-item">
                                <input type="checkbox" name="pages[]" value="tours" checked>
                                <div class="check-text">
                                    <strong>🌴 <?php esc_html_e('Tours Directory Page', 'tourivo'); ?></strong>
                                    <span><?php esc_html_e('Creates "/tours" displaying the interactive tour packages grid.', 'tourivo'); ?></span>
                                </div>
                            </label>

                            <label class="wizard-check-item">
                                <input type="checkbox" name="pages[]" value="hotels" checked>
                                <div class="check-text">
                                    <strong>🏨 <?php esc_html_e('Hotels & Stays Page', 'tourivo'); ?></strong>
                                    <span><?php esc_html_e('Creates "/hotels" displaying boutique hotels, rooms, and resorts.', 'tourivo'); ?></span>
                                </div>
                            </label>

                            <label class="wizard-check-item">
                                <input type="checkbox" name="pages[]" value="search" checked>
                                <div class="check-text">
                                    <strong>🔍 <?php esc_html_e('Search & Filter Portal', 'tourivo'); ?></strong>
                                    <span><?php esc_html_e('Creates "/search-trips" with live price sliders, destination & star filters.', 'tourivo'); ?></span>
                                </div>
                            </label>

                            <label class="wizard-check-item">
                                <input type="checkbox" name="pages[]" value="track" checked>
                                <div class="check-text">
                                    <strong>🎫 <?php esc_html_e('Track My Booking & Voucher Portal', 'tourivo'); ?></strong>
                                    <span><?php esc_html_e('Creates "/track-booking" for travelers to track status & print vouchers.', 'tourivo'); ?></span>
                                </div>
                            </label>

                            <label class="wizard-check-item">
                                <input type="checkbox" name="pages[]" value="wishlist" checked>
                                <div class="check-text">
                                    <strong>❤️ <?php esc_html_e('Traveler Wishlist Page', 'tourivo'); ?></strong>
                                    <span><?php esc_html_e('Creates "/wishlist" allowing travelers to save favorite trips.', 'tourivo'); ?></span>
                                </div>
                            </label>
                        </div>

                        <div class="wizard-footer">
                            <button type="button" class="wizard-btn wizard-btn-secondary" onclick="TourivoWizard.goToStep(1);">← <?php esc_html_e('Back', 'tourivo'); ?></button>
                            <button type="button" class="wizard-btn wizard-btn-primary" id="btnCreatePages">
                                <?php esc_html_e('Generate Pages & Next →', 'tourivo'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Step 3: Demo Content Importer -->
                <div class="wizard-content-card" id="wizard-card-3" style="display:none;">
                    <div class="card-intro">
                        <h2>🚀 <?php esc_html_e('Import Realistic Sample Trips & Hotels', 'tourivo'); ?></h2>
                        <p><?php esc_html_e('Populate sample data (Bali Beach, Swiss Alps, Kyoto Heritage, Grand Oceanfront Resort) to immediately test booking flows.', 'tourivo'); ?></p>
                    </div>

                    <div class="demo-import-box">
                        <div class="demo-preview-icons">
                            <span>🏝️</span>
                            <span>🏔️</span>
                            <span>🏯</span>
                            <span>🏨</span>
                        </div>
                        <h4 style="margin: 0 0 6px; color:#0f172a; font-size:16px;"><?php esc_html_e('Ready to install 3 Featured Tours, 2 Boutique Hotels & 4 Room Types.', 'tourivo'); ?></h4>
                        <p style="margin:0; font-size:13px; color:#64748b;"><?php esc_html_e('Includes complete day-by-day itineraries, high-res photos, inclusions, FAQs, and realistic pricing.', 'tourivo'); ?></p>
                    </div>

                    <div id="demoImportProgress" style="display:none; text-align:center; padding: 20px;">
                        <span class="spinner is-active" style="float:none; margin:0 auto 10px;"></span>
                        <p style="margin:0; font-weight:600; color:#0d9488;"><?php esc_html_e('Importing sample tours, hotels, rooms, and destinations...', 'tourivo'); ?></p>
                    </div>

                    <div class="wizard-footer">
                        <button type="button" class="wizard-btn wizard-btn-secondary" onclick="TourivoWizard.goToStep(4);"><?php esc_html_e('Skip Demo Import', 'tourivo'); ?></button>
                        <button type="button" class="wizard-btn wizard-btn-success" id="btnImportDemo">
                            ✨ <?php esc_html_e('Import Demo Data Now', 'tourivo'); ?>
                        </button>
                    </div>
                </div>

                <!-- Step 4: Ready / Completion Screen -->
                <div class="wizard-content-card" id="wizard-card-4" style="display:none; text-align:center;">
                    <div style="font-size: 54px; margin-bottom: 12px;">🎉</div>
                    <h2 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 10px;"><?php esc_html_e('Your Booking Engine is Ready!', 'tourivo'); ?></h2>
                    <p style="font-size: 15px; color: #64748b; max-width: 500px; margin: 0 auto 24px;">
                        <?php esc_html_e('Tourivo has been successfully configured. You are ready to accept bookings, manage travel itineraries, and publish hotel rooms.', 'tourivo'); ?>
                    </p>

                    <div class="wizard-quick-links-grid" id="wizardQuickLinks">
                        <a href="<?php echo esc_url(home_url('/tours')); ?>" target="_blank" class="quick-link-box">
                            <span class="ql-icon">🌴</span>
                            <strong><?php esc_html_e('View Tours Page', 'tourivo'); ?></strong>
                            <small><?php esc_html_e('Browse travel packages', 'tourivo'); ?></small>
                        </a>
                        <a href="<?php echo esc_url(home_url('/hotels')); ?>" target="_blank" class="quick-link-box">
                            <span class="ql-icon">🏨</span>
                            <strong><?php esc_html_e('View Hotels Page', 'tourivo'); ?></strong>
                            <small><?php esc_html_e('Browse rooms & stays', 'tourivo'); ?></small>
                        </a>
                        <a href="<?php echo esc_url(home_url('/track-booking')); ?>" target="_blank" class="quick-link-box">
                            <span class="ql-icon">🎫</span>
                            <strong><?php esc_html_e('Track Booking Portal', 'tourivo'); ?></strong>
                            <small><?php esc_html_e('Customer self-service', 'tourivo'); ?></small>
                        </a>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=tourivo_tour')); ?>" class="quick-link-box">
                            <span class="ql-icon">➕</span>
                            <strong><?php esc_html_e('Create First Tour', 'tourivo'); ?></strong>
                            <small><?php esc_html_e('Add your custom itinerary', 'tourivo'); ?></small>
                        </a>
                    </div>

                    <div style="margin-top: 32px; border-top: 1px solid #e2e8f0; padding-top: 24px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=tourivo')); ?>" class="wizard-btn wizard-btn-primary" style="padding: 12px 32px; font-size: 16px;">
                            🚀 <?php esc_html_e('Go to Tourivo Dashboard', 'tourivo'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .tourivo-wizard-body {
            max-width: 820px;
            margin: 40px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .tourivo-wizard-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .tourivo-wizard-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .brand-icon {
            font-size: 32px;
        }
        .brand-title {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.02em;
        }
        .brand-tagline {
            font-size: 15px;
            color: #64748b;
            margin: 6px 0 0;
        }
        .tourivo-wizard-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 24px;
            position: relative;
        }
        .tourivo-wizard-steps::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 40px;
            right: 40px;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        .wizard-step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        .step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            color: #64748b;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 6px;
            transition: all 0.2s;
        }
        .wizard-step.active .step-num {
            background: #0d9488;
            border-color: #0d9488;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.2);
        }
        .wizard-step.completed .step-num {
            background: #10b981;
            border-color: #10b981;
            color: #ffffff;
        }
        .step-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }
        .wizard-step.active .step-label {
            color: #0f172a;
        }
        .wizard-content-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
        }
        .card-intro {
            margin-bottom: 24px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 16px;
        }
        .card-intro h2 {
            margin: 0 0 6px;
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .card-intro p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
        }
        .wizard-form-group {
            margin-bottom: 18px;
        }
        .wizard-form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            color: #334155;
        }
        .wizard-input, .wizard-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: #f8fafc;
        }
        .wizard-input:focus, .wizard-select:focus {
            outline: none;
            border-color: #0d9488;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
        }
        .wizard-form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 14px;
        }
        .wizard-hint {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: #94a3b8;
        }
        .wizard-checklist {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }
        .wizard-check-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s;
        }
        .wizard-check-item:hover {
            border-color: #0d9488;
            background: #f0fdfa;
        }
        .wizard-check-item input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            accent-color: #0d9488;
        }
        .check-text strong {
            display: block;
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .check-text span {
            font-size: 12px;
            color: #64748b;
        }
        .demo-import-box {
            text-align: center;
            padding: 30px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
            margin-bottom: 24px;
        }
        .demo-preview-icons {
            font-size: 36px;
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 12px;
        }
        .wizard-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 28px;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
        .wizard-btn {
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .wizard-btn-primary {
            background: #0d9488;
            color: #ffffff;
        }
        .wizard-btn-primary:hover {
            background: #0f766e;
            color: #ffffff;
        }
        .wizard-btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .wizard-btn-secondary:hover {
            background: #e2e8f0;
        }
        .wizard-btn-success {
            background: #10b981;
            color: #ffffff;
        }
        .wizard-btn-success:hover {
            background: #059669;
            color: #ffffff;
        }
        .wizard-skip-link {
            font-size: 13px;
            color: #94a3b8;
            text-decoration: none;
        }
        .wizard-skip-link:hover {
            color: #64748b;
            text-decoration: underline;
        }
        .wizard-quick-links-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 20px;
            text-align: left;
        }
        .quick-link-box {
            display: block;
            padding: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .quick-link-box:hover {
            border-color: #0d9488;
            background: #f0fdfa;
            transform: translateY(-2px);
        }
        .quick-link-box .ql-icon {
            font-size: 24px;
            display: block;
            margin-bottom: 6px;
        }
        .quick-link-box strong {
            display: block;
            font-size: 14px;
            color: #0f172a;
        }
        .quick-link-box small {
            font-size: 12px;
            color: #64748b;
        }
        </style>

        <script>
        var TourivoWizard = {
            currentStep: 1,
            nonce: '<?php echo esc_js($nonce); ?>',
            ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',

            goToStep: function(step) {
                for (var i = 1; i <= 4; i++) {
                    var card = document.getElementById('wizard-card-' + i);
                    var nav = document.getElementById('step-nav-' + i);
                    if (card) card.style.display = (i === step) ? 'block' : 'none';
                    if (nav) {
                        nav.classList.remove('active');
                        if (i < step) nav.classList.add('completed');
                        if (i === step) nav.classList.add('active');
                    }
                }
                this.currentStep = step;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            var currSelect = document.getElementById('wz_currency_code');
            var currSymbol = document.getElementById('wz_currency_symbol');
            if (currSelect && currSymbol) {
                currSelect.addEventListener('change', function() {
                    var opt = currSelect.options[currSelect.selectedIndex];
                    var symbol = opt.getAttribute('data-symbol');
                    if (symbol) currSymbol.value = symbol;
                });
            }

            // Step 1 Save
            var btn1 = document.getElementById('btnSaveStep1');
            if (btn1) {
                btn1.addEventListener('click', function() {
                    btn1.disabled = true;
                    btn1.textContent = '<?php echo esc_js(__('Saving...', 'tourivo')); ?>';

                    var formData = new FormData();
                    formData.append('action', 'tourivo_wizard_save_step1');
                    formData.append('nonce', TourivoWizard.nonce);
                    formData.append('agency_name', document.getElementById('wz_agency_name').value);
                    formData.append('notify_email', document.getElementById('wz_notify_email').value);
                    formData.append('currency', document.getElementById('wz_currency_code').value);
                    formData.append('currency_symbol', document.getElementById('wz_currency_symbol').value);
                    formData.append('currency_position', document.getElementById('wz_currency_pos').value);

                    fetch(TourivoWizard.ajaxUrl, { method: 'POST', body: formData })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        btn1.disabled = false;
                        btn1.textContent = '<?php echo esc_js(__('Continue to Pages →', 'tourivo')); ?>';
                        if (data.success) {
                            TourivoWizard.goToStep(2);
                        } else {
                            alert(data.data && data.data.message ? data.data.message : 'Error saving settings.');
                        }
                    })
                    .catch(function() {
                        btn1.disabled = false;
                        btn1.textContent = '<?php echo esc_js(__('Continue to Pages →', 'tourivo')); ?>';
                        alert('Connection error.');
                    });
                });
            }

            // Step 2 Create Pages
            var btn2 = document.getElementById('btnCreatePages');
            if (btn2) {
                btn2.addEventListener('click', function() {
                    btn2.disabled = true;
                    btn2.textContent = '<?php echo esc_js(__('Creating Pages...', 'tourivo')); ?>';

                    var checkedPages = [];
                    document.querySelectorAll('input[name="pages[]"]:checked').forEach(function(cb) {
                        checkedPages.push(cb.value);
                    });

                    var formData = new FormData();
                    formData.append('action', 'tourivo_wizard_create_pages');
                    formData.append('nonce', TourivoWizard.nonce);
                    formData.append('pages', JSON.stringify(checkedPages));

                    fetch(TourivoWizard.ajaxUrl, { method: 'POST', body: formData })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        btn2.disabled = false;
                        btn2.textContent = '<?php echo esc_js(__('Generate Pages & Next →', 'tourivo')); ?>';
                        TourivoWizard.goToStep(3);
                    })
                    .catch(function() {
                        btn2.disabled = false;
                        btn2.textContent = '<?php echo esc_js(__('Generate Pages & Next →', 'tourivo')); ?>';
                        TourivoWizard.goToStep(3);
                    });
                });
            }

            // Step 3 Import Demo Data
            var btn3 = document.getElementById('btnImportDemo');
            var progBox = document.getElementById('demoImportProgress');
            if (btn3) {
                btn3.addEventListener('click', function() {
                    btn3.disabled = true;
                    if (progBox) progBox.style.display = 'block';

                    var formData = new FormData();
                    formData.append('action', 'tourivo_wizard_import_demo');
                    formData.append('nonce', TourivoWizard.nonce);

                    fetch(TourivoWizard.ajaxUrl, { method: 'POST', body: formData })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (progBox) progBox.style.display = 'none';
                        TourivoWizard.goToStep(4);
                    })
                    .catch(function() {
                        if (progBox) progBox.style.display = 'none';
                        TourivoWizard.goToStep(4);
                    });
                });
            }
        });
        </script>
        <?php
    }

    /**
     * AJAX handler: Save step 1 settings.
     */
    public static function handleSaveStep1(): void
    {
        check_ajax_referer('tourivo_wizard_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_settings')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tourivo')], 403);
        }

        $agencyName     = isset($_POST['agency_name']) ? sanitize_text_field(wp_unslash((string) $_POST['agency_name'])) : get_bloginfo('name');
        $notifyEmail    = isset($_POST['notify_email']) ? sanitize_email(wp_unslash((string) $_POST['notify_email'])) : get_option('admin_email');
        $currency       = isset($_POST['currency']) ? sanitize_text_field(wp_unslash((string) $_POST['currency'])) : 'USD';
        $currencySymbol = isset($_POST['currency_symbol']) ? sanitize_text_field(wp_unslash((string) $_POST['currency_symbol'])) : '$';
        $currencyPos    = isset($_POST['currency_position']) ? sanitize_text_field(wp_unslash((string) $_POST['currency_position'])) : 'left';

        $currentSettings = get_option('tourivo_settings', Config::getDefaults());
        $merged = array_merge($currentSettings, [
            'email_from_name'            => $agencyName,
            'email_notification_address' => $notifyEmail,
            'currency'                   => $currency,
            'currency_symbol'            => $currencySymbol,
            'currency_position'          => $currencyPos,
        ]);

        update_option('tourivo_settings', $merged);
        wp_send_json_success(['message' => __('Settings saved successfully.', 'tourivo')]);
    }

    /**
     * AJAX handler: Create default core pages.
     */
    public static function handleCreatePages(): void
    {
        check_ajax_referer('tourivo_wizard_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_settings')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tourivo')], 403);
        }

        $rawPages = isset($_POST['pages']) ? sanitize_text_field(wp_unslash((string) $_POST['pages'])) : '[]';
        $requested = json_decode($rawPages, true);
        if (!is_array($requested)) {
            $requested = ['tours', 'hotels', 'search', 'track', 'wishlist'];
        }

        $pageDefinitions = [
            'tours'    => [
                'title'   => 'Tours & Trips',
                'slug'    => 'tours',
                'content' => '<!-- wp:shortcode -->[tourivo_tours columns="3" count="9"]<!-- /wp:shortcode -->',
            ],
            'hotels'   => [
                'title'   => 'Hotels & Accommodations',
                'slug'    => 'hotels',
                'content' => '<!-- wp:shortcode -->[tourivo_hotels columns="3" count="9"]<!-- /wp:shortcode -->',
            ],
            'search'   => [
                'title'   => 'Search Trips & Stays',
                'slug'    => 'search-trips',
                'content' => '<!-- wp:shortcode -->[tourivo_filter_search type="tour"]<!-- /wp:shortcode -->',
            ],
            'track'    => [
                'title'   => 'Track My Booking',
                'slug'    => 'track-booking',
                'content' => '<!-- wp:shortcode -->[tourivo_booking_lookup]<!-- /wp:shortcode -->',
            ],
            'wishlist' => [
                'title'   => 'My Wishlist',
                'slug'    => 'wishlist',
                'content' => '<!-- wp:shortcode -->[tourivo_wishlist]<!-- /wp:shortcode -->',
            ],
        ];

        $created = [];
        foreach ($requested as $key) {
            if (!isset($pageDefinitions[$key])) {
                continue;
            }

            $def = $pageDefinitions[$key];
            $existing = get_page_by_path($def['slug']);

            if (!$existing) {
                $pageId = wp_insert_post([
                    'post_title'   => $def['title'],
                    'post_name'    => $def['slug'],
                    'post_content' => $def['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ]);
                if ($pageId && !is_wp_error($pageId)) {
                    $created[$key] = get_permalink($pageId);
                }
            } else {
                $created[$key] = get_permalink($existing->ID);
            }
        }

        wp_send_json_success(['pages' => $created]);
    }

    /**
     * AJAX handler: Run demo data seeder.
     */
    public static function handleImportDemo(): void
    {
        check_ajax_referer('tourivo_wizard_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_settings')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tourivo')], 403);
        }

        $result = Seeder::run();
        wp_send_json_success($result);
    }
}
