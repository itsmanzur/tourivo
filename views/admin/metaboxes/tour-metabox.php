<?php
/**
 * Tour Meta Box View (Tabbed UI)
 *
 * @package Tourivo
 * @var WP_Post $post
 * @var string $tourType
 * @var string $basePrice
 * @var string $salePrice
 * @var string $duration
 * @var int $minGuests
 * @var int $maxGuests
 * @var string $badge
 * @var string $pickupLocation
 * @var string $dropoffLocation
 * @var string $latitude
 * @var string $longitude
 * @var array $itinerary
 * @var array $inclusions
 * @var array $exclusions
 * @var array $faqs
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tourivo-metabox-wrapper">
    <div class="tourivo-tabs-nav">
        <ul>
            <li class="active"><a href="#tab-pricing"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Pricing & General', 'tourivo'); ?></a></li>
            <li><a href="#tab-itinerary"><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Itinerary Builder', 'tourivo'); ?></a></li>
            <li><a href="#tab-inclusions"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Inclusions & Exclusions', 'tourivo'); ?></a></li>
            <li><a href="#tab-location"><span class="dashicons dashicons-location"></span> <?php esc_html_e('Location & Map', 'tourivo'); ?></a></li>
            <li><a href="#tab-faqs"><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e('FAQs & Badge', 'tourivo'); ?></a></li>
            <?php do_action('tourivo_tour_metabox_tabs', $post); ?>
        </ul>
    </div>

    <div class="tourivo-tabs-content">
        <!-- 1. Pricing & General Tab -->
        <div id="tab-pricing" class="tourivo-tab-pane active">
            <div class="tourivo-form-group">
                <label for="_tourivo_tour_type"><?php esc_html_e('Tour Type', 'tourivo'); ?></label>
                <select name="_tourivo_tour_type" id="_tourivo_tour_type">
                    <option value="single_day" <?php selected($tourType, 'single_day'); ?>><?php esc_html_e('Single Day Tour', 'tourivo'); ?></option>
                    <option value="multi_day" <?php selected($tourType, 'multi_day'); ?>><?php esc_html_e('Multi-Day Tour', 'tourivo'); ?></option>
                    <option value="hourly" <?php selected($tourType, 'hourly'); ?>><?php esc_html_e('Hourly / Time Slot Activity', 'tourivo'); ?></option>
                    <option value="fixed_departure" <?php selected($tourType, 'fixed_departure'); ?>><?php esc_html_e('Fixed Departure Tour', 'tourivo'); ?></option>
                </select>
            </div>

            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_base_price"><?php esc_html_e('Base Price ($)', 'tourivo'); ?></label>
                        <input type="number" step="0.01" min="0" name="_tourivo_base_price" id="_tourivo_base_price" value="<?php echo esc_attr($basePrice); ?>" placeholder="199.00">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_sale_price"><?php esc_html_e('Sale / Offer Price ($)', 'tourivo'); ?></label>
                        <input type="number" step="0.01" min="0" name="_tourivo_sale_price" id="_tourivo_sale_price" value="<?php echo esc_attr($salePrice); ?>" placeholder="149.00">
                    </div>
                </div>
            </div>

            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_duration"><?php esc_html_e('Trip Duration Text', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_duration" id="_tourivo_duration" value="<?php echo esc_attr($duration); ?>" placeholder="e.g. 3 Days / 2 Nights">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_min_guests"><?php esc_html_e('Min Guests', 'tourivo'); ?></label>
                        <input type="number" min="1" name="_tourivo_min_guests" id="_tourivo_min_guests" value="<?php echo esc_attr($minGuests); ?>">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_max_guests"><?php esc_html_e('Max Group Size', 'tourivo'); ?></label>
                        <input type="number" min="1" name="_tourivo_max_guests" id="_tourivo_max_guests" value="<?php echo esc_attr($maxGuests); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Itinerary Builder Tab -->
        <div id="tab-itinerary" class="tourivo-tab-pane">
            <p class="description"><?php esc_html_e('Add day-by-day plan and activities for this tour.', 'tourivo'); ?></p>
            <div id="tourivo-itinerary-repeater" class="tourivo-repeater-container">
                <?php if (!empty($itinerary)) : ?>
                    <?php foreach ($itinerary as $index => $item) : ?>
                        <div class="tourivo-repeater-row">
                            <div class="row-header">
                                <strong><?php echo esc_html(sprintf(__('Day / Stage: %s', 'tourivo'), $item['day'] ?? ($index + 1))); ?></strong>
                                <button type="button" class="button remove-row-btn">&times;</button>
                            </div>
                            <div class="row-body">
                                <div class="tourivo-row">
                                    <div class="tourivo-col" style="flex: 0 0 100px;">
                                        <label><?php esc_html_e('Day #', 'tourivo'); ?></label>
                                        <input type="text" name="_tourivo_itinerary[<?php echo esc_attr($index); ?>][day]" value="<?php echo esc_attr($item['day'] ?? ($index + 1)); ?>">
                                    </div>
                                    <div class="tourivo-col">
                                        <label><?php esc_html_e('Day Title', 'tourivo'); ?></label>
                                        <input type="text" name="_tourivo_itinerary[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($item['title'] ?? ''); ?>" placeholder="e.g. Arrival in Bali & Beach Walk">
                                    </div>
                                    <div class="tourivo-col" style="flex: 0 0 160px;">
                                        <label><?php esc_html_e('Included Meals', 'tourivo'); ?></label>
                                        <input type="text" name="_tourivo_itinerary[<?php echo esc_attr($index); ?>][meals]" value="<?php echo esc_attr($item['meals'] ?? ''); ?>" placeholder="Breakfast, Dinner">
                                    </div>
                                </div>
                                <div class="tourivo-form-group" style="margin-top: 10px;">
                                    <label><?php esc_html_e('Description & Details', 'tourivo'); ?></label>
                                    <textarea rows="3" name="_tourivo_itinerary[<?php echo esc_attr($index); ?>][desc]"><?php echo esc_textarea($item['desc'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="tourivo-add-itinerary-btn" class="button button-primary">+ <?php esc_html_e('Add Day to Itinerary', 'tourivo'); ?></button>
        </div>

        <!-- 3. Inclusions & Exclusions Tab -->
        <div id="tab-inclusions" class="tourivo-tab-pane">
            <div class="tourivo-row">
                <div class="tourivo-col">
                    <h3><?php esc_html_e('Included in Tour', 'tourivo'); ?></h3>
                    <div id="tourivo-inclusions-list" class="tourivo-list-repeater">
                        <?php if (!empty($inclusions)) : foreach ($inclusions as $inc) : ?>
                            <div class="list-item-row">
                                <span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span>
                                <input type="text" name="_tourivo_inclusions[]" value="<?php echo esc_attr($inc); ?>">
                                <button type="button" class="button remove-list-item">&times;</button>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <button type="button" id="tourivo-add-inclusion-btn" class="button">+ <?php esc_html_e('Add Included Item', 'tourivo'); ?></button>
                </div>

                <div class="tourivo-col">
                    <h3><?php esc_html_e('Excluded from Tour', 'tourivo'); ?></h3>
                    <div id="tourivo-exclusions-list" class="tourivo-list-repeater">
                        <?php if (!empty($exclusions)) : foreach ($exclusions as $exc) : ?>
                            <div class="list-item-row">
                                <span class="dashicons dashicons-dismiss" style="color: #ef4444;"></span>
                                <input type="text" name="_tourivo_exclusions[]" value="<?php echo esc_attr($exc); ?>">
                                <button type="button" class="button remove-list-item">&times;</button>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <button type="button" id="tourivo-add-exclusion-btn" class="button">+ <?php esc_html_e('Add Excluded Item', 'tourivo'); ?></button>
                </div>
            </div>
        </div>

        <!-- 4. Location & Map Tab -->
        <div id="tab-location" class="tourivo-tab-pane">
            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_pickup_location"><?php esc_html_e('Pickup Point / Meeting Place', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_pickup_location" id="_tourivo_pickup_location" value="<?php echo esc_attr($pickupLocation); ?>" placeholder="e.g. Airport Terminal 1 or Hotel Lobby">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_dropoff_location"><?php esc_html_e('Drop-off Location', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_dropoff_location" id="_tourivo_dropoff_location" value="<?php echo esc_attr($dropoffLocation); ?>" placeholder="e.g. Central City Station">
                    </div>
                </div>
            </div>
            <div class="tourivo-row">
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_latitude"><?php esc_html_e('Latitude (GPS)', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_latitude" id="_tourivo_latitude" value="<?php echo esc_attr($latitude); ?>" placeholder="e.g. -8.409518">
                    </div>
                </div>
                <div class="tourivo-col">
                    <div class="tourivo-form-group">
                        <label for="_tourivo_longitude"><?php esc_html_e('Longitude (GPS)', 'tourivo'); ?></label>
                        <input type="text" name="_tourivo_longitude" id="_tourivo_longitude" value="<?php echo esc_attr($longitude); ?>" placeholder="e.g. 115.188916">
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. FAQs & Badge Tab -->
        <div id="tab-faqs" class="tourivo-tab-pane">
            <div class="tourivo-form-group">
                <label for="_tourivo_badge"><?php esc_html_e('Highlight Ribbon / Badge', 'tourivo'); ?></label>
                <input type="text" name="_tourivo_badge" id="_tourivo_badge" value="<?php echo esc_attr($badge); ?>" placeholder="e.g. Bestseller, 20% Off, Top Rated">
            </div>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

            <h3><?php esc_html_e('Frequently Asked Questions (FAQs)', 'tourivo'); ?></h3>
            <div id="tourivo-faqs-list" class="tourivo-repeater-container">
                <?php if (!empty($faqs)) : foreach ($faqs as $i => $faq) : ?>
                    <div class="tourivo-repeater-row">
                        <div class="row-header">
                            <strong><?php esc_html_e('Question & Answer', 'tourivo'); ?></strong>
                            <button type="button" class="button remove-row-btn">&times;</button>
                        </div>
                        <div class="row-body">
                            <div class="tourivo-form-group">
                                <label><?php esc_html_e('Question', 'tourivo'); ?></label>
                                <input type="text" name="_tourivo_faqs[<?php echo esc_attr($i); ?>][question]" value="<?php echo esc_attr($faq['question'] ?? ''); ?>">
                            </div>
                            <div class="tourivo-form-group">
                                <label><?php esc_html_e('Answer', 'tourivo'); ?></label>
                                <textarea rows="2" name="_tourivo_faqs[<?php echo esc_attr($i); ?>][answer]"><?php echo esc_textarea($faq['answer'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <button type="button" id="tourivo-add-faq-btn" class="button">+ <?php esc_html_e('Add FAQ Question', 'tourivo'); ?></button>
        </div>

        <?php do_action('tourivo_tour_metabox_panes', $post); ?>
    </div>
</div>
