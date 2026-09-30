<?php

declare(strict_types=1);

namespace Tourivo\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BookingsTable
 *
 * Displays, filters, exports (CSV), and manages manual customer bookings in WordPress Admin.
 *
 * @package Tourivo\Admin
 */
class BookingsTable
{
    /**
     * Render the bookings management view.
     *
     * @return void
     */
    public static function render(): void
    {
        global $wpdb;

        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';
        $statusFilter  = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'all';
        $searchQuery   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        // Build SQL
        $where = ['1=1'];
        $params = [];

        if ($statusFilter !== 'all') {
            $where[] = 'booking_status = %s';
            $params[] = $statusFilter;
        }

        if (!empty($searchQuery)) {
            $where[] = '(booking_code LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)';
            $like = '%' . $wpdb->esc_like($searchQuery) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT * FROM {$bookingsTable} WHERE {$whereSql} ORDER BY id DESC LIMIT 50";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $bookings = $wpdb->get_results($sql);
        $currencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');
        $nonce = wp_create_nonce('tourivo_admin_nonce');

        // Fetch available Tours and Rooms for the Manual Booking Modal
        $tours = get_posts(['post_type' => 'tourivo_tour', 'post_status' => 'publish', 'posts_per_page' => -1]);
        $rooms = get_posts(['post_type' => 'tourivo_room', 'post_status' => 'publish', 'posts_per_page' => -1]);

        $exportUrl = wp_nonce_url(
            admin_url('admin-post.php?action=tourivo_export_bookings_csv&status=' . urlencode($statusFilter)),
            'tourivo_export_csv_action'
        );
        ?>
        <div class="wrap tourivo-admin-wrap">
            <div class="tourivo-page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h1 class="wp-heading-inline" style="margin-right: 12px;"><?php esc_html_e('All Bookings', 'tourivo'); ?></h1>
                    <button type="button" id="tourivo-open-manual-booking-btn" class="page-title-action button-primary">
                        ➕ <?php esc_html_e('Add Manual Booking', 'tourivo'); ?>
                    </button>
                    <a href="<?php echo esc_url($exportUrl); ?>" class="page-title-action button">
                        📥 <?php esc_html_e('Export to CSV', 'tourivo'); ?>
                    </a>
                </div>
            </div>

            <!-- Filter Status Links -->
            <ul class="subsubsub">
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=all')); ?>" class="<?php echo ($statusFilter === 'all') ? 'current' : ''; ?>"><?php esc_html_e('All', 'tourivo'); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=pending')); ?>" class="<?php echo ($statusFilter === 'pending') ? 'current' : ''; ?>"><?php esc_html_e('Pending', 'tourivo'); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=confirmed')); ?>" class="<?php echo ($statusFilter === 'confirmed') ? 'current' : ''; ?>"><?php esc_html_e('Confirmed', 'tourivo'); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=cancelled')); ?>" class="<?php echo ($statusFilter === 'cancelled') ? 'current' : ''; ?>"><?php esc_html_e('Cancelled', 'tourivo'); ?></a></li>
            </ul>

