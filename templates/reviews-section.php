<?php
/**
 * Tourivo Reviews & Star Rating Section Template
 *
 * @package Tourivo
 * @var int $postId
 */

if (!defined('ABSPATH')) {
    exit;
}

$postId = $postId ?? get_the_ID();
if (!$postId) {
    return;
}

$reviewService = new \Tourivo\Services\ReviewService();
$breakdown = $reviewService->getRatingBreakdown($postId);
$comments = get_comments([
    'post_id' => $postId,
    'status'  => 'approve',
    'type'    => 'comment',
    'order'   => 'DESC',
]);
?>

<div class="tourivo-block-section tourivo-reviews-block" id="tourivo-reviews">
    <h2 class="section-heading">
        ⭐ <?php esc_html_e('Traveler Reviews & Ratings', 'tourivo'); ?>
        <?php if ($breakdown['total'] > 0) : ?>
            <span class="reviews-total-badge">(<?php echo esc_html((string)$breakdown['total']); ?>)</span>
        <?php endif; ?>
    </h2>

    <!-- Rating Summary Header -->
    <div class="tourivo-rating-overview">
        <div class="rating-score-box">
            <span class="score-number"><?php echo esc_html(number_format($breakdown['average'], 1)); ?></span>
            <div class="score-stars">
                <?php
                $filledStars = (int) round($breakdown['average']);
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= $filledStars ? '<span class="star filled">★</span>' : '<span class="star">☆</span>';
                }
                ?>
            </div>
            <span class="score-subtext">
                <?php echo esc_html(sprintf(_n('Based on %d review', 'Based on %d reviews', $breakdown['total'], 'tourivo'), $breakdown['total'])); ?>
            </span>
        </div>

        <div class="rating-bars-box">
            <?php for ($s = 5; $s >= 1; $s--) : 
                $pct = $breakdown['percentages'][$s] ?? 0;
                $count = $breakdown['stars'][$s] ?? 0;
            ?>
                <div class="rating-bar-row">
                    <span class="bar-star-label"><?php echo esc_html((string)$s); ?> ★</span>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: <?php echo esc_attr((string)$pct); ?>%;"></div>
                    </div>
                    <span class="bar-count-label"><?php echo esc_html((string)$count); ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Reviews List -->
    <div class="tourivo-reviews-list">
        <?php if (!empty($comments)) : ?>
            <?php foreach ($comments as $comm) : 
                $rating = (int) get_comment_meta((int)$comm->comment_ID, \Tourivo\Services\ReviewService::META_RATING, true);
                $isVerified = $reviewService->isVerifiedTraveler($comm->comment_author_email, $postId);
            ?>
                <div class="tourivo-review-card" id="comment-<?php echo esc_attr((string)$comm->comment_ID); ?>">
                    <div class="review-header">
                        <div class="review-author-avatar">
                            <?php echo get_avatar($comm, 48); ?>
                        </div>
                        <div class="review-author-info">
                            <h4 class="author-name">
                                <?php echo esc_html($comm->comment_author); ?>
                                <?php if ($isVerified) : ?>
                                    <span class="verified-badge" title="<?php esc_attr_e('Verified Booking Traveler', 'tourivo'); ?>">
                                        ✓ <?php esc_html_e('Verified Traveler', 'tourivo'); ?>
                                    </span>
                                <?php endif; ?>
                            </h4>
                            <span class="review-date"><?php echo esc_html(get_comment_date('F j, Y', $comm)); ?></span>
                        </div>
                        <?php if ($rating > 0) : ?>
                            <div class="review-stars">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating ? '<span class="star filled">★</span>' : '<span class="star">☆</span>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="review-content">
                        <?php echo wp_kses_post(wpautop($comm->comment_content)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="no-reviews-msg" style="color: #64748b; padding: 20px 0;">
                <?php esc_html_e('No reviews yet. Be the first to share your travel experience!', 'tourivo'); ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Leave a Review Form -->
    <div class="tourivo-leave-review-form-wrap">
        <h3>✍️ <?php esc_html_e('Write a Review', 'tourivo'); ?></h3>
        
        <?php if (comments_open($postId)) : ?>
            <form action="<?php echo esc_url(site_url('/wp-comments-post.php')); ?>" method="post" class="tourivo-review-form">
                <!-- Interactive Star Selector -->
                <div class="form-row rating-picker-row">
                    <label><strong><?php esc_html_e('Your Rating *', 'tourivo'); ?></strong></label>
                    <div class="tourivo-star-picker" id="tourivo-star-picker">
                        <span class="picker-star" data-val="1">★</span>
                        <span class="picker-star" data-val="2">★</span>
                        <span class="picker-star" data-val="3">★</span>
                        <span class="picker-star" data-val="4">★</span>
                        <span class="picker-star" data-val="5">★</span>
                    </div>
                    <input type="hidden" name="tourivo_rating" id="tourivo_rating_input" value="5" required>
                </div>

                <div class="form-row-group" style="display: flex; gap: 14px;">
                    <div class="form-row" style="flex: 1;">
                        <label for="author"><?php esc_html_e('Your Name *', 'tourivo'); ?></label>
                        <input type="text" name="author" id="author" required value="<?php echo esc_attr(wp_get_current_user()->display_name ?? ''); ?>">
                    </div>
                    <div class="form-row" style="flex: 1;">
                        <label for="email"><?php esc_html_e('Your Email *', 'tourivo'); ?></label>
                        <input type="email" name="email" id="email" required value="<?php echo esc_attr(wp_get_current_user()->user_email ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 12px;">
                    <label for="comment"><?php esc_html_e('Your Review / Feedback *', 'tourivo'); ?></label>
                    <textarea name="comment" id="comment" rows="4" required placeholder="<?php esc_attr_e('What did you love about this trip? How was the guide, accommodation, and itinerary?', 'tourivo'); ?>"></textarea>
                </div>

                <input type="hidden" name="comment_post_ID" value="<?php echo esc_attr((string)$postId); ?>">
                <input type="hidden" name="comment_parent" id="comment_parent" value="0">

                <div class="form-row form-submit-row" style="margin-top: 14px;">
                    <button type="submit" class="tourivo-btn tourivo-btn-primary">
                        🚀 <?php esc_html_e('Submit Review', 'tourivo'); ?>
                    </button>
                </div>
            </form>
        <?php else : ?>
            <p style="color: #64748b;"><?php esc_html_e('Comments and reviews are closed for this trip.', 'tourivo'); ?></p>
        <?php endif; ?>
    </div>
</div>
