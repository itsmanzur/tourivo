<?php
/**
 * Interactive Booking Panel Template
 *
 * @package Tourivo
 * @var int $itemId
 * @var string $itemType
 * @var float $basePrice
 * @var int $maxGuests
 * @var string $currencySymbol
 */

use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

$tourivoItemId         = isset($tourivoItemId) ? (int) $tourivoItemId : (isset($itemId) ? (int) $itemId : (int) get_the_ID());
$tourivoPostType       = get_post_type($tourivoItemId);
$tourivoItemType       = isset($tourivoItemType) ? (string) $tourivoItemType : (($tourivoPostType === 'tourivo_room') ? 'room' : 'tour');
$tourivoCurrencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');

if ($tourivoItemType === 'tour') {
    $tourivoTour          = new \Tourivo\Models\Tour($tourivoItemId);
    $tourivoBasePrice     = $tourivoTour->getActivePrice();
    $tourivoMinGuests     = $tourivoTour->getMinGuests();
    $tourivoMaxGuests     = $tourivoTour->getMaxGuests() > 0 ? $tourivoTour->getMaxGuests() : 20;
    $tourivoChildAgeLabel = $tourivoTour->getChildAgeLabel() ?: __('Age 2-11', 'tourivo');
    $tourivoInfantsFree   = $tourivoTour->isInfantsFree();
    $tourivoMaxRooms      = 1;
} else {
    $tourivoRoom          = new \Tourivo\Models\Room($tourivoItemId);
    $tourivoBasePrice     = $tourivoRoom->getNightlyPrice();
    $tourivoMinGuests     = 1;
    $tourivoMaxGuests     = $tourivoRoom->getMaxGuests() > 0 ? $tourivoRoom->getMaxGuests() : 4;
    $tourivoChildAgeLabel = __('Age 2-11', 'tourivo');
    $tourivoInfantsFree   = true;
    $tourivoMaxRooms      = $tourivoRoom->getQuantity() > 0 ? $tourivoRoom->getQuantity() : 10;
}
?>

