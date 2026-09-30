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

$tourivoTourId = (int) get_the_ID();
$tourivoTour   = new \Tourivo\Models\Tour($tourivoTourId);
?>

<div id="tourivo-single-tour-<?php the_ID(); ?>" <?php post_class('tourivo-single-wrapper tourivo-single-tour'); ?>>
    <div class="tourivo-container">
        <!-- Top Breadcrumbs / Back link -->
        <div class="tourivo-top-meta">
            <a href="<?php echo esc_url(get_post_type_archive_link('tourivo_tour')); ?>" class="back-link">&larr; <?php esc_html_e('All Tours & Packages', 'tourivo'); ?></a>
            <?php if ($tourivoTour->getBadge()) : ?>
                <span class="tourivo-ribbon-inline"><?php echo esc_html($tourivoTour->getBadge()); ?></span>
            <?php endif; ?>
        </div>

        <header class="tourivo-header-section">
            <h1 class="tourivo-title"><?php the_title(); ?></h1>
            <div class="tourivo-sub-meta">
                <?php 
                $tourivoDestinations = $tourivoTour->getDestinations();
                if (!empty($tourivoDestinations)) : 
                ?>
                    <span class="meta-tag"><span class="dashicons dashicons-location"></span> <?php echo esc_html($tourivoDestinations[0]->name); ?></span>
                <?php endif; ?>

                <?php if ($tourivoTour->getDuration()) : ?>
                    <span class="meta-tag"><span class="dashicons dashicons-clock"></span> <?php echo esc_html($tourivoTour->getDuration()); ?></span>
                <?php endif; ?>

                <span class="meta-tag"><span class="dashicons dashicons-groups"></span> <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: Minimum guests, 2: Maximum guests */
                        __('Min: %1$d | Max: %2$d Pax', 'tourivo'),
                        $tourivoTour->getMinGuests(),
                        $tourivoTour->getMaxGuests()
                    )
                );
                ?></span>
                <span class="meta-tag"><span class="dashicons dashicons-tag"></span> <?php echo esc_html(ucwords(str_replace('_', ' ', $tourivoTour->getTourType()))); ?></span>
                <?php if ($tourivoTour->getReviewCount() > 0) : ?>
                    <a href="#tourivo-reviews" class="meta-tag meta-rating-link">
                        <span class="rating-star">★</span> <?php echo esc_html(number_format($tourivoTour->getAverageRating(), 1)); ?> (<?php echo esc_html((string)$tourivoTour->getReviewCount()); ?> <?php esc_html_e('reviews', 'tourivo'); ?>)
                    </a>
                <?php endif; ?>
                <button type="button" class="tourivo-wishlist-toggle-single" data-id="<?php echo esc_attr((string)$tourivoTourId); ?>" data-type="tour">
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
                $tourivoItinerary = $tourivoTour->getItinerary();
                if (!empty($tourivoItinerary)) : 
                ?>
                    <div class="tourivo-block-section">
                        <h2 class="section-heading"><?php esc_html_e('Day by Day Itinerary', 'tourivo'); ?></h2>
                        <div class="tourivo-itinerary-timeline">
                            <?php foreach ($tourivoItinerary as $tourivoStep) : ?>
                                <div class="timeline-step">
                                    <div class="step-badge"><?php echo esc_html($tourivoStep['day'] ?? '1'); ?></div>
                                    <div class="step-content">
                                        <h3 class="step-title"><?php echo esc_html($tourivoStep['title'] ?? ''); ?></h3>
                                        <?php if (!empty($tourivoStep['meals'])) : ?>
                                            <span class="step-meals"><span class="dashicons dashicons-food"></span> <strong><?php esc_html_e('Meals:', 'tourivo'); ?></strong> <?php echo esc_html($tourivoStep['meals']); ?></span>
                                        <?php endif; ?>
                                        <p class="step-desc"><?php echo nl2br(esc_html($tourivoStep['desc'] ?? '')); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Inclusions & Exclusions -->
                <?php 
                $tourivoInclusions = $tourivoTour->getInclusions();
                $tourivoExclusions = $tourivoTour->getExclusions();
                if (!empty($tourivoInclusions) || !empty($tourivoExclusions)) : 
                ?>
                    <div class="tourivo-block-section">
                        <h2 class="section-heading"><?php esc_html_e("What's Included & Excluded", 'tourivo'); ?></h2>
                        <div class="tourivo-inc-exc-grid">
                            <?php if (!empty($tourivoInclusions)) : ?>
                                <div class="inc-box">
                                    <h3><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <?php esc_html_e('Included', 'tourivo'); ?></h3>
                                    <ul>
                                        <?php foreach ($tourivoInclusions as $tourivoInc) : ?>
                                            <li><span class="dashicons dashicons-yes"></span> <?php echo esc_html($tourivoInc); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($tourivoExclusions)) : ?>
                                <div class="exc-box">
                                    <h3><span class="dashicons dashicons-dismiss" style="color:#ef4444;"></span> <?php esc_html_e('Excluded', 'tourivo'); ?></h3>
                                    <ul>
                                        <?php foreach ($tourivoExclusions as $tourivoExc) : ?>
                                            <li><span class="dashicons dashicons-no-alt"></span> <?php echo esc_html($tourivoExc); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Meeting & Pickup Locations -->
                <?php if ($tourivoTour->getPickupLocation() || $tourivoTour->getDropoffLocation()) : ?>
                    <div class="tourivo-block-section">
                        <h2 class="section-heading"><?php esc_html_e('Meeting & Logistics', 'tourivo'); ?></h2>
                        <div class="logistics-box">
                            <?php if ($tourivoTour->getPickupLocation()) : ?>
                                <div class="logistics-item">
                                    <strong><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e('Pickup / Meeting Point:', 'tourivo'); ?></strong>
                                    <p><?php echo esc_html($tourivoTour->getPickupLocation()); ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($tourivoTour->getDropoffLocation()) : ?>
                                <div class="logistics-item">
                                    <strong><span class="dashicons dashicons-flag"></span> <?php esc_html_e('Drop-off Point:', 'tourivo'); ?></strong>
                                    <p><?php echo esc_html($tourivoTour->getDropoffLocation()); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- FAQs -->
                <?php 
                $tourivoFaqs = $tourivoTour->getFaqs();
                if (!empty($tourivoFaqs)) : 
                ?>
                    <div class="tourivo-block-section">
                        <h2 class="section-heading"><?php esc_html_e('Frequently Asked Questions', 'tourivo'); ?></h2>
                        <div class="tourivo-faqs-accordion">
                            <?php foreach ($tourivoFaqs as $tourivoFaq) : ?>
                                <details class="faq-item">
                                    <summary class="faq-question"><?php echo esc_html($tourivoFaq['question'] ?? ''); ?></summary>
                                    <div class="faq-answer"><?php echo nl2br(esc_html($tourivoFaq['answer'] ?? '')); ?></div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reviews & Star Ratings Section -->
                <?php 
                $tourivoPostId = $tourivoTourId;
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
