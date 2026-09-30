<?php
/**
 * Tour Card Template
 *
 * @package Tourivo
 * @var \Tourivo\Models\Tour $tour
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($tour) || !($tour instanceof \Tourivo\Models\Tour)) {
    return;
}
?>
<div class="tourivo-card tourivo-tour-card">
    <div class="tourivo-card-thumb">
        <a href="<?php echo esc_url($tour->getPermalink()); ?>">
            <?php if ($tour->getThumbnailUrl()) : ?>
                <img src="<?php echo esc_url($tour->getThumbnailUrl('tourivo-card')); ?>" alt="<?php echo esc_attr($tour->getTitle()); ?>" loading="lazy">
            <?php else : ?>
                <div class="tourivo-placeholder-img"><span class="dashicons dashicons-palmtree"></span></div>
            <?php endif; ?>
        </a>

        <!-- Wishlist Button -->
        <button type="button" class="tourivo-wishlist-toggle" data-id="<?php echo esc_attr((string)$tour->getId()); ?>" data-type="tour" title="<?php esc_attr_e('Save to Wishlist', 'tourivo'); ?>" aria-label="<?php esc_attr_e('Save to Wishlist', 'tourivo'); ?>">
            <span class="dashicons dashicons-heart"></span>
        </button>

        <?php if ($tour->getBadge()) : ?>
            <span class="tourivo-ribbon"><?php echo esc_html($tour->getBadge()); ?></span>
        <?php endif; ?>

        <?php 
        $tourivoDestinations = $tour->getDestinations();
        if (!empty($tourivoDestinations)) : 
        ?>
            <span class="tourivo-dest-tag"><span class="dashicons dashicons-location"></span> <?php echo esc_html($tourivoDestinations[0]->name); ?></span>
        <?php endif; ?>
    </div>

    <div class="tourivo-card-body">
        <div class="tourivo-card-meta">
            <?php if ($tour->getDuration()) : ?>
                <span class="meta-item"><span class="dashicons dashicons-clock"></span> <?php echo esc_html($tour->getDuration()); ?></span>
            <?php endif; ?>
            <span class="meta-item"><span class="dashicons dashicons-groups"></span> <?php
            echo esc_html(
                sprintf(
                    /* translators: %d: Maximum guests allowed */
                    __('Max: %d', 'tourivo'),
                    $tour->getMaxGuests()
                )
            );
            ?></span>
            <?php if ($tour->getReviewCount() > 0) : ?>
                <span class="meta-item meta-rating" title="<?php
                echo esc_attr(
                    sprintf(
                        /* translators: %s: Rating score out of 5 */
                        __('%s out of 5 stars', 'tourivo'),
                        $tour->getAverageRating()
                    )
                );
                ?>">
                    <span class="rating-star">★</span> <?php echo esc_html(number_format($tour->getAverageRating(), 1)); ?> <span class="rating-count">(<?php echo esc_html((string)$tour->getReviewCount()); ?>)</span>
                </span>
            <?php endif; ?>
        </div>

        <h3 class="tourivo-card-title">
            <a href="<?php echo esc_url($tour->getPermalink()); ?>"><?php echo esc_html($tour->getTitle()); ?></a>
        </h3>

        <div class="tourivo-card-footer">
            <div class="tourivo-price-box">
                <span class="price-from"><?php esc_html_e('From', 'tourivo'); ?></span>
                <span class="price-val"><?php echo esc_html($tour->getFormattedPrice()); ?></span>
                <?php if ($tour->getSalePrice()) : ?>
                    <del class="price-original">$<?php echo esc_html((string)$tour->getPrice()); ?></del>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url($tour->getPermalink()); ?>" class="tourivo-btn tourivo-btn-sm"><?php esc_html_e('Explore', 'tourivo'); ?> &rarr;</a>
        </div>
    </div>
</div>
