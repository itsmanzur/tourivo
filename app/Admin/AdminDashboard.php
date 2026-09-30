<?php

declare(strict_types=1);

namespace Tourivo\Admin;

use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\RoomPostType;
use Tourivo\PostTypes\TourPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminDashboard
 *
 * Renders the main Tourivo admin dashboard with KPI stats, recent bookings, and seeder.
 *
 * @package Tourivo\Admin
 */
class AdminDashboard
{
    /**
     * Render the admin dashboard view.
     *
     * @return void
     */
    public static function render(): void
    {
        global $wpdb;

        // 1. KPI Counts
        $toursCount  = (int) (wp_count_posts(TourPostType::POST_TYPE)->publish ?? 0);
        $hotelsCount = (int) (wp_count_posts(HotelPostType::POST_TYPE)->publish ?? 0);
        $roomsCount  = (int) (wp_count_posts(RoomPostType::POST_TYPE)->publish ?? 0);

        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $bookingsCount = 0;
        $totalRevenue  = 0.0;
        $recentBookings = [];

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bookingsTable)) === $bookingsTable) {
            $bookingsCount  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$bookingsTable}");
            $totalRevenue   = (float) ($wpdb->get_var("SELECT SUM(total_amount) FROM {$bookingsTable} WHERE booking_status != 'cancelled'") ?: 0.0);
            $recentBookings = (array) $wpdb->get_results("SELECT * FROM {$bookingsTable} ORDER BY id DESC LIMIT 5");
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');
        ?>
        <div class="wrap tourivo-admin-wrap">
            <div class="tourivo-dashboard-header">
                <div>
                    <h1 class="wp-heading-inline"><?php esc_html_e('Tourivo Dashboard', 'tourivo'); ?></h1>
                    <p class="tourivo-subtitle"><?php esc_html_e('Welcome back! Overview of your travel packages, hotel rooms, and live booking activity.', 'tourivo'); ?></p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=tourivo_tour')); ?>" class="button button-primary">+ <?php esc_html_e('New Tour', 'tourivo'); ?></a>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=tourivo_hotel')); ?>" class="button">+ <?php esc_html_e('New Hotel', 'tourivo'); ?></a>
                </div>
            </div>

            <!-- KPI Metric Tiles -->
            <div class="tourivo-kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-icon icon-tours"><span class="dashicons dashicons-palmtree"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Active Tours', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html((string)$toursCount); ?></span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon icon-hotels"><span class="dashicons dashicons-building"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Hotels & Stays', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html((string)$hotelsCount); ?></span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon icon-rooms"><span class="dashicons dashicons-admin-home"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Room Types', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html((string)$roomsCount); ?></span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon icon-bookings"><span class="dashicons dashicons-calendar-alt"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Total Bookings', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html((string)$bookingsCount); ?></span>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon icon-revenue"><span class="dashicons dashicons-money-alt"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Total Revenue', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html(\Tourivo\Support\Money::format($totalRevenue)); ?></span>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Split: Recent Bookings & Quick Actions / Seeder -->
            <div class="tourivo-dashboard-layout">
                <!-- Recent Bookings Table -->
                <div class="dashboard-main-col">
                    <div class="tourivo-card-box">
                        <div class="box-header">
                            <h2><?php esc_html_e('Recent Bookings', 'tourivo'); ?></h2>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings')); ?>" class="view-all-link"><?php esc_html_e('View All Bookings &rarr;', 'tourivo'); ?></a>
                        </div>
                        <?php if (!empty($recentBookings)) : ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Code', 'tourivo'); ?></th>
                                        <th><?php esc_html_e('Customer', 'tourivo'); ?></th>
                                        <th><?php esc_html_e('Amount', 'tourivo'); ?></th>
                                        <th><?php esc_html_e('Status', 'tourivo'); ?></th>
                                        <th><?php esc_html_e('Date', 'tourivo'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBookings as $bk) : ?>
                                        <tr>
                                            <td><strong>#<?php echo esc_html($bk->booking_code); ?></strong></td>
                                            <td><?php echo esc_html($bk->customer_name); ?><br><small style="color:#64748b;"><?php echo esc_html($bk->customer_email); ?></small></td>
                                            <td><strong><?php echo esc_html(\Tourivo\Support\Money::format((float)$bk->total_amount)); ?></strong></td>
                                            <td>
                                                <span class="tourivo-badge tourivo-badge-<?php echo esc_attr($bk->booking_status); ?>">
                                                    <?php echo esc_html(ucfirst($bk->booking_status)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html(gmdate('M d, Y', strtotime($bk->created_at))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <div class="empty-state-box">
                                <p><?php esc_html_e('No bookings recorded yet. Start sharing your tours and hotels with travelers!', 'tourivo'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Side: 1-Click Demo Data & Shortcodes Helper -->
                <div class="dashboard-side-col">
                    <div class="tourivo-card-box seeder-card">
                        <h3><span class="dashicons dashicons-download"></span> <?php esc_html_e('1-Click Demo Travel & Hotel Data', 'tourivo'); ?></h3>
                        <p><?php esc_html_e('Quickly test Tourivo by importing ready-made tours (Bali, Swiss Alps, Dubai) and boutique hotels with rooms.', 'tourivo'); ?></p>
                        <button type="button" id="tourivo-import-sample-btn" class="button button-secondary tourivo-import-sample-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_admin_nonce')); ?>">
                            <span class="dashicons dashicons-cloud-upload"></span> <?php esc_html_e('Import Demo Data', 'tourivo'); ?>
                        </button>
                        <div id="tourivo-seeder-notice" class="tourivo-seeder-notice" style="display:none; margin-top:10px;"></div>
                    </div>

                    <div class="tourivo-card-box">
                        <h3><span class="dashicons dashicons-shortcode"></span> <?php esc_html_e('Useful Shortcodes', 'tourivo'); ?></h3>
                        <ul class="shortcode-tips">
                            <li><code>[tourivo_search_bar]</code> – Search filter bar</li>
                            <li><code>[tourivo_tours columns="3"]</code> – Tours grid</li>
                            <li><code>[tourivo_hotels columns="3"]</code> – Hotels grid</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
