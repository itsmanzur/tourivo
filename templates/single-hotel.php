<?php
/**
 * Single Hotel Template
 *
 * This template can be overridden by copying it to yourtheme/tourivo/single-hotel.php.
 *
 * @package Tourivo
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$hotelId = get_the_ID();
$hotel   = new \Tourivo\Models\Hotel($hotelId);
$rooms   = $hotel->getRooms();
$symbol  = (string) apply_filters('tourivo/currency_symbol', '$');
?>

<div id="tourivo-single-hotel-<?php the_ID(); ?>" <?php post_class('tourivo-single-wrapper tourivo-single-hotel'); ?>>
    <div class="tourivo-container">
        <!-- Top Breadcrumbs / Back link -->
        <div class="tourivo-top-meta">
            <a href="<?php echo esc_url(get_post_type_archive_link('tourivo_hotel')); ?>" class="back-link">&larr; <?php esc_html_e('All Hotels & Accommodations', 'tourivo'); ?></a>
        </div>

        <header class="tourivo-header-section">
            <div class="tourivo-stars-row">
                <?php echo str_repeat('★', $hotel->getStarRating()); ?>
            </div>
            <h1 class="tourivo-title"><?php the_title(); ?></h1>
            <div class="tourivo-sub-meta">
                <?php if ($hotel->getAddress()) : ?>
                    <span class="meta-tag"><span class="dashicons dashicons-location"></span> <?php echo esc_html($hotel->getAddress()); ?>, <?php echo esc_html($hotel->getCity()); ?></span>
                <?php endif; ?>

                <?php if ($hotel->getPhone()) : ?>
                    <span class="meta-tag"><span class="dashicons dashicons-phone"></span> <?php echo esc_html($hotel->getPhone()); ?></span>
                <?php endif; ?>

                <span class="meta-tag"><span class="dashicons dashicons-clock"></span> <?php echo esc_html(sprintf(__('Check-in: %1$s | Check-out: %2$s', 'tourivo'), $hotel->getCheckInTime(), $hotel->getCheckOutTime())); ?></span>
                <?php if ($hotel->getReviewCount() > 0) : ?>
                    <a href="#tourivo-reviews" class="meta-tag meta-rating-link">
                        <span class="rating-star">★</span> <?php echo esc_html(number_format($hotel->getAverageRating(), 1)); ?> (<?php echo esc_html((string)$hotel->getReviewCount()); ?> <?php esc_html_e('reviews', 'tourivo'); ?>)
                    </a>
                <?php endif; ?>
                <button type="button" class="tourivo-wishlist-toggle-single" data-id="<?php echo esc_attr((string)$hotelId); ?>" data-type="hotel">
                    <span class="dashicons dashicons-heart"></span> <span class="wishlist-btn-label"><?php esc_html_e('Save', 'tourivo'); ?></span>
                </button>
            </div>
        </header>

        <!-- Featured Banner -->
        <?php if (has_post_thumbnail()) : ?>
            <div class="tourivo-featured-banner">
                <?php the_post_thumbnail('large', ['class' => 'tourivo-hero-img']); ?>
            </div>
        <?php endif; ?>

        <!-- Amenities -->
        <?php 
        $amenities = $hotel->getAmenities();
        if (!empty($amenities)) : 
        ?>
            <div class="tourivo-block-section">
                <h2 class="section-heading"><?php esc_html_e('Property Amenities & Highlights', 'tourivo'); ?></h2>
                <div class="tourivo-amenities-tags">
                    <?php foreach ($amenities as $amenity) : ?>
                        <span class="amenity-badge"><span class="dashicons dashicons-yes"></span> <?php echo esc_html($amenity->name); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hotel Overview -->
        <div class="tourivo-block-section">
            <h2 class="section-heading"><?php esc_html_e('About this Property', 'tourivo'); ?></h2>
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
        </div>

        <!-- Available Rooms Section -->
        <div class="tourivo-block-section">
            <h2 class="section-heading"><?php esc_html_e('Available Room Types & Rates', 'tourivo'); ?></h2>

            <?php if (!empty($rooms)) : ?>
                <div class="tourivo-rooms-list">
                    <?php foreach ($rooms as $room) : ?>
                        <div class="tourivo-room-card" id="room-<?php echo esc_attr($room->getId()); ?>">
                            <div class="room-thumb-col">
                                <?php if ($room->getThumbnailUrl()) : ?>
                                    <img src="<?php echo esc_url($room->getThumbnailUrl('tourivo-card')); ?>" alt="<?php echo esc_attr($room->getTitle()); ?>">
                                <?php else : ?>
                                    <div class="tourivo-placeholder-img"><span class="dashicons dashicons-admin-home"></span></div>
                                <?php endif; ?>
                            </div>

                            <div class="room-info-col">
                                <h3 class="room-title"><?php echo esc_html($room->getTitle()); ?></h3>
                                <div class="room-specs">
                                    <span class="spec-item"><span class="dashicons dashicons-groups"></span> <?php echo esc_html(sprintf(__('Up to %d Guests', 'tourivo'), $room->getMaxGuests())); ?></span>
                                    <?php if ($room->getBedType()) : ?>
                                        <span class="spec-item"><span class="dashicons dashicons-bed"></span> <?php echo esc_html($room->getBedType()); ?></span>
                                    <?php endif; ?>
                                    <?php if ($room->getRoomSize()) : ?>
                                        <span class="spec-item"><span class="dashicons dashicons-editor-expand"></span> <?php echo esc_html($room->getRoomSize()); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="room-desc">
                                    <?php echo esc_html($room->getExcerpt(20)); ?>
                                </div>
                            </div>

                            <div class="room-price-col">
                                <div class="price-tag">
                                    <span class="amount"><?php echo esc_html($room->getFormattedPrice()); ?></span>
                                    <span class="unit">/ <?php esc_html_e('night', 'tourivo'); ?></span>
                                </div>
                                <div class="room-booking-widget-wrap">
                                    <details class="room-reserve-dropdown">
                                        <summary class="tourivo-btn tourivo-btn-primary"><?php esc_html_e('Reserve Room', 'tourivo'); ?></summary>
                                        <div class="room-dropdown-content">
                                            <?php 
                                            $itemId = $room->getId();
                                            $itemType = 'hotel_room';
                                            include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/booking-panel.php'; 
                                            ?>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="no-rooms-msg"><?php esc_html_e('No rooms currently available for this property.', 'tourivo'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Reviews & Star Ratings Section -->
        <?php 
        $postId = $hotelId;
        include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/reviews-section.php'; 
        ?>
    </div>
</div>

<?php get_footer();

