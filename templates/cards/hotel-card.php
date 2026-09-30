<?php
/**
 * Hotel Card Template
 *
 * @package Tourivo
 * @var \Tourivo\Models\Hotel $hotel
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($hotel) || !($hotel instanceof \Tourivo\Models\Hotel)) {
    return;
}

$minPrice = $hotel->getMinPrice();
$symbol = (string) apply_filters('tourivo/currency_symbol', '$');
?>
<div class="tourivo-card tourivo-hotel-card">
    <div class="tourivo-card-thumb">
        <a href="<?php echo esc_url($hotel->getPermalink()); ?>">
            <?php if ($hotel->getThumbnailUrl()) : ?>
                <img src="<?php echo esc_url($hotel->getThumbnailUrl('tourivo-card')); ?>" alt="<?php echo esc_attr($hotel->getTitle()); ?>" loading="lazy">
            <?php else : ?>
                <div class="tourivo-placeholder-img"><span class="dashicons dashicons-building"></span></div>
            <?php endif; ?>
        </a>

        <!-- Wishlist Button -->
        <button type="button" class="tourivo-wishlist-toggle" data-id="<?php echo esc_attr((string)$hotel->getId()); ?>" data-type="hotel" title="<?php esc_attr_e('Save to Wishlist', 'tourivo'); ?>" aria-label="<?php esc_attr_e('Save to Wishlist', 'tourivo'); ?>">
            <span class="dashicons dashicons-heart"></span>
        </button>

        <div class="tourivo-stars-badge">
            <?php echo str_repeat('★', $hotel->getStarRating()); ?>
        </div>

        <?php if ($hotel->getCity()) : ?>
            <span class="tourivo-dest-tag"><span class="dashicons dashicons-location"></span> <?php echo esc_html($hotel->getCity()); ?></span>
        <?php endif; ?>
    </div>

    <div class="tourivo-card-body">
        <div class="tourivo-card-meta">
            <span class="meta-item"><span class="dashicons dashicons-admin-home"></span> <?php echo esc_html(sprintf(__('%d Room Types', 'tourivo'), count($hotel->getRooms()))); ?></span>
            <?php if ($hotel->getAddress()) : ?>
                <span class="meta-item text-truncate"><span class="dashicons dashicons-location-alt"></span> <?php echo esc_html($hotel->getAddress()); ?></span>
            <?php endif; ?>
            <?php if ($hotel->getReviewCount() > 0) : ?>
                <span class="meta-item meta-rating" title="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'tourivo'), $hotel->getAverageRating())); ?>">
                    <span class="rating-star">★</span> <?php echo esc_html(number_format($hotel->getAverageRating(), 1)); ?> <span class="rating-count">(<?php echo esc_html((string)$hotel->getReviewCount()); ?>)</span>
                </span>
            <?php endif; ?>
        </div>

        <h3 class="tourivo-card-title">
            <a href="<?php echo esc_url($hotel->getPermalink()); ?>"><?php echo esc_html($hotel->getTitle()); ?></a>
        </h3>

        <div class="tourivo-card-footer">
            <div class="tourivo-price-box">
                <span class="price-from"><?php esc_html_e('Starts from', 'tourivo'); ?></span>
                <span class="price-val"><?php echo esc_html(\Tourivo\Support\Money::format($minPrice)); ?></span>
                <span class="price-unit">/ <?php esc_html_e('night', 'tourivo'); ?></span>
            </div>
            <a href="<?php echo esc_url($hotel->getPermalink()); ?>" class="tourivo-btn tourivo-btn-sm"><?php esc_html_e('View Rooms', 'tourivo'); ?> &rarr;</a>
        </div>
    </div>
</div>

