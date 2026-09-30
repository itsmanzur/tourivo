<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\TourPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ReviewService
 *
 * Handles 5-star rating submission, average rating calculation, and verified traveler validation.
 *
 * @package Tourivo\Services
 */
class ReviewService
{
    public const META_RATING = '_tourivo_rating';
    public const META_AVG_RATING = '_tourivo_average_rating';
    public const META_REVIEW_COUNT = '_tourivo_review_count';

    public function __construct()
    {
        add_action('comment_post', [$this, 'onCommentPost'], 10, 3);
        add_action('edit_comment', [$this, 'onCommentUpdate']);
        add_action('deleted_comment', [$this, 'onCommentUpdate']);
        add_action('trash_comment', [$this, 'onCommentUpdate']);
        add_action('untrash_comment', [$this, 'onCommentUpdate']);
        add_action('wp_set_comment_status', [$this, 'onCommentUpdate']);
        add_action('transition_comment_status', [$this, 'onCommentTransition'], 10, 3);
    }

    /**
     * Save rating meta on comment creation.
     *
     * @param int $commentId
     * @param int|string $commentApproved
     * @param array<string, mixed> $commentData
     * @return void
     */
    public function onCommentPost(int $commentId, int|string $commentApproved, array $commentData): void
    {
        $postId = (int) ($commentData['comment_post_ID'] ?? 0);
        if (!$postId) {
            return;
        }

        $postType = get_post_type($postId);
        if (!in_array($postType, [TourPostType::POST_TYPE, HotelPostType::POST_TYPE], true)) {
            return;
        }

        if (isset($_POST['tourivo_rating'])) {
            $rating = max(1, min(5, (int) sanitize_text_field(wp_unslash((string) $_POST['tourivo_rating']))));
            update_comment_meta($commentId, self::META_RATING, $rating);
        }

        $this->updatePostRatingCache($postId);
    }

    /**
     * Triggered on comment status update, edit, or delete.
     *
     * @param int $commentId
     * @return void
     */
    public function onCommentUpdate(int $commentId): void
    {
        $comment = get_comment($commentId);
        if ($comment && !empty($comment->comment_post_ID)) {
            $this->updatePostRatingCache((int) $comment->comment_post_ID);
        }
    }

    /**
     * Handle transition comment status.
     *
     * @param string $newStatus
     * @param string $oldStatus
     * @param \WP_Comment $comment
     * @return void
     */
    public function onCommentTransition(string $newStatus, string $oldStatus, \WP_Comment $comment): void
    {
        if (!empty($comment->comment_post_ID)) {
            $this->updatePostRatingCache((int) $comment->comment_post_ID);
        }
    }

    /**
     * Recalculate and cache average rating and review count for a post using efficient SQL aggregation.
     *
     * @param int $postId
     * @return void
     */
    public function updatePostRatingCache(int $postId): void
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(CAST(cm.meta_value AS DECIMAL(10,2))) as avg_rating, 
                COUNT(cm.meta_id) as review_count
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = %s
             WHERE c.comment_post_ID = %d 
               AND c.comment_approved = '1'
               AND CAST(cm.meta_value AS UNSIGNED) BETWEEN 1 AND 5",
            self::META_RATING,
            $postId
        ));

        $avg = ($row && $row->avg_rating !== null) ? round((float) $row->avg_rating, 1) : 0.0;
        $count = ($row && $row->review_count !== null) ? (int) $row->review_count : 0;

        update_post_meta($postId, self::META_AVG_RATING, $avg);
        update_post_meta($postId, self::META_REVIEW_COUNT, $count);
    }

    /**
     * Get aggregate breakdown of ratings (5, 4, 3, 2, 1 stars) via SQL.
     *
     * @param int $postId
     * @return array{average: float, total: int, stars: array<int, int>, percentages: array<int, int>}
     */
    public function getRatingBreakdown(int $postId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                CAST(cm.meta_value AS UNSIGNED) as star_rating,
                COUNT(*) as total_count
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = %s
             WHERE c.comment_post_ID = %d 
               AND c.comment_approved = '1'
               AND CAST(cm.meta_value AS UNSIGNED) BETWEEN 1 AND 5
             GROUP BY star_rating",
            self::META_RATING,
            $postId
        ));

        $stars = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $totalRating = 0;
        $totalCount = 0;

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $rating = (int) $row->star_rating;
                $cnt = (int) $row->total_count;
                if (isset($stars[$rating])) {
                    $stars[$rating] = $cnt;
                    $totalRating += ($rating * $cnt);
                    $totalCount += $cnt;
                }
            }
        }

        $avg = $totalCount > 0 ? round($totalRating / $totalCount, 1) : 0.0;
        $percentages = [];
        foreach ($stars as $s => $cnt) {
            $percentages[$s] = $totalCount > 0 ? (int) round(($cnt / $totalCount) * 100) : 0;
        }

        return [
            'average'     => $avg,
            'total'       => $totalCount,
            'stars'       => $stars,
            'percentages' => $percentages,
        ];
    }

    /**
     * Check if an email has a confirmed booking for the post (Verified Traveler).
     * Resolves hotel rooms to parent hotels and vice versa.
     *
     * @param string $email
     * @param int $postId
     * @return bool
     */
    public function isVerifiedTraveler(string $email, int $postId): bool
    {
        if (empty($email) || !$postId) {
            return false;
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable = $wpdb->prefix . 'tourivo_booking_items';

        $targetIds = [$postId];
        $postType = get_post_type($postId);

        // If hotel, also match any rooms belonging to this hotel
        if ($postType === HotelPostType::POST_TYPE) {
            $childRooms = get_posts([
                'post_type'      => 'tourivo_room',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => '_tourivo_parent_hotel_id',
                        'value' => $postId,
                    ],
                ],
            ]);
            if (!empty($childRooms)) {
                $targetIds = array_merge($targetIds, array_map('intval', $childRooms));
            }
        }

        $placeholders = implode(',', array_fill(0, count($targetIds), '%d'));
        $sql = "SELECT b.id FROM {$bookingsTable} b
                INNER JOIN {$itemsTable} bi ON b.id = bi.booking_id
                WHERE b.customer_email = %s 
                AND bi.item_id IN ($placeholders) 
                AND b.booking_status IN ('confirmed', 'completed') 
                LIMIT 1";

        $params = array_merge([$email], $targetIds);
        $found = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return !empty($found);
    }
}
