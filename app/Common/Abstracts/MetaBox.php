<?php

declare(strict_types=1);

namespace Tourivo\Common\Abstracts;

use Tourivo\Common\Traits\Hookable;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MetaBox
 *
 * Base abstract class for all custom post type metaboxes.
 *
 * @package Tourivo\Common\Abstracts
 */
abstract class MetaBox
{
    use Hookable;

    /**
     * Unique ID for the metabox.
     *
     * @var string
     */
    protected string $id;

    /**
     * Title of the metabox.
     *
     * @var string
     */
    protected string $title;

    /**
     * Post type or types this metabox applies to.
     *
     * @var string|array<string>
     */
    protected string|array $postTypes;

    /**
     * Context ('normal', 'side', 'advanced').
     *
     * @var string
     */
    protected string $context = 'normal';

    /**
     * Priority ('high', 'core', 'default', 'low').
     *
     * @var string
     */
    protected string $priority = 'high';

    /**
     * Nonce action name.
     *
     * @var string
     */
    protected string $nonceAction;

    /**
     * Nonce field name.
     *
     * @var string
     */
    protected string $nonceName;

    /**
     * MetaBox constructor.
     */
    public function __construct()
    {
        $this->nonceAction = $this->id . '_action';
        $this->nonceName   = $this->id . '_nonce';

        $this->addAction('add_meta_boxes', [$this, 'registerMetaBox']);
        $this->addAction('save_post', [$this, 'handleSave'], 10, 2);
    }

    /**
     * Register the metabox with WordPress.
     *
     * @return void
     */
    public function registerMetaBox(): void
    {
        $screen = is_array($this->postTypes) ? $this->postTypes : [$this->postTypes];

        foreach ($screen as $postType) {
            add_meta_box(
                $this->id,
                $this->title,
                [$this, 'renderView'],
                $postType,
                $this->context,
                $this->priority
            );
        }
    }

    /**
     * Render the metabox view wrapper with security nonce.
     *
     * @param WP_Post $post
     * @return void
     */
    public function renderView(WP_Post $post): void
    {
        wp_nonce_field($this->nonceAction, $this->nonceName);
        $this->render($post);
    }

    /**
     * Render content. Must be implemented by child classes.
     *
     * @param WP_Post $post
     * @return void
     */
    abstract public function render(WP_Post $post): void;

    /**
     * Handle saving of metabox fields.
     *
     * @param int     $postId
     * @param WP_Post $post
     * @return void
     */
    public function handleSave(int $postId, WP_Post $post): void
    {
        // 1. Verify nonce
        if (!isset($_POST[$this->nonceName]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$this->nonceName])), $this->nonceAction)) {
            return;
        }

        // 2. Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 3. Check post types
        $screens = is_array($this->postTypes) ? $this->postTypes : [$this->postTypes];
        if (!in_array($post->post_type, $screens, true)) {
            return;
        }

        // 4. Check user capability
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // 5. Delegate to child class save logic
        $this->save($postId, $post);
    }

    /**
     * Save data. Must be implemented by child classes.
     *
     * @param int     $postId
     * @param WP_Post $post
     * @return void
     */
    abstract public function save(int $postId, WP_Post $post): void;

    /**
     * Render a view file from plugin views directory.
     *
     * @param string $viewPath Relative to views/
     * @param array  $data
     * @return void
     */
    protected function loadView(string $viewPath, array $data = []): void
    {
        $file = untrailingslashit(TOURIVO_PLUGIN_DIR) . '/views/' . ltrim($viewPath, '/');
        if (file_exists($file)) {
            extract($data, EXTR_SKIP);
            include $file;
        }
    }
}
