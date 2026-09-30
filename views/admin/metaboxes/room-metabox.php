<?php
/**
 * Room Meta Box View (Tabbed UI)
 *
 * @package Tourivo
 * @var WP_Post $post
 * @var int $parentHotelId
 * @var string $nightlyPrice
 * @var int $maxAdults
 * @var int $maxChildren
 * @var int $maxGuests
 * @var int $roomQuantity
 * @var string $roomSize
 * @var string $bedType
 * @var array<WP_Post> $hotels
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tourivo-metabox-wrapper">
    <div class="tourivo-tabs-nav">
        <ul>
            <li class="active"><a href="#room-tab-pricing"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing & Capacity', 'tourivo'); ?></a></li>
            <li><a href="#room-tab-specs"><span class="dashicons dashicons-admin-home"></span> <?php esc_html_e('Room Specs & Beds', 'tourivo'); ?></a></li>
            <?php do_action('tourivo_room_metabox_tabs', $post); ?>
        </ul>
    </div>

    <div class="tourivo-tabs-content">
        <!-- 1. Pricing & Capacity Tab -->
        <div id="room-tab-pricing" class="tourivo-tab-pane active">
            <div class="tourivo-form-group">
                <label for="_tourivo_parent_hotel_id"><strong><?php esc_html_e('Select Parent Hotel / Property *', 'tourivo'); ?></strong></label>
                <select name="_tourivo_parent_hotel_id" id="_tourivo_parent_hotel_id" style="width: 100%; max-width: 480px;">
                    <option value=""><?php esc_html_e('-- Select Hotel --', 'tourivo'); ?></option>
                    <?php foreach ($hotels as $hotel) : ?>
                        <option value="<?php echo esc_attr((string)$hotel->ID); ?>" <?php selected($parentHotelId, $hotel->ID); ?>>
                            🏨 <?php echo esc_html($hotel->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Select which hotel or resort property this room unit belongs to.', 'tourivo'); ?></p>
            </div>

            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_nightly_price"><?php esc_html_e('Base Nightly Price ($) *', 'tourivo'); ?></label>
                        <input type="number" step="0.01" min="0" name="_tourivo_nightly_price" id="_tourivo_nightly_price" value="<?php echo esc_attr($nightlyPrice); ?>" placeholder="120.00">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_room_quantity"><?php esc_html_e('Total Units Available', 'tourivo'); ?></label>
                        <input type="number" min="1" name="_tourivo_room_quantity" id="_tourivo_room_quantity" value="<?php echo esc_attr((string)$roomQuantity); ?>">
                        <p class="description"><?php esc_html_e('Inventory capacity for this room type.', 'tourivo'); ?></p>
                    </div>
                </div>
            </div>

            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_max_adults"><?php esc_html_e('Max Adults', 'tourivo'); ?></label>
                        <input type="number" min="1" name="_tourivo_max_adults" id="_tourivo_max_adults" value="<?php echo esc_attr((string)$maxAdults); ?>">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_max_children"><?php esc_html_e('Max Children', 'tourivo'); ?></label>
                        <input type="number" min="0" name="_tourivo_max_children" id="_tourivo_max_children" value="<?php echo esc_attr((string)$maxChildren); ?>">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_max_guests"><?php esc_html_e('Total Max Guests', 'tourivo'); ?></label>
                        <input type="number" min="1" name="_tourivo_max_guests" id="_tourivo_max_guests" value="<?php echo esc_attr((string)$maxGuests); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Room Specs & Beds Tab -->
        <div id="room-tab-specs" class="tourivo-tab-pane">
            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_bed_type"><?php esc_html_e('Bed Configuration', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_bed_type" id="_tourivo_bed_type" value="<?php echo esc_attr($bedType); ?>" placeholder="e.g. 1 King Bed or 2 Single Beds">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_room_size"><?php esc_html_e('Room Dimensions / Size', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_room_size" id="_tourivo_room_size" value="<?php echo esc_attr($roomSize); ?>" placeholder="e.g. 35 m² / 376 ft²">
                    </div>
                </div>
            </div>
        </div>

        <?php do_action('tourivo_room_metabox_panes', $post); ?>
    </div>
</div>
