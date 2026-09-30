<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Admin\MetaBoxes\HotelMetaBox;
use Tourivo\Admin\MetaBoxes\RoomMetaBox;
use Tourivo\Admin\MetaBoxes\TourMetaBox;
use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\Models\Hotel;
use Tourivo\Models\Room;
use Tourivo\Models\Tour;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\RoomPostType;
use Tourivo\PostTypes\TourPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MetaBoxServiceProvider
 *
 * Registers metaboxes, admin scripts/styles for metaboxes, and custom columns for post listings.
 *
 * @package Tourivo\Providers
 */
class MetaBoxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(TourMetaBox::class, fn () => new TourMetaBox());
        $this->container->singleton(HotelMetaBox::class, fn () => new HotelMetaBox());
        $this->container->singleton(RoomMetaBox::class, fn () => new RoomMetaBox());
    }

    public function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        // Initialize MetaBoxes
        $this->container->get(TourMetaBox::class);
        $this->container->get(HotelMetaBox::class);
        $this->container->get(RoomMetaBox::class);

        // Assets
        $this->addAction('admin_enqueue_scripts', [$this, 'enqueueMetaBoxAssets']);

        // Custom Admin List Columns - Tour
        $this->addFilter('manage_' . TourPostType::POST_TYPE . '_posts_columns', [$this, 'setTourColumns']);
        $this->addAction('manage_' . TourPostType::POST_TYPE . '_posts_custom_column', [$this, 'renderTourColumn'], 10, 2);

        // Custom Admin List Columns - Hotel
        $this->addFilter('manage_' . HotelPostType::POST_TYPE . '_posts_columns', [$this, 'setHotelColumns']);
        $this->addAction('manage_' . HotelPostType::POST_TYPE . '_posts_custom_column', [$this, 'renderHotelColumn'], 10, 2);

        // Custom Admin List Columns - Room
        $this->addFilter('manage_' . RoomPostType::POST_TYPE . '_posts_columns', [$this, 'setRoomColumns']);
        $this->addAction('manage_' . RoomPostType::POST_TYPE . '_posts_custom_column', [$this, 'renderRoomColumn'], 10, 2);
    }

    /**
     * Enqueue assets on post edit screens.
     *
     * @param string $hook
     * @return void
     */
    public function enqueueMetaBoxAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, [TourPostType::POST_TYPE, HotelPostType::POST_TYPE, RoomPostType::POST_TYPE], true)) {
            return;
        }

        wp_enqueue_style(
            'tourivo-admin-metabox',
            TOURIVO_PLUGIN_URL . 'assets/css/admin-metabox.css',
            ['dashicons'],
            TOURIVO_VERSION
        );

        wp_enqueue_script(
            'tourivo-admin-metabox',
            TOURIVO_PLUGIN_URL . 'assets/js/admin-metabox.js',
            [],
            TOURIVO_VERSION,
            true
        );
    }

    /**
     * Tour custom columns.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function setTourColumns(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $title) {
            $newColumns[$key] = $title;
            if ($key === 'title') {
                $newColumns['tour_type'] = __('Type', 'tourivo');
                $newColumns['price']     = __('Price', 'tourivo');
                $newColumns['duration']  = __('Duration', 'tourivo');
            }
        }
        return $newColumns;
    }

    /**
     * Render Tour custom column value.
     *
     * @param string $column
     * @param int    $postId
     * @return void
     */
    public function renderTourColumn(string $column, int $postId): void
    {
        $tour = new Tour($postId);

        switch ($column) {
            case 'tour_type':
                echo esc_html(ucwords(str_replace('_', ' ', $tour->getTourType())));
                break;
            case 'price':
                echo '<strong>' . esc_html($tour->getFormattedPrice()) . '</strong>';
                if ($tour->getSalePrice()) {
                    echo ' <del style="color:#94a3b8; font-size:11px;">$' . esc_html((string)$tour->getPrice()) . '</del>';
                }
                break;
            case 'duration':
                echo esc_html($tour->getDuration() ?: '—');
                break;
        }
    }

    /**
     * Hotel custom columns.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function setHotelColumns(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $title) {
            $newColumns[$key] = $title;
            if ($key === 'title') {
                $newColumns['star_rating'] = __('Rating', 'tourivo');
                $newColumns['city']        = __('City', 'tourivo');
                $newColumns['room_count']  = __('Rooms', 'tourivo');
            }
        }
        return $newColumns;
    }

    /**
     * Render Hotel custom column value.
     *
     * @param string $column
     * @param int    $postId
     * @return void
     */
    public function renderHotelColumn(string $column, int $postId): void
    {
        $hotel = new Hotel($postId);

        switch ($column) {
            case 'star_rating':
                echo str_repeat('★', $hotel->getStarRating());
                break;
            case 'city':
                echo esc_html($hotel->getCity() ?: '—');
                break;
            case 'room_count':
                echo esc_html((string) count($hotel->getRooms()));
                break;
        }
    }

    /**
     * Room custom columns.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function setRoomColumns(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $title) {
            $newColumns[$key] = $title;
            if ($key === 'title') {
                $newColumns['parent_hotel'] = __('Parent Hotel', 'tourivo');
                $newColumns['nightly_rate'] = __('Price / Night', 'tourivo');
                $newColumns['capacity']     = __('Capacity', 'tourivo');
            }
        }
        return $newColumns;
    }

    /**
     * Render Room custom column value.
     *
     * @param string $column
     * @param int    $postId
     * @return void
     */
    public function renderRoomColumn(string $column, int $postId): void
    {
        $room = new Room($postId);

        switch ($column) {
            case 'parent_hotel':
                $hotel = $room->getHotel();
                if ($hotel) {
                    echo '<a href="' . esc_url(get_edit_post_link($hotel->getId())) . '"><strong>' . esc_html($hotel->getTitle()) . '</strong></a>';
                } else {
                    echo '<span style="color:#ef4444;">' . esc_html__('Unassigned', 'tourivo') . '</span>';
                }
                break;
            case 'nightly_rate':
                echo '<strong>' . esc_html($room->getFormattedPrice()) . '</strong>';
                break;
            case 'capacity':
                echo sprintf(
                    /* translators: 1: Adults count, 2: Children count */
                    esc_html__('%1$d Adults, %2$d Children', 'tourivo'),
                    $room->getMaxAdults(),
                    $room->getMaxChildren()
                );
                break;
        }
    }
}
