<?php
/**
 * Tourivo Admin Availability & Pricing Calendar MetaBox View
 *
 * @package Tourivo\Views\Admin
 * @var \WP_Post $post
 * @var int      $itemId
 * @var string   $itemType 'tour' or 'room'
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tourivo-availability-app" id="tourivo-availability-app" data-item-id="<?php echo esc_attr((string) $itemId); ?>" data-item-type="<?php echo esc_attr($itemType); ?>">
    <div class="tourivo-avail-header">
        <div class="tourivo-avail-nav-controls">
            <button type="button" class="button button-secondary tourivo-avail-nav-prev" aria-label="<?php esc_attr_e('Previous Month', 'tourivo'); ?>">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            </button>
            <h3 class="tourivo-avail-current-month" aria-live="polite">
                <!-- Dynamically populated (e.g. November 2026) -->
            </h3>
            <button type="button" class="button button-secondary tourivo-avail-nav-next" aria-label="<?php esc_attr_e('Next Month', 'tourivo'); ?>">
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>
            <button type="button" class="button button-secondary tourivo-avail-nav-today">
                <?php esc_html_e('Today', 'tourivo'); ?>
            </button>
        </div>

        <div class="tourivo-avail-header-meta">
            <span class="tourivo-avail-meta-badge">
                <span class="dashicons dashicons-groups"></span>
                <strong><?php esc_html_e('Default Capacity:', 'tourivo'); ?></strong>
                <span class="tourivo-avail-def-cap">—</span>
            </span>
            <span class="tourivo-avail-meta-badge">
                <span class="dashicons dashicons-tag"></span>
                <strong><?php esc_html_e('Base Price:', 'tourivo'); ?></strong>
                <span class="tourivo-avail-def-price">—</span>
            </span>
        </div>
    </div>

    <div class="tourivo-avail-body">
        <!-- Main Calendar Area -->
        <div class="tourivo-avail-calendar-wrap">
            <div class="tourivo-avail-weekdays" role="row">
                <div class="tourivo-avail-weekday" role="columnheader"><?php esc_html_e('Mon', 'tourivo'); ?></div>
                <div class="tourivo-avail-weekday" role="columnheader"><?php esc_html_e('Tue', 'tourivo'); ?></div>
                <div class="tourivo-avail-weekday" role="columnheader"><?php esc_html_e('Wed', 'tourivo'); ?></div>
                <div class="tourivo-avail-weekday" role="columnheader"><?php esc_html_e('Thu', 'tourivo'); ?></div>
                <div class="tourivo-avail-weekday" role="columnheader"><?php esc_html_e('Fri', 'tourivo'); ?></div>
                <div class="tourivo-avail-weekday tourivo-weekend" role="columnheader"><?php esc_html_e('Sat', 'tourivo'); ?></div>
                <div class="tourivo-avail-weekday tourivo-weekend" role="columnheader"><?php esc_html_e('Sun', 'tourivo'); ?></div>
            </div>

            <div class="tourivo-avail-grid" id="tourivo-avail-grid" role="grid" aria-label="<?php esc_attr_e('Availability Calendar', 'tourivo'); ?>" tabindex="0">
                <!-- Days dynamically rendered here -->
            </div>

            <div class="tourivo-avail-legend">
                <span class="tourivo-legend-item"><span class="legend-dot status-available"></span> <?php esc_html_e('Available', 'tourivo'); ?></span>
                <span class="tourivo-legend-item"><span class="legend-dot status-blocked"></span> <?php esc_html_e('Blocked', 'tourivo'); ?></span>
                <span class="tourivo-legend-item"><span class="legend-dot status-sold-out"></span> <?php esc_html_e('Sold Out', 'tourivo'); ?></span>
                <span class="tourivo-legend-item"><span class="legend-dot status-override"></span> <?php esc_html_e('Custom Price Override', 'tourivo'); ?></span>
                <span class="tourivo-legend-hint">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php esc_html_e('Click a date to select, or hold Shift to select a date range. Use Arrow keys to navigate.', 'tourivo'); ?>
                </span>
            </div>
        </div>

        <!-- Bulk Action Panel -->
        <div class="tourivo-avail-panel">
            <div class="tourivo-panel-header">
                <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;">
                    <?php esc_html_e('Bulk Availability & Pricing', 'tourivo'); ?>
                </h4>
                <div class="tourivo-selection-badge" id="tourivo-selection-badge">
                    <?php esc_html_e('0 dates selected', 'tourivo'); ?>
                </div>
            </div>

            <!-- Quick Weekday Filters -->
            <div class="tourivo-panel-section">
                <label class="tourivo-panel-label"><?php esc_html_e('Filter Selected Dates by Day:', 'tourivo'); ?></label>
                <div class="tourivo-dow-checkboxes">
                    <label><input type="checkbox" class="tourivo-dow-check" value="1" checked> <?php esc_html_e('Mo', 'tourivo'); ?></label>
                    <label><input type="checkbox" class="tourivo-dow-check" value="2" checked> <?php esc_html_e('Tu', 'tourivo'); ?></label>
                    <label><input type="checkbox" class="tourivo-dow-check" value="3" checked> <?php esc_html_e('We', 'tourivo'); ?></label>
                    <label><input type="checkbox" class="tourivo-dow-check" value="4" checked> <?php esc_html_e('Th', 'tourivo'); ?></label>
                    <label><input type="checkbox" class="tourivo-dow-check" value="5" checked> <?php esc_html_e('Fr', 'tourivo'); ?></label>
                    <label><input type="checkbox" class="tourivo-dow-check" value="6" checked> <?php esc_html_e('Sa', 'tourivo'); ?></label>
                    <label><input type="checkbox" class="tourivo-dow-check" value="7" checked> <?php esc_html_e('Su', 'tourivo'); ?></label>
                </div>
            </div>

            <!-- Changes Form -->
            <div class="tourivo-panel-section">
                <div class="tourivo-form-group" style="margin-bottom: 12px;">
                    <label for="tourivo-bulk-status" class="tourivo-panel-label"><?php esc_html_e('Status Action:', 'tourivo'); ?></label>
                    <select id="tourivo-bulk-status" class="tourivo-panel-select">
                        <option value=""><?php esc_html_e('— No Status Change —', 'tourivo'); ?></option>
                        <option value="available"><?php esc_html_e('🟢 Mark as Available (Open)', 'tourivo'); ?></option>
                        <option value="blocked"><?php esc_html_e('🔴 Mark as Blocked (Closed)', 'tourivo'); ?></option>
                    </select>
                </div>

                <div class="tourivo-form-group" style="margin-bottom: 12px;">
                    <label for="tourivo-bulk-capacity" class="tourivo-panel-label"><?php esc_html_e('Adjust Total Capacity:', 'tourivo'); ?></label>
                    <input type="number" id="tourivo-bulk-capacity" min="0" step="1" class="tourivo-panel-input" placeholder="<?php esc_attr_e('Leave blank to keep current', 'tourivo'); ?>">
                </div>

                <div class="tourivo-form-group" style="margin-bottom: 12px;">
                    <label for="tourivo-bulk-price" class="tourivo-panel-label"><?php esc_html_e('Date Price Override:', 'tourivo'); ?></label>
                    <input type="number" id="tourivo-bulk-price" min="0" step="0.01" class="tourivo-panel-input" placeholder="<?php esc_attr_e('e.g. 199.00', 'tourivo'); ?>">
                    <label style="display: block; margin-top: 6px; font-size: 12px; color: #64748b;">
                        <input type="checkbox" id="tourivo-bulk-reset-price" value="1">
                        <?php esc_html_e('Reset price override to default base price', 'tourivo'); ?>
                    </label>
                </div>

                <div class="tourivo-form-group" style="margin-bottom: 16px; background: #fffbeb; padding: 10px; border-radius: 6px; border: 1px solid #fef3c7;">
                    <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: #92400e; cursor: pointer;">
                        <input type="checkbox" id="tourivo-bulk-force" value="1" style="margin-top: 2px;">
                        <span>
                            <strong><?php esc_html_e('Force Block Booked Dates', 'tourivo'); ?></strong><br>
                            <?php esc_html_e('Allows closing dates that already contain bookings (existing reservations remain valid).', 'tourivo'); ?>
                        </span>
                    </label>
                </div>

                <div class="tourivo-panel-actions">
                    <button type="button" class="button button-primary tourivo-bulk-apply-btn" id="tourivo-bulk-apply-btn">
                        <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Apply to Selected Dates', 'tourivo'); ?>
                    </button>
                    <button type="button" class="button button-secondary tourivo-bulk-clear-btn" id="tourivo-bulk-clear-btn">
                        <?php esc_html_e('Clear Selection', 'tourivo'); ?>
                    </button>
                </div>

                <div class="tourivo-panel-alert" id="tourivo-panel-alert" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>
