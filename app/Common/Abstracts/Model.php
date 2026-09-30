<?php

declare(strict_types=1);

namespace Tourivo\Common\Abstracts;

use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Model
 *
 * Base object model for Tourivo entities (Tours, Hotels, Rooms, Bookings).
 *
 * @package Tourivo\Common\Abstracts
 */
abstract class Model
{
    /**
     * The post or entity ID.
     *
     * @var int
     */
    protected int $id = 0;

    /**
     * WP_Post instance if post-based.
     *
     * @var WP_Post|null
     */
    protected ?WP_Post $post = null;

    /**
     * Model constructor.
     *
     * @param int|WP_Post $post
     */
    public function __construct(int|WP_Post $post)
    {
        if ($post instanceof WP_Post) {
            $this->id = $post->ID;
            $this->post = $post;
        } else {
            $this->id = $post;
            $this->post = get_post($post);
        }
    }

    /**
     * Get entity ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get underlying WP_Post.
     *
     * @return WP_Post|null
     */
    public function getPost(): ?WP_Post
    {
        return $this->post;
    }

    /**
     * Get post title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->post ? get_the_title($this->post) : '';
    }

    /**
     * Get post permalink.
     *
     * @return string
     */
    public function getPermalink(): string
    {
        return $this->post ? (string) get_permalink($this->post) : '';
    }

    /**
     * Get post excerpt or trimmed content.
     *
     * @param int $words
     * @return string
     */
    public function getExcerpt(int $words = 25): string
    {
        if (!$this->post) {
            return '';
        }

        if (!empty($this->post->post_excerpt)) {
            return $this->post->post_excerpt;
        }

        return wp_trim_words(strip_shortcodes($this->post->post_content), $words);
    }

    /**
     * Get post thumbnail image URL.
     *
     * @param string $size
     * @return string
     */
    public function getThumbnailUrl(string $size = 'tourivo-card'): string
    {
        if (!$this->post) {
            return '';
        }

        $url = get_the_post_thumbnail_url($this->post, $size);
        return $url ?: '';
    }

    /**
     * Get meta field value.
     *
     * @param string $key
     * @param bool   $single
     * @return mixed
     */
    public function getMeta(string $key, bool $single = true): mixed
    {
        return get_post_meta($this->id, $key, $single);
    }

    /**
     * Update meta field value.
     *
     * @param string $key
     * @param mixed  $value
     * @return bool|int
     */
    public function updateMeta(string $key, mixed $value): bool|int
    {
        return update_post_meta($this->id, $key, $value);
    }
}