<div class="tourivo-booking-panel" id="tourivo-booking-panel-<?php echo esc_attr((string) $tourivoItemId); ?>" data-item-id="<?php echo esc_attr((string) $tourivoItemId); ?>" data-item-type="<?php echo esc_attr($tourivoItemType); ?>" data-unit-price="<?php echo esc_attr((string) $tourivoBasePrice); ?>" data-currency="<?php echo esc_attr($tourivoCurrencySymbol); ?>" data-min-guests="<?php echo esc_attr((string) $tourivoMinGuests); ?>">
    <div class="panel-header">
        <div class="panel-price-box">
            <span class="price-label"><?php esc_html_e('Price:', 'tourivo'); ?></span>
            <span class="price-amount" id="tourivo-live-price"><?php echo esc_html(Money::format($tourivoBasePrice)); ?></span>
            <span class="price-suffix"><?php echo ($tourivoItemType === 'tour') ? esc_html__('/ person', 'tourivo') : esc_html__('/ night', 'tourivo'); ?></span>
        </div>
        <div class="availability-status" id="tourivo-avail-status">
            <span class="status-badge status-check"><?php esc_html_e('Instant Confirmation', 'tourivo'); ?></span>
        </div>
    </div>

    <form class="tourivo-booking-form" id="tourivo-booking-form">
        <!-- Anti-Spam Honeypot -->
        <div style="display:none !important; visibility:hidden !important; position:absolute; left:-9999px;">
            <input type="text" name="tourivo_hp_check" value="" tabindex="-1" autocomplete="off">
        </div>

        <!-- Date Selection -->
        <div class="form-section">
            <label class="section-label"><?php echo ($tourivoItemType === 'tour') ? esc_html__('Select Tour Date *', 'tourivo') : esc_html__('Check-in Date *', 'tourivo'); ?></label>
            <div class="input-with-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
                <input type="date" name="check_in" id="tourivo-check-in" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" value="<?php echo esc_attr(wp_date('Y-m-d', strtotime('+1 day'))); ?>" required>
            </div>
        </div>

        <?php if ($tourivoItemType === 'room') : ?>
            <div class="form-section">
                <label class="section-label"><?php esc_html_e('Check-out Date *', 'tourivo'); ?></label>
                <div class="input-with-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <input type="date" name="check_out" id="tourivo-check-out" min="<?php echo esc_attr(wp_date('Y-m-d', strtotime('+1 day'))); ?>" value="<?php echo esc_attr(wp_date('Y-m-d', strtotime('+2 days'))); ?>" required>
                </div>
            </div>

            <!-- Room Units Counter -->
            <div class="form-section">
                <label class="section-label"><?php esc_html_e('Number of Rooms *', 'tourivo'); ?></label>
                <div class="guest-counter-row">
                    <div class="guest-counter-item" style="width: 100%;">
                        <div class="guest-type">
                            <strong><?php esc_html_e('Rooms', 'tourivo'); ?></strong>
                            <small><?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: Maximum rooms available */
                                    __('Up to %d available', 'tourivo'),
                                    $tourivoMaxRooms
                                )
                            );
                            ?></small>
                        </div>
                        <div class="counter-controls">
                            <button type="button" class="counter-btn minus-btn" data-target="rooms-count">-</button>
                            <input type="number" name="rooms" id="rooms-count" value="1" min="1" max="<?php echo esc_attr((string) $tourivoMaxRooms); ?>" readonly>
                            <button type="button" class="counter-btn plus-btn" data-target="rooms-count">+</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Guest Counters -->
        <div class="form-section">
            <label class="section-label"><?php esc_html_e('Guests / Travelers', 'tourivo'); ?></label>
            <div class="guest-counter-row">
                <div class="guest-counter-item">
                    <div class="guest-type">
                        <strong><?php esc_html_e('Adults', 'tourivo'); ?></strong>
                        <small><?php esc_html_e('Age 12+', 'tourivo'); ?></small>
                    </div>
                    <div class="counter-controls">
                        <button type="button" class="counter-btn minus-btn" data-target="adults-count">-</button>
                        <input type="number" name="adults" id="adults-count" value="<?php echo esc_attr((string) max(1, $tourivoMinGuests)); ?>" min="1" max="<?php echo esc_attr((string) $tourivoMaxGuests); ?>" readonly>
                        <button type="button" class="counter-btn plus-btn" data-target="adults-count">+</button>
                    </div>
                </div>

                <div class="guest-counter-item">
                    <div class="guest-type">
                        <strong><?php esc_html_e('Children', 'tourivo'); ?></strong>
                        <small><?php echo esc_html($tourivoChildAgeLabel); ?></small>
                    </div>
                    <div class="counter-controls">
                        <button type="button" class="counter-btn minus-btn" data-target="children-count">-</button>
                        <input type="number" name="children" id="children-count" value="0" min="0" max="<?php echo esc_attr((string) $tourivoMaxGuests); ?>" readonly>
                        <button type="button" class="counter-btn plus-btn" data-target="children-count">+</button>
                    </div>
                </div>

                <div class="guest-counter-item">
                    <div class="guest-type">
                        <strong><?php esc_html_e('Infants', 'tourivo'); ?></strong>
                        <small><?php echo $tourivoInfantsFree ? esc_html__('Under 2 (Free)', 'tourivo') : esc_html__('Under 2', 'tourivo'); ?></small>
                    </div>
                    <div class="counter-controls">
                        <button type="button" class="counter-btn minus-btn" data-target="infants-count">-</button>
                        <input type="number" name="infants" id="infants-count" value="0" min="0" max="<?php echo esc_attr((string) $tourivoMaxGuests); ?>" readonly>
                        <button type="button" class="counter-btn plus-btn" data-target="infants-count">+</button>
                    </div>
                </div>
            </div>
        </div>

        <?php do_action('tourivo_booking_panel_after_guests', $tourivoItemId, $tourivoItemType); ?>

        <!-- Live Price Breakdown -->
        <div class="price-breakdown-box" id="tourivo-price-breakdown" aria-live="polite">
            <div class="breakdown-items" id="tourivo-breakdown-items">
                <div class="breakdown-row">
                    <span class="breakdown-desc" id="breakdown-calc-label"><?php echo esc_html(Money::format($tourivoBasePrice)); ?> &times; 1 <?php echo ($tourivoItemType === 'room') ? esc_html__('Night', 'tourivo') : esc_html__('Guest', 'tourivo'); ?></span>
                    <span class="breakdown-val" id="breakdown-total-val"><?php echo esc_html(Money::format($tourivoBasePrice)); ?></span>
                </div>
            </div>
            <div class="breakdown-row" id="breakdown-subtotal-row" style="display:none;">
                <span class="breakdown-desc"><?php esc_html_e('Subtotal', 'tourivo'); ?></span>
                <span class="breakdown-val" id="breakdown-subtotal-val"><?php echo esc_html(Money::format($tourivoBasePrice)); ?></span>
            </div>
            <div class="breakdown-row" id="breakdown-discount-row" style="display:none; color:#15803d;">
                <span class="breakdown-desc"><?php esc_html_e('Discount', 'tourivo'); ?></span>
                <span class="breakdown-val" id="breakdown-discount-val">-<?php echo esc_html(Money::format(0)); ?></span>
            </div>
            <div class="breakdown-row" id="breakdown-tax-row" style="display:none;">
                <span class="breakdown-desc" id="breakdown-tax-label"><?php esc_html_e('Tax', 'tourivo'); ?></span>
                <span class="breakdown-val" id="breakdown-tax-val"><?php echo esc_html(Money::format(0)); ?></span>
            </div>
            <div class="breakdown-row total-row">
                <span><strong><?php esc_html_e('Total Amount', 'tourivo'); ?></strong></span>
                <span class="total-amount-val" id="live-grand-total"><strong><?php echo esc_html(Money::format($tourivoBasePrice)); ?></strong></span>
            </div>
        </div>

        <!-- Checkout Customer Details Accordion -->
        <div class="checkout-fields-box" id="tourivo-customer-details">
            <h4 class="checkout-fields-title"><?php esc_html_e('Lead Traveler Information', 'tourivo'); ?></h4>
            <div class="field-row">
                <input type="text" name="customer_name" placeholder="<?php esc_attr_e('Full Name *', 'tourivo'); ?>" required>
            </div>
            <div class="field-row">
                <input type="email" name="customer_email" placeholder="<?php esc_attr_e('Email Address *', 'tourivo'); ?>" required>
            </div>
            <div class="field-row">
                <input type="tel" name="customer_phone" placeholder="<?php esc_attr_e('Phone / WhatsApp *', 'tourivo'); ?>" required>
            </div>
            <div class="field-row">
                <textarea name="customer_notes" rows="2" placeholder="<?php esc_attr_e('Special requests or notes...', 'tourivo'); ?>"></textarea>
            </div>
            <?php if (\Tourivo\Config\Config::get('require_consent', false)) : ?>
                <div class="field-row tourivo-consent-row" style="margin-top:10px; display:flex; align-items:flex-start; gap:8px;">
                    <input type="checkbox" name="consent" id="tourivo-booking-consent-<?php echo esc_attr((string) $tourivoItemId); ?>" value="1" required style="margin-top:3px;">
                    <label for="tourivo-booking-consent-<?php echo esc_attr((string) $tourivoItemId); ?>" style="font-size:13px; line-height:1.4;">
                        <?php echo \Tourivo\Support\Privacy::getRenderedConsentLabel(); ?> <span class="required" style="color:#ef4444;">*</span>
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <?php do_action('tourivo_booking_panel_before_submit', $tourivoItemId, $tourivoItemType); ?>

        <!-- Action Submit -->
        <button type="submit" class="tourivo-btn tourivo-btn-primary tourivo-btn-block" id="tourivo-submit-btn">
            <span class="dashicons dashicons-lock"></span> <?php esc_html_e('Book Now (Pay Offline)', 'tourivo'); ?>
        </button>

        <!-- Trip Inquiry CTA -->
        <div class="tourivo-inquiry-cta">
            <button type="button" class="tourivo-btn-inquiry" id="tourivo-open-inquiry-modal">
                💬 <?php esc_html_e('Have questions? Ask a Travel Specialist', 'tourivo'); ?>
            </button>
        </div>

        <div class="panel-alert" id="tourivo-panel-alert" style="display: none;"></div>
    </form>

    <!-- Trip Inquiry Modal -->
    <div id="tourivo-inquiry-modal" class="tourivo-modal-wrap" style="display: none;">
        <div class="tourivo-modal-overlay"></div>
        <div class="tourivo-modal-content">
            <div class="modal-header">
                <h3>💬 <?php esc_html_e('Ask an Expert / Custom Trip Inquiry', 'tourivo'); ?></h3>
                <button type="button" class="close-inquiry-modal">&times;</button>
            </div>
            <form id="tourivo-inquiry-form">
                <!-- Anti-Spam Honeypot -->
                <div style="display:none !important; visibility:hidden !important; position:absolute; left:-9999px;">
                    <input type="text" name="tourivo_inq_hp" value="" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="action" value="tourivo_submit_inquiry">
                <input type="hidden" name="item_id" value="<?php echo esc_attr((string) $tourivoItemId); ?>">
                <input type="hidden" name="item_type" value="<?php echo esc_attr($tourivoItemType); ?>">
                <div class="modal-body">
                    <p class="modal-subtitle"><?php esc_html_e('Have special requests or group questions? Send your inquiry directly to our travel specialists.', 'tourivo'); ?></p>
                    <div class="inquiry-field-row">
                        <input type="text" name="customer_name" placeholder="<?php esc_attr_e('Your Full Name *', 'tourivo'); ?>" required>
                    </div>
                    <div class="inquiry-field-row">
                        <input type="email" name="customer_email" placeholder="<?php esc_attr_e('Your Email Address *', 'tourivo'); ?>" required>
                    </div>
                    <div class="inquiry-field-row">
                        <input type="tel" name="customer_phone" placeholder="<?php esc_attr_e('Phone / WhatsApp Number', 'tourivo'); ?>">
                    </div>
                    <div class="inquiry-field-row-grid">
                        <div>
                            <label><small><?php esc_html_e('Expected Travel Date', 'tourivo'); ?></small></label>
                            <input type="date" name="travel_date" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>">
                        </div>
                        <div>
                            <label><small><?php esc_html_e('Travelers Count', 'tourivo'); ?></small></label>
                            <input type="number" name="guests" min="1" max="50" value="1">
                        </div>
                    </div>
                    <div class="inquiry-field-row">
                        <textarea name="message" rows="3" placeholder="<?php esc_attr_e('Tell us about your questions or custom trip preferences...', 'tourivo'); ?>" required></textarea>
                    </div>
                    <?php if (\Tourivo\Config\Config::get('require_consent', false)) : ?>
                        <div class="inquiry-field-row tourivo-consent-row" style="display:flex; align-items:flex-start; gap:8px; margin-top:8px;">
                            <input type="checkbox" name="consent" id="tourivo-inq-consent-<?php echo esc_attr((string) $tourivoItemId); ?>" value="1" required style="margin-top:3px;">
                            <label for="tourivo-inq-consent-<?php echo esc_attr((string) $tourivoItemId); ?>" style="font-size:13px; line-height:1.4;">
                                <?php echo \Tourivo\Support\Privacy::getRenderedConsentLabel(); ?> <span class="required" style="color:#ef4444;">*</span>
                            </label>
                        </div>
                    <?php endif; ?>
                    <div class="inquiry-alert" id="tourivo-inquiry-alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="tourivo-btn tourivo-btn-primary tourivo-btn-block" id="tourivo-inquiry-submit-btn">
                        ✈️ <?php esc_html_e('Send Inquiry to Specialists', 'tourivo'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
