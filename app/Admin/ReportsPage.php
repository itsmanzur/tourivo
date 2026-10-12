<?php

declare(strict_types=1);

namespace Tourivo\Admin;

use Tourivo\Common\Container;
use Tourivo\Services\ReportingService;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ReportsPage
 *
 * Renders the Admin Business Analytics, Sales, Tax, Occupancy, and Performance Reports hub.
 *
 * @package Tourivo\Admin
 */
class ReportsPage
{
    /**
     * Render the admin reports and analytics page.
     *
     * @return void
     */
    public static function render(): void
    {
        if (!current_user_can('manage_tourivo_bookings')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'tourivo'), 403);
        }

        /** @var ReportingService $reporting */
        $reporting = Container::getInstance()->get(ReportingService::class);

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $period    = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : 'this_month';
        $startDate = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : null;
        $endDate   = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : null;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $range    = $reporting->resolveDateRange($period, $startDate, $endDate);
        $metrics  = $reporting->getKpiMetrics($range['start'], $range['end']);
        $occupancy= $reporting->getOccupancyRate($range['start'], $range['end']);
        $topItems = $reporting->getTopPerformingItems($range['start'], $range['end'], 5);
        $timeline = $reporting->getTimelineData($range['start'], $range['end']);

        $currency = (string) apply_filters('tourivo/currency_symbol', '$');
        $adminUrl = admin_url('admin.php?page=tourivo-reports');

        $presets = [
            'today'       => __('Today', 'tourivo'),
            'last_7_days' => __('Last 7 Days', 'tourivo'),
            'this_month'  => __('This Month', 'tourivo'),
            'last_month'  => __('Last Month', 'tourivo'),
            'this_year'   => __('This Year', 'tourivo'),
        ];
        ?>
        <div class="wrap tourivo-admin-wrap tourivo-reports-wrap">
            <!-- Header & Filter Bar -->
            <div class="tourivo-dashboard-header tourivo-reports-header">
                <div>
                    <h1 class="wp-heading-inline"><?php esc_html_e('Analytics & Business Reports', 'tourivo'); ?></h1>
                    <p class="tourivo-subtitle">
                        <?php
                        printf(
                            /* translators: 1: Filter date range label */
                            esc_html__('Monitoring sales, occupancy rates, and tax filings for: %s', 'tourivo'),
                            '<strong>' . esc_html($range['label']) . '</strong>'
                        );
                        ?>
                    </p>
                </div>
                <div class="header-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                        <input type="hidden" name="action" value="tourivo_export_accounting_csv">
                        <input type="hidden" name="period" value="<?php echo esc_attr($range['period']); ?>">
                        <input type="hidden" name="start_date" value="<?php echo esc_attr(substr($range['start'], 0, 10)); ?>">
                        <input type="hidden" name="end_date" value="<?php echo esc_attr(substr($range['end'], 0, 10)); ?>">
                        <?php wp_nonce_field('tourivo_reports_action', 'tourivo_reports_nonce'); ?>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-media-spreadsheet" style="vertical-align: middle; margin-top: -2px;"></span>
                            <?php esc_html_e('Export Accounting & Tax CSV', 'tourivo'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Date Range Filter Toolbar -->
            <div class="tourivo-card-box tourivo-reports-filter-box">
                <div class="tourivo-filter-row">
                    <div class="tourivo-preset-pills">
                        <?php foreach ($presets as $pKey => $pLabel) : ?>
                            <a href="<?php echo esc_url(add_query_arg('period', $pKey, $adminUrl)); ?>"
                               class="tourivo-preset-pill <?php echo ($range['period'] === $pKey) ? 'active' : ''; ?>">
                                <?php echo esc_html($pLabel); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="tourivo-custom-date-form">
                        <input type="hidden" name="page" value="tourivo-reports">
                        <input type="hidden" name="period" value="custom">
                        <div class="date-inputs-wrap">
                            <label for="reports-start-date"><?php esc_html_e('From:', 'tourivo'); ?></label>
                            <input type="date" id="reports-start-date" name="start_date" value="<?php echo esc_attr(substr($range['start'], 0, 10)); ?>" required>
                            <label for="reports-end-date"><?php esc_html_e('To:', 'tourivo'); ?></label>
                            <input type="date" id="reports-end-date" name="end_date" value="<?php echo esc_attr(substr($range['end'], 0, 10)); ?>" required>
                            <button type="submit" class="button button-secondary"><?php esc_html_e('Filter', 'tourivo'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary KPI Grid -->
            <div class="tourivo-kpi-grid tourivo-reports-kpi-grid">
                <!-- Total Bookings -->
                <div class="kpi-card">
                    <div class="kpi-icon icon-bookings"><span class="dashicons dashicons-calendar-alt"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Total Bookings', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html((string) $metrics['total_bookings']); ?></span>
                        <span class="kpi-subtext">
                            <?php
                            printf(
                                /* translators: 1: Confirmed bookings count, 2: Pending count */
                                esc_html__('%1$d confirmed &bull; %2$d pending', 'tourivo'),
                                (int) $metrics['confirmed_bookings'],
                                (int) $metrics['pending_bookings']
                            );
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Gross Revenue -->
                <div class="kpi-card">
                    <div class="kpi-icon icon-revenue"><span class="dashicons dashicons-chart-line"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Gross Revenue', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html(Money::format($metrics['gross_revenue'])); ?></span>
                        <span class="kpi-subtext">
                            <?php
                            printf(
                                /* translators: 1: Net Revenue amount */
                                esc_html__('Net: %s', 'tourivo'),
                                esc_html(Money::format($metrics['net_revenue']))
                            );
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Tax Collection -->
                <div class="kpi-card">
                    <div class="kpi-icon icon-tax"><span class="dashicons dashicons-vault"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Tax Collected', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html(Money::format($metrics['total_tax'])); ?></span>
                        <span class="kpi-subtext">
                            <?php
                            printf(
                                /* translators: 1: Discounts applied */
                                esc_html__('Discounts: %s', 'tourivo'),
                                esc_html(Money::format($metrics['total_discount']))
                            );
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Occupancy Rate -->
                <div class="kpi-card">
                    <div class="kpi-icon icon-occupancy"><span class="dashicons dashicons-building"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Occupancy Rate', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html(number_format($occupancy['rate'], 1)); ?>%</span>
                        <div class="tourivo-progress-bar-wrap" title="<?php echo esc_attr(sprintf('%d booked / %d total capacity', $occupancy['total_booked'], $occupancy['total_capacity'])); ?>">
                            <div class="tourivo-progress-bar" style="width: <?php echo esc_attr((string) min(100.0, $occupancy['rate'])); ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Total Travelers -->
                <div class="kpi-card">
                    <div class="kpi-icon icon-travelers"><span class="dashicons dashicons-groups"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Total Guests / Travelers', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html((string) $metrics['total_travelers']); ?></span>
                        <span class="kpi-subtext"><?php esc_html_e('Adults & children served', 'tourivo'); ?></span>
                    </div>
                </div>

                <!-- Average Order Value (AOV) -->
                <div class="kpi-card">
                    <div class="kpi-icon icon-aov"><span class="dashicons dashicons-tag"></span></div>
                    <div class="kpi-info">
                        <span class="kpi-title"><?php esc_html_e('Average Booking Value', 'tourivo'); ?></span>
                        <span class="kpi-number"><?php echo esc_html(Money::format($metrics['average_order_value'])); ?></span>
                        <span class="kpi-subtext">
                            <?php
                            printf(
                                /* translators: 1: Outstanding due amount */
                                esc_html__('Unpaid due: %s', 'tourivo'),
                                esc_html(Money::format($metrics['due_amount']))
                            );
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Sales & Bookings Timeline Visualizer -->
            <div class="tourivo-card-box tourivo-chart-card">
                <div class="box-header">
                    <h2><?php esc_html_e('Sales & Booking Volume Timeline', 'tourivo'); ?></h2>
                    <span class="tourivo-chart-legend">
                        <span class="legend-dot" style="background:#0284c7;"></span> <?php esc_html_e('Daily Revenue', 'tourivo'); ?>
                    </span>
                </div>
                <div class="tourivo-timeline-chart-container">
                    <?php if (empty($timeline)) : ?>
                        <div class="tourivo-no-data-notice">
                            <p><?php esc_html_e('No booking transactions recorded during this selected date range.', 'tourivo'); ?></p>
                        </div>
                    <?php else :
                        $maxRev = 1.0;
                        foreach ($timeline as $dayData) {
                            if ($dayData['revenue'] > $maxRev) {
                                $maxRev = $dayData['revenue'];
                            }
                        }
                    ?>
                        <div class="tourivo-bar-chart">
                            <?php foreach ($timeline as $dayData) :
                                $heightPct = min(100.0, max(8.0, ($dayData['revenue'] / $maxRev) * 100.0));
                            ?>
                                <div class="bar-col" title="<?php echo esc_attr(sprintf('%s: %s (%d bookings)', $dayData['label'], Money::format($dayData['revenue']), $dayData['booking_count'])); ?>">
                                    <div class="bar-fill-wrap">
                                        <div class="bar-fill" style="height: <?php echo esc_attr((string) round($heightPct)); ?>%;">
                                            <span class="bar-tooltip"><?php echo esc_html(Money::format($dayData['revenue'])); ?></span>
                                        </div>
                                    </div>
                                    <span class="bar-label"><?php echo esc_html($dayData['label']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Performing Products Layout -->
            <div class="tourivo-dashboard-layout tourivo-top-items-grid">
                <!-- Top Tour Packages -->
                <div class="tourivo-card-box">
                    <div class="box-header">
                        <h2>
                            <span class="dashicons dashicons-palmtree" style="color:#0284c7;"></span>
                            <?php esc_html_e('Top Tour Packages', 'tourivo'); ?>
                        </h2>
                    </div>
                    <?php if (empty($topItems['tours'])) : ?>
                        <p class="tourivo-empty-text"><?php esc_html_e('No tour bookings found in this period.', 'tourivo'); ?></p>
                    <?php else :
                        $maxTourSales = max(1.0, (float) ($topItems['tours'][0]['total_sales'] ?? 1.0));
                    ?>
                        <div class="tourivo-ranked-list">
                            <?php foreach ($topItems['tours'] as $idx => $tour) :
                                $share = min(100.0, round(($tour['total_sales'] / $maxTourSales) * 100.0));
                            ?>
                                <div class="ranked-item">
                                    <div class="ranked-header">
                                        <div class="ranked-title">
                                            <span class="rank-badge">#<?php echo esc_html((string) ($idx + 1)); ?></span>
                                            <strong><?php echo esc_html($tour['item_title']); ?></strong>
                                        </div>
                                        <div class="ranked-sales">
                                            <strong><?php echo esc_html(Money::format($tour['total_sales'])); ?></strong>
                                        </div>
                                    </div>
                                    <div class="ranked-progress">
                                        <div class="ranked-progress-fill" style="width: <?php echo esc_attr((string) $share); ?>%;"></div>
                                    </div>
                                    <div class="ranked-meta">
                                        <span><?php echo esc_html(sprintf(_n('%d ticket sold', '%d tickets sold', $tour['units_sold'], 'tourivo'), $tour['units_sold'])); ?></span>
                                        <span>&bull;</span>
                                        <span><?php echo esc_html(sprintf(_n('%d traveler', '%d travelers', $tour['traveler_count'], 'tourivo'), $tour['traveler_count'])); ?></span>
                                        <span>&bull;</span>
                                        <span><?php echo esc_html(sprintf(_n('%d booking', '%d bookings', $tour['bookings_count'], 'tourivo'), $tour['bookings_count'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Top Hotel Stays / Rooms -->
                <div class="tourivo-card-box">
                    <div class="box-header">
                        <h2>
                            <span class="dashicons dashicons-admin-home" style="color:#8b5cf6;"></span>
                            <?php esc_html_e('Top Hotel Stays & Rooms', 'tourivo'); ?>
                        </h2>
                    </div>
                    <?php if (empty($topItems['rooms'])) : ?>
                        <p class="tourivo-empty-text"><?php esc_html_e('No hotel room bookings found in this period.', 'tourivo'); ?></p>
                    <?php else :
                        $maxRoomSales = max(1.0, (float) ($topItems['rooms'][0]['total_sales'] ?? 1.0));
                    ?>
                        <div class="tourivo-ranked-list">
                            <?php foreach ($topItems['rooms'] as $idx => $room) :
                                $share = min(100.0, round(($room['total_sales'] / $maxRoomSales) * 100.0));
                            ?>
                                <div class="ranked-item">
                                    <div class="ranked-header">
                                        <div class="ranked-title">
                                            <span class="rank-badge rank-badge-room">#<?php echo esc_html((string) ($idx + 1)); ?></span>
                                            <strong><?php echo esc_html($room['item_title']); ?></strong>
                                        </div>
                                        <div class="ranked-sales">
                                            <strong><?php echo esc_html(Money::format($room['total_sales'])); ?></strong>
                                        </div>
                                    </div>
                                    <div class="ranked-progress">
                                        <div class="ranked-progress-fill ranked-fill-room" style="width: <?php echo esc_attr((string) $share); ?>%;"></div>
                                    </div>
                                    <div class="ranked-meta">
                                        <span><?php echo esc_html(sprintf(_n('%d night/unit', '%d nights/units', $room['units_sold'], 'tourivo'), $room['units_sold'])); ?></span>
                                        <span>&bull;</span>
                                        <span><?php echo esc_html(sprintf(_n('%d guest', '%d guests', $room['traveler_count'], 'tourivo'), $room['traveler_count'])); ?></span>
                                        <span>&bull;</span>
                                        <span><?php echo esc_html(sprintf(_n('%d booking', '%d bookings', $room['bookings_count'], 'tourivo'), $room['bookings_count'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