            <!-- Search Form -->
            <form method="get" class="search-box" style="margin-bottom: 12px; float: right;">
                <input type="hidden" name="page" value="tourivo-bookings">
                <?php if ($statusFilter !== 'all') : ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr($statusFilter); ?>">
                <?php endif; ?>
                <input type="search" name="s" value="<?php echo esc_attr($searchQuery); ?>" placeholder="<?php esc_attr_e('Search bookings...', 'tourivo'); ?>">
                <input type="submit" class="button" value="<?php esc_attr_e('Search', 'tourivo'); ?>">
            </form>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="width: 130px;"><?php esc_html_e('Booking Code', 'tourivo'); ?></th>
                        <th><?php esc_html_e('Customer', 'tourivo'); ?></th>
                        <th><?php esc_html_e('Item & Dates', 'tourivo'); ?></th>
                        <th><?php esc_html_e('Total Amount', 'tourivo'); ?></th>
                        <th><?php esc_html_e('Payment', 'tourivo'); ?></th>
                        <th><?php esc_html_e('Booking Status', 'tourivo'); ?></th>
                        <th style="width: 180px;"><?php esc_html_e('Quick Actions', 'tourivo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($bookings)) : foreach ($bookings as $b) : 
                        $lineItem = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$itemsTable} WHERE booking_id = %d LIMIT 1", $b->id));
                    ?>
                        <tr id="booking-row-<?php echo esc_attr((string)$b->id); ?>">
                            <td>
                                <strong>#<?php echo esc_html($b->booking_code); ?></strong>
                                <br><small style="color:#64748b;"><?php echo esc_html(gmdate('M d, Y H:i', strtotime($b->created_at))); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($b->customer_name); ?></strong>
                                <br><a href="mailto:<?php echo esc_attr($b->customer_email); ?>"><?php echo esc_html($b->customer_email); ?></a>
                                <?php if ($b->customer_phone) : ?>
                                    <br><small><?php echo esc_html($b->customer_phone); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lineItem) : ?>
                                    <strong><?php echo esc_html($lineItem->item_title); ?></strong>
                                    <br><small><?php echo esc_html(sprintf(__('Date: %s', 'tourivo'), gmdate('M d, Y', strtotime($lineItem->check_in)))); ?>
                                    <?php if ($lineItem->check_out) echo esc_html(' &rarr; ' . gmdate('M d, Y', strtotime($lineItem->check_out))); ?>
                                    | <?php echo esc_html(sprintf(__('%d Travelers', 'tourivo'), $lineItem->quantity)); ?></small>
                                <?php else : ?>
                                    <span style="color:#94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html(\Tourivo\Support\Money::format((float)$b->total_amount)); ?></strong>
                            </td>
                            <td>
                                <span class="tourivo-badge tourivo-badge-<?php echo esc_attr($b->payment_status); ?>">
                                    <?php echo esc_html(strtoupper($b->payment_method) . ' (' . ucfirst($b->payment_status) . ')'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="tourivo-badge tourivo-badge-<?php echo esc_attr($b->booking_status); ?>" id="status-badge-<?php echo esc_attr((string)$b->id); ?>">
                                    <?php echo esc_html(ucfirst($b->booking_status)); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons-group">
                                    <button type="button" class="button button-small open-timeline-btn" data-id="<?php echo esc_attr((string)$b->id); ?>" data-code="<?php echo esc_attr($b->booking_code); ?>" data-name="<?php echo esc_attr($b->customer_name); ?>" data-email="<?php echo esc_attr($b->customer_email); ?>" data-phone="<?php echo esc_attr($b->customer_phone); ?>" data-item="<?php echo esc_attr($b->item_title ?? ''); ?>" data-checkin="<?php echo esc_attr($b->check_in ?? ''); ?>" data-checkout="<?php echo esc_attr($b->check_out ?? ''); ?>" data-amount="<?php echo esc_attr(\Tourivo\Support\Money::format((float)$b->total_amount)); ?>" data-status="<?php echo esc_attr($b->booking_status); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_timeline_nonce')); ?>" title="<?php esc_attr_e('View Booking Details & Timeline Notes', 'tourivo'); ?>">
                                        👁️ <?php esc_html_e('Details', 'tourivo'); ?>
                                    </button>
                                    <?php if ($b->booking_status !== 'confirmed') : ?>
                                        <button type="button" class="button button-small button-primary change-status-btn" data-id="<?php echo esc_attr((string)$b->id); ?>" data-status="confirmed" data-nonce="<?php echo esc_attr($nonce); ?>">✓ <?php esc_html_e('Confirm', 'tourivo'); ?></button>
                                    <?php endif; ?>
                                    <?php if ($b->booking_status !== 'cancelled') : ?>
                                        <button type="button" class="button button-small button-link-delete change-status-btn" data-id="<?php echo esc_attr((string)$b->id); ?>" data-status="cancelled" data-nonce="<?php echo esc_attr($nonce); ?>" style="color:#ef4444;"><?php esc_html_e('Cancel', 'tourivo'); ?></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: #64748b;">
                                <?php esc_html_e('No bookings found matching criteria.', 'tourivo'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Manual Booking Modal -->
            <div id="tourivo-manual-booking-modal" class="tourivo-admin-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-dialog">
                    <div class="modal-header">
                        <h2>➕ <?php esc_html_e('Create Manual Booking (Phone / WhatsApp / Walk-in)', 'tourivo'); ?></h2>
                        <button type="button" class="close-modal-btn">&times;</button>
                    </div>
                    <form id="tourivo-manual-booking-form">
                        <div class="modal-body">
                            <div class="tourivo-form-group">
                                <label for="manual_item_select"><strong><?php esc_html_e('Select Tour or Hotel Room *', 'tourivo'); ?></strong></label>
                                <select name="item_id" id="manual_item_select" required style="width: 100%;">
                                    <option value=""><?php esc_html_e('-- Select Trip or Room --', 'tourivo'); ?></option>
                                    <optgroup label="<?php esc_attr_e('Tour Packages', 'tourivo'); ?>">
                                        <?php foreach ($tours as $t) : ?>
                                            <option value="<?php echo esc_attr((string) $t->ID); ?>" data-type="tour">
                                                🌴 <?php echo esc_html($t->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('Hotel Rooms', 'tourivo'); ?>">
                                        <?php foreach ($rooms as $r) : ?>
                                            <option value="<?php echo esc_attr((string) $r->ID); ?>" data-type="room">
                                                🏨 <?php echo esc_html($r->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                                <input type="hidden" name="item_type" id="manual_item_type" value="tour">
                            </div>

                            <div class="tourivo-row" style="display: flex; gap: 12px;">
                                <div class="tourivo-col" style="flex: 1;">
                                    <label for="manual_check_in"><?php esc_html_e('Check-in Date *', 'tourivo'); ?></label>
                                    <input type="date" name="check_in" id="manual_check_in" required value="<?php echo esc_attr(gmdate('Y-m-d')); ?>" style="width: 100%;">
                                </div>
                                <div class="tourivo-col" style="flex: 1;">
                                    <label for="manual_check_out"><?php esc_html_e('Check-out Date (For Rooms)', 'tourivo'); ?></label>
                                    <input type="date" name="check_out" id="manual_check_out" style="width: 100%;">
                                </div>
                            </div>

                            <div class="tourivo-row" style="display: flex; gap: 12px; margin-top: 10px;">
                                <div class="tourivo-col" style="flex: 1;">
                                    <label for="manual_adults"><?php esc_html_e('Adults *', 'tourivo'); ?></label>
                                    <input type="number" name="adults" id="manual_adults" min="1" value="1" required style="width: 100%;">
                                </div>
                                <div class="tourivo-col" style="flex: 1;">
                                    <label for="manual_children"><?php esc_html_e('Children', 'tourivo'); ?></label>
                                    <input type="number" name="children" id="manual_children" min="0" value="0" style="width: 100%;">
                                </div>
                                <div class="tourivo-col" style="flex: 1;">
                                    <label for="manual_total_amount"><?php esc_html_e('Total Price ($) *', 'tourivo'); ?></label>
                                    <input type="number" step="0.01" min="0" name="total_amount" id="manual_total_amount" placeholder="0.00" required style="width: 100%;">
                                </div>
                            </div>

                            <hr style="margin: 16px 0; border: 0; border-top: 1px solid #e2e8f0;">

                            <div class="tourivo-row" style="display: flex; gap: 12px;">
                                <div class="tourivo-col" style="flex: 1;">
                                    <label for="manual_customer_name"><?php esc_html_e('Customer Full Name *', 'tourivo'); ?></label>
                                    <input type="text" name="customer_name" id="manual_customer_name" required placeholder="John Doe" style="width: 100%;">
                                </div>
                                <div class="tourivo-col" style="flex: 1;">
                                    <label for="manual_customer_email"><?php esc_html_e('Customer Email *', 'tourivo'); ?></label>
                                    <input type="email" name="customer_email" id="manual_customer_email" required placeholder="john@example.com" style="width: 100%;">
                                </div>
                            </div>

                            <div class="tourivo-row" style="display: flex; gap: 12px; margin-top: 10px;">
                                <div class="tourivo-col" style="flex: 1;">
                                    <label for="manual_customer_phone"><?php esc_html_e('Phone / WhatsApp', 'tourivo'); ?></label>
                                    <input type="tel" name="customer_phone" id="manual_customer_phone" placeholder="+123456789" style="width: 100%;">
                                </div>
                                <div class="tourivo-col" style="flex: 1;">
                                    <label for="manual_payment_status"><?php esc_html_e('Payment Status', 'tourivo'); ?></label>
                                    <select name="payment_status" id="manual_payment_status" style="width: 100%;">
                                        <option value="paid"><?php esc_html_e('Paid (Full Cash / Online)', 'tourivo'); ?></option>
                                        <option value="pending"><?php esc_html_e('Pending (Pay on Arrival)', 'tourivo'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="tourivo-form-group" style="margin-top: 10px;">
                                <label for="manual_customer_notes"><?php esc_html_e('Internal Notes', 'tourivo'); ?></label>
                                <textarea name="customer_notes" id="manual_customer_notes" rows="2" placeholder="<?php esc_attr_e('e.g. Phone booking confirmed by agent.', 'tourivo'); ?>" style="width: 100%;"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                            <button type="button" class="button close-modal-btn"><?php esc_html_e('Cancel', 'tourivo'); ?></button>
                            <button type="submit" id="manual-booking-submit-btn" class="button button-primary">
                                ✓ <?php esc_html_e('Confirm & Create Booking', 'tourivo'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Booking Details & Timeline Notes Modal -->
            <div id="tourivo-booking-timeline-modal" class="tourivo-admin-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-dialog" style="max-width: 680px;">
                    <div class="modal-header">
                        <h2>📋 <span id="timeline-modal-code">Booking #</span></h2>
                        <button type="button" class="close-modal-btn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- Summary Grid -->
                        <div class="timeline-summary-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; font-size: 13px;">
                            <div><strong><?php esc_html_e('Customer:', 'tourivo'); ?></strong> <span id="timeline-modal-name"></span></div>
                            <div><strong><?php esc_html_e('Email:', 'tourivo'); ?></strong> <span id="timeline-modal-email"></span></div>
                            <div><strong><?php esc_html_e('Phone:', 'tourivo'); ?></strong> <span id="timeline-modal-phone"></span></div>
                            <div><strong><?php esc_html_e('Total Amount:', 'tourivo'); ?></strong> <span id="timeline-modal-amount" style="color: #16a34a; font-weight: 700;"></span></div>
                            <div style="grid-column: 1 / -1;"><strong><?php esc_html_e('Trip / Room:', 'tourivo'); ?></strong> <span id="timeline-modal-item"></span></div>
                        </div>

                        <!-- Activity Timeline -->
                        <h3 style="margin: 0 0 10px 0; font-size: 15px;">⏱️ <?php esc_html_e('Activity History & Internal Notes', 'tourivo'); ?></h3>
                        <div id="timeline-logs-container" style="max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                            <div class="timeline-loading" style="text-align: center; color: #64748b;">Loading activity logs...</div>
                        </div>

                        <!-- Add Note Form -->
                        <form id="tourivo-add-note-form">
                            <input type="hidden" name="booking_id" id="timeline-booking-id" value="">
                            <div class="tourivo-form-group">
                                <label for="internal-note-text"><strong><?php esc_html_e('Add Internal Staff Note', 'tourivo'); ?></strong></label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="text" name="note" id="internal-note-text" required placeholder="<?php esc_attr_e('e.g. Customer requested early check-in or confirmed passport.', 'tourivo'); ?>" style="flex: 1;">
                                    <button type="submit" id="add-note-submit-btn" class="button button-primary">
                                        ➕ <?php esc_html_e('Add Note', 'tourivo'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer" style="display: flex; justify-content: flex-end; padding: 12px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                        <button type="button" class="button close-modal-btn"><?php esc_html_e('Close', 'tourivo'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle CSV Export of bookings.
     */
    public static function exportCsv(): void
    {
        if (!current_user_can('manage_tourivo_bookings')) {
            wp_die(__('Unauthorized access.', 'tourivo'), 403);
        }

        check_admin_referer('tourivo_export_csv_action');

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';
        $statusFilter  = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'all';

        $where = '1=1';
        $params = [];

        if ($statusFilter !== 'all') {
            $where .= ' AND b.booking_status = %s';
            $params[] = $statusFilter;
        }

        $sql = "SELECT b.*, i.item_title, i.item_type, i.check_in, i.check_out, i.quantity 
                FROM {$bookingsTable} b 
                LEFT JOIN {$itemsTable} i ON b.id = i.booking_id 
                WHERE {$where} 
                ORDER BY b.id DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        $filename = 'tourivo-bookings-manifest-' . gmdate('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Excel support
        fwrite($output, "\xEF\xBB\xBF");

        // Header Row
        fputcsv($output, [
            __('Booking Code', 'tourivo'),
            __('Customer Name', 'tourivo'),
            __('Customer Email', 'tourivo'),
            __('Customer Phone', 'tourivo'),
            __('Trip / Stay Title', 'tourivo'),
            __('Type', 'tourivo'),
            __('Check-in Date', 'tourivo'),
            __('Check-out Date', 'tourivo'),
            __('Travelers', 'tourivo'),
            __('Total Amount', 'tourivo'),
            __('Payment Method', 'tourivo'),
            __('Payment Status', 'tourivo'),
            __('Booking Status', 'tourivo'),
            __('Booking Created At', 'tourivo'),
        ]);

        $escapeCell = static function (mixed $val): string {
            $str = (string) ($val ?? '');
            if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $str;
            }
            return $str;
        };

        foreach ($results as $row) {
            fputcsv($output, [
                $escapeCell($row['booking_code'] ?? ''),
                $escapeCell($row['customer_name'] ?? ''),
                $escapeCell($row['customer_email'] ?? ''),
                $escapeCell($row['customer_phone'] ?? ''),
                $escapeCell($row['item_title'] ?? 'N/A'),
                $escapeCell(ucfirst((string) ($row['item_type'] ?? 'Tour'))),
                $escapeCell($row['check_in'] ?? ''),
                $escapeCell($row['check_out'] ?? ''),
                $escapeCell((string) ($row['quantity'] ?? '1')),
                $escapeCell(number_format((float) ($row['total_amount'] ?? 0), 2, '.', '')),
                $escapeCell($row['payment_method'] ?? ''),
                $escapeCell($row['payment_status'] ?? ''),
                $escapeCell($row['booking_status'] ?? ''),
                $escapeCell($row['created_at'] ?? ''),
            ]);
        }

        fclose($output);
        exit;
    }
}
