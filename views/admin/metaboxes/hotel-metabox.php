<?php
/**
 * Hotel Meta Box View (Tabbed UI)
 *
 * @package Tourivo
 * @var WP_Post $post
 * @var int $starRating
 * @var string $address
 * @var string $city
 * @var string $postalCode
 * @var string $latitude
 * @var string $longitude
 * @var string $checkInTime
 * @var string $checkOutTime
 * @var string $phone
 * @var string $email
 * @var string $policy
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tourivo-metabox-wrapper">
    <div class="tourivo-tabs-nav">
        <ul>
            <li class="active"><a href="#hotel-tab-general"><span class="dashicons dashicons-admin-home"></span> <?php esc_html_e('General & Rating', 'tourivo'); ?></a></li>
            <li><a href="#hotel-tab-location"><span class="dashicons dashicons-location"></span> <?php esc_html_e('Location & Contact', 'tourivo'); ?></a></li>
            <li><a href="#hotel-tab-policies"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Policies & Rules', 'tourivo'); ?></a></li>
            <?php do_action('tourivo_hotel_metabox_tabs', $post); ?>
        </ul>
    </div>

    <div class="tourivo-tabs-content">
        <!-- 1. General & Rating Tab -->
        <div id="hotel-tab-general" class="tourivo-tab-pane active">
            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_star_rating"><?php esc_html_e('Star Rating *', 'tourivo'); ?></label>
                        <select name="_tourivo_star_rating" id="_tourivo_star_rating">
                            <option value="1" <?php selected($starRating, 1); ?>>★ 1 Star</option>
                            <option value="2" <?php selected($starRating, 2); ?>>★★ 2 Stars</option>
                            <option value="3" <?php selected($starRating, 3); ?>>★★★ 3 Stars (Comfort)</option>
                            <option value="4" <?php selected($starRating, 4); ?>>★★★★ 4 Stars (First Class)</option>
                            <option value="5" <?php selected($starRating, 5); ?>>★★★★★ 5 Stars (Luxury Resort)</option>
                        </select>
                        <p class="description"><?php esc_html_e('Property official star rating category.', 'tourivo'); ?></p>
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_city"><?php esc_html_e('City / Region', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_city" id="_tourivo_city" value="<?php echo esc_attr($city); ?>" placeholder="e.g. Cox's Bazar / Bali / Paris">
                        <p class="description"><?php esc_html_e('City or neighborhood location.', 'tourivo'); ?></p>
                    </div>
                </div>
            </div>

            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_check_in_time"><?php esc_html_e('Standard Check-in Time', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_check_in_time" id="_tourivo_check_in_time" value="<?php echo esc_attr($checkInTime); ?>" placeholder="14:00">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_check_out_time"><?php esc_html_e('Standard Check-out Time', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_check_out_time" id="_tourivo_check_out_time" value="<?php echo esc_attr($checkOutTime); ?>" placeholder="11:00">
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Location & Contact Tab -->
        <div id="hotel-tab-location" class="tourivo-tab-pane">
            <div class="tourivo-form-group">
                <label for="_tourivo_address"><?php esc_html_e('Full Street Address', 'tourivo'); ?></label>
                <input type="text" name="_tourivo_address" id="_tourivo_address" value="<?php echo esc_attr($address); ?>" placeholder="e.g. Marine Drive Road, Kolatoli Beach">
            </div>

            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_phone"><?php esc_html_e('Contact Phone / WhatsApp', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_phone" id="_tourivo_phone" value="<?php echo esc_attr($phone); ?>" placeholder="+880 1700-000000">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_email"><?php esc_html_e('Front Desk Contact Email', 'tourivo'); ?></label>
                        <input type="email" name="_tourivo_email" id="_tourivo_email" value="<?php echo esc_attr($email); ?>" placeholder="booking@hotel.com">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_postal_code"><?php esc_html_e('Postal / ZIP Code', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_postal_code" id="_tourivo_postal_code" value="<?php echo esc_attr($postalCode); ?>" placeholder="4700">
                    </div>
                </div>
            </div>

            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_latitude"><?php esc_html_e('Map Latitude (GPS)', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_latitude" id="_tourivo_latitude" value="<?php echo esc_attr($latitude); ?>" placeholder="21.4272">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_longitude"><?php esc_html_e('Map Longitude (GPS)', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_longitude" id="_tourivo_longitude" value="<?php echo esc_attr($longitude); ?>" placeholder="91.9702">
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Policies & Rules Tab -->
        <div id="hotel-tab-policies" class="tourivo-tab-pane">
            <div class="tourivo-form-group">
                <label for="_tourivo_policy"><?php esc_html_e('Hotel Policies & House Rules', 'tourivo'); ?></label>
                <textarea rows="6" name="_tourivo_policy" id="_tourivo_policy" placeholder="<?php esc_attr_e('e.g. Pets are not allowed. Valid Government ID card required at check-in. Cancellation allowed up to 48 hours before arrival.', 'tourivo'); ?>"><?php echo esc_textarea($policy); ?></textarea>
                <p class="description"><?php esc_html_e('These house rules will be displayed clearly to travelers before booking.', 'tourivo'); ?></p>
            </div>
        </div>

        <?php do_action('tourivo_hotel_metabox_panes', $post); ?>
    </div>
</div>
