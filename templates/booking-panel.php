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

$itemId         = isset($itemId) ? (int) $itemId : get_the_ID();
$postType       = get_post_type($itemId);
$itemType       = ($postType === 'tourivo_room') ? 'room' : 'tour';
$currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');

if ($itemType === 'tour') {
    $tour = new \Tourivo\Models\Tour($itemId);
    $basePrice = $tour->getActivePrice();
    $maxGuests = $tour->getMaxGuests() > 0 ? $tour->getMaxGuests() : 20;
    $maxRooms  = 1;
} else {
    $room = new \Tourivo\Models\Room($itemId);
    $basePrice = $room->getNightlyPrice();
    $maxGuests = $room->getMaxGuests() > 0 ? $room->getMaxGuests() : 4;
    $maxRooms  = $room->getQuantity() > 0 ? $room->getQuantity() : 10;
}
?>

<div class="tourivo-booking-panel" id="tourivo-booking-panel-<?php echo esc_attr((string) $itemId); ?>" data-item-id="<?php echo esc_attr((string) $itemId); ?>" data-item-type="<?php echo esc_attr($itemType); ?>" data-unit-price="<?php echo esc_attr((string) $basePrice); ?>" data-currency="<?php echo esc_attr($currencySymbol); ?>">
    <div class="panel-header">
        <div class="panel-price-box">
            <span class="price-label"><?php esc_html_e('Price:', 'tourivo'); ?></span>
            <span class="price-amount" id="tourivo-live-price"><?php echo esc_html(Money::format($basePrice)); ?></span>
            <span class="price-suffix"><?php echo ($itemType === 'tour') ? esc_html__('/ person', 'tourivo') : esc_html__('/ night', 'tourivo'); ?></span>
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
            <label class="section-label"><?php echo ($itemType === 'tour') ? esc_html__('Select Tour Date *', 'tourivo') : esc_html__('Check-in Date *', 'tourivo'); ?></label>
            <div class="input-with-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
                <input type="date" name="check_in" id="tourivo-check-in" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" value="<?php echo esc_attr(wp_date('Y-m-d', strtotime('+1 day'))); ?>" required>
            </div>
        </div>

        <?php if ($itemType === 'room') : ?>
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
                            <small><?php echo esc_html(sprintf(__('Up to %d available', 'tourivo'), $maxRooms)); ?></small>
                        </div>
                        <div class="counter-controls">
                            <button type="button" class="counter-btn minus-btn" data-target="rooms-count">-</button>
                            <input type="number" name="rooms" id="rooms-count" value="1" min="1" max="<?php echo esc_attr((string) $maxRooms); ?>" readonly>
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
                        <input type="number" name="adults" id="adults-count" value="1" min="1" max="<?php echo esc_attr((string) $maxGuests); ?>" readonly>
                        <button type="button" class="counter-btn plus-btn" data-target="adults-count">+</button>
                    </div>
                </div>

                <div class="guest-counter-item">
                    <div class="guest-type">
                        <strong><?php esc_html_e('Children', 'tourivo'); ?></strong>
                        <small><?php esc_html_e('Age 2-11', 'tourivo'); ?></small>
                    </div>
                    <div class="counter-controls">
                        <button type="button" class="counter-btn minus-btn" data-target="children-count">-</button>
                        <input type="number" name="children" id="children-count" value="0" min="0" max="<?php echo esc_attr((string) $maxGuests); ?>" readonly>
                        <button type="button" class="counter-btn plus-btn" data-target="children-count">+</button>
                    </div>
                </div>
            </div>
        </div>

        <?php do_action('tourivo_booking_panel_after_guests', $itemId, $itemType); ?>

        <!-- Live Price Breakdown -->
        <div class="price-breakdown-box" id="tourivo-price-breakdown">
            <div class="breakdown-row">
                <span class="breakdown-desc" id="breakdown-calc-label"><?php echo esc_html(Money::format($basePrice)); ?> &times; 1 <?php echo ($itemType === 'room') ? esc_html__('Night', 'tourivo') : esc_html__('Guest', 'tourivo'); ?></span>
                <span class="breakdown-val" id="breakdown-total-val"><?php echo esc_html(Money::format($basePrice)); ?></span>
            </div>
            <div class="breakdown-row total-row">
                <span><strong><?php esc_html_e('Total Amount', 'tourivo'); ?></strong></span>
                <span class="total-amount-val" id="live-grand-total"><strong><?php echo esc_html(Money::format($basePrice)); ?></strong></span>
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
        </div>

        <?php do_action('tourivo_booking_panel_before_submit', $itemId, $itemType); ?>

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
                <input type="hidden" name="item_id" value="<?php echo esc_attr((string) $itemId); ?>">
                <input type="hidden" name="item_type" value="<?php echo esc_attr($itemType); ?>">
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
