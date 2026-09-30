<?php
/**
 * Single Tour Template
 *
 * This template can be overridden by copying it to yourtheme/tourivo/single-tour.php.
 *
 * @package Tourivo
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$tourId = get_the_ID();
$tour   = new \Tourivo\Models\Tour($tourId);
?>

<div id="tourivo-single-tour-<?php the_ID(); ?>" <?php post_class('tourivo-single-wrapper tourivo-single-tour'); ?>>
    <div class="tourivo-container">
        <!-- Top Breadcrumbs / Back link -->
        <div class="tourivo-top-meta">
            <a href="<?php echo esc_url(get_post_type_archive_link('tourivo_tour')); ?>" class="back-link">&larr; <?php esc_html_e('All Tours & Packages', 'tourivo'); ?></a>
            <?php if ($tour->getBadge()) : ?>
                <span class="tourivo-ribbon-inline"><?php echo esc_html($tour->getBadge()); ?></span>
            <?php endif; ?>
        </div>

        <header class="tourivo-header-section">
            <h1 class="tourivo-title"><?php the_title(); ?></h1>
            <div class="tourivo-sub-meta">
                <?php 
                $destinations = $tour->getDestinations();
                if (!empty($destinations)) : 
                ?>
                    <span class="meta-tag"><span class="dashicons dashicons-location"></span> <?php echo esc_html($destinations[0]->name); ?></span>
                <?php endif; ?>

                <?php if ($tour->getDuration()) : ?>
                    <span class="meta-tag"><span class="dashicons dashicons-clock"></span> <?php echo esc_html($tour->getDuration()); ?></span>
                <?php endif; ?>

                <span class="meta-tag"><span class="dashicons dashicons-groups"></span> <?php echo esc_html(sprintf(__('Min: %1$d | Max: %2$d Pax', 'tourivo'), $tour->getMinGuests(), $tour->getMaxGuests())); ?></span>
                <span class="meta-tag"><span class="dashicons dashicons-tag"></span> <?php echo esc_html(ucwords(str_replace('_', ' ', $tour->getTourType()))); ?></span>
                <?php if ($tour->getReviewCount() > 0) : ?>
                    <a href="#tourivo-reviews" class="meta-tag meta-rating-link">
                        <span class="rating-star">★</span> <?php echo esc_html(number_format($tour->getAverageRating(), 1)); ?> (<?php echo esc_html((string)$tour->getReviewCount()); ?> <?php esc_html_e('reviews', 'tourivo'); ?>)
                    </a>
                <?php endif; ?>
                <button type="button" class="tourivo-wishlist-toggle-single" data-id="<?php echo esc_attr((string)$tourId); ?>" data-type="tour">
                    <span class="dashicons dashicons-heart"></span> <span class="wishlist-btn-label"><?php esc_html_e('Save', 'tourivo'); ?></span>
                </button>
            </div>
        </header>

        <!-- Main Layout Grid: Content + Sticky Booking Sidebar -->
        <div class="tourivo-layout-grid">
            <!-- Left Column: Tour Details -->
            <div class="tourivo-main-content">
                <!-- Featured Image -->
                <?php if (has_post_thumbnail()) : ?>
                    <div class="tourivo-featured-banner">
                        <?php the_post_thumbnail('large', ['class' => 'tourivo-hero-img']); ?>
                    </div>
                <?php endif; ?>

                <!-- Tour Overview -->
                <div class="tourivo-block-section">
                    <h2 class="section-heading"><?php esc_html_e('Tour Overview & Description', 'tourivo'); ?></h2>
                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>
                </div>

                <!-- Itinerary Timeline -->
                <?php 
                $itinerary = $tour->getItinerary();
                if (!empty($itinerary)) : 
                ?>
                    <div class="tourivo-block-section">
                        <h2 class="section-heading"><?php esc_html_e('Day by Day Itinerary', 'tourivo'); ?></h2>
                        <div class="tourivo-itinerary-timeline">
                            <?php foreach ($itinerary as $step) : ?>
                                <div class="timeline-step">
                                    <div class="step-badge"><?php echo esc_html($step['day'] ?? '1'); ?></div>
                                    <div class="step-content">
                                        <h3 class="step-title"><?php echo esc_html($step['title'] ?? ''); ?></h3>
                                        <?php if (!empty($step['meals'])) : ?>
                                            <span class="step-meals"><span class="dashicons dashicons-food"></span> <strong><?php esc_html_e('Meals:', 'tourivo'); ?></strong> <?php echo esc_html($step['meals']); ?></span>
                                        <?php endif; ?>
                                        <p class="step-desc"><?php echo nl2br(esc_html($step['desc'] ?? '')); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Inclusions & Exclusions -->
                <?php 
                $inclusions = $tour->getInclusions();
                $exclusions = $tour->getExclusions();
                if (!empty($inclusions) || !empty($exclusions)) : 
                ?>
                    <div class="tourivo-block-section">
                        <h2 class="section-heading"><?php esc_html_e("What's Included & Excluded", 'tourivo'); ?></h2>
                        <div class="tourivo-inc-exc-grid">
                            <?php if (!empty($inclusions)) : ?>
                                <div class="inc-box">
                                    <h3><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <?php esc_html_e('Included', 'tourivo'); ?></h3>
                                    <ul>
                                        <?php foreach ($inclusions as $inc) : ?>
                                            <li><span class="dashicons dashicons-yes"></span> <?php echo esc_html($inc); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($exclusions)) : ?>
                                <div class="exc-box">
                                    <h3><span class="dashicons dashicons-dismiss" style="color:#ef4444;"></span> <?php esc_html_e('Excluded', 'tourivo'); ?></h3>
                                    <ul>
                                        <?php foreach ($exclusions as $exc) : ?>
                                            <li><span class="dashicons dashicons-no-alt"></span> <?php echo esc_html($exc); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Meeting & Pickup Locations -->
                <?php if ($tour->getPickupLocation() || $tour->getDropoffLocation()) : ?>
                    <div class="tourivo-block-section">
                        <h2 class="section-heading"><?php esc_html_e('Meeting & Logistics', 'tourivo'); ?></h2>
                        <div class="logistics-box">
                            <?php if ($tour->getPickupLocation()) : ?>
                                <div class="logistics-item">
                                    <strong><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e('Pickup / Meeting Point:', 'tourivo'); ?></strong>
                                    <p><?php echo esc_html($tour->getPickupLocation()); ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($tour->getDropoffLocation()) : ?>
                                <div class="logistics-item">
                                    <strong><span class="dashicons dashicons-flag"></span> <?php esc_html_e('Drop-off Point:', 'tourivo'); ?></strong>
                                    <p><?php echo esc_html($tour->getDropoffLocation()); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- FAQs -->
                <?php 
                $faqs = $tour->getFaqs();
                if (!empty($faqs)) : 
                ?>
                    <div class="tourivo-block-section">
                        <h2 class="section-heading"><?php esc_html_e('Frequently Asked Questions', 'tourivo'); ?></h2>
                        <div class="tourivo-faqs-accordion">
                            <?php foreach ($faqs as $faq) : ?>
                                <details class="faq-item">
                                    <summary class="faq-question"><?php echo esc_html($faq['question'] ?? ''); ?></summary>
                                    <div class="faq-answer"><?php echo nl2br(esc_html($faq['answer'] ?? '')); ?></div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reviews & Star Ratings Section -->
                <?php 
                $postId = $tourId;
                include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/reviews-section.php'; 
                ?>
            </div>

            <!-- Right Column: Sticky Booking Panel -->
            <div class="tourivo-sidebar">
                <div class="tourivo-sticky-box">
                    <?php 
                    include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/booking-panel.php'; 
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer();
