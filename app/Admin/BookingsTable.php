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
    public const PER_PAGE = 20;

    public const STATUS_FILTERS = ['all', 'pending', 'confirmed', 'on_hold', 'completed', 'cancelled', 'cancel_requested'];

    /**
     * Fetch one page of bookings (newest first) plus the first line item of each, in two queries.
     *
     * @param string $status  One of STATUS_FILTERS.
     * @param string $search  Free text matched against code, name, email and phone.
     * @param int    $page    1-based page number.
     * @param int    $perPage Rows per page.
     * @return array{bookings: array<int, object>, total: int, items: array<int, object>}
     */
    public static function fetchPage(string $status, string $search, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        global $wpdb;

        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        $where  = ['1=1'];
        $params = [];

        if ($status === 'cancel_requested') {
            $where[] = "cancel_requested_at IS NOT NULL AND booking_status != 'cancelled'";
        } elseif ($status !== 'all' && in_array($status, self::STATUS_FILTERS, true)) {
            $where[]  = 'booking_status = %s';
            $params[] = $status;
        }

        if ($search !== '') {
            $where[] = '(booking_code LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)';
            $like    = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);
        $page     = max(1, $page);
        $perPage  = max(1, $perPage);
        $offset   = ($page - 1) * $perPage;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $countSql = "SELECT COUNT(*) FROM {$bookingsTable} WHERE {$whereSql}";
        $total    = (int) $wpdb->get_var(empty($params) ? $countSql : $wpdb->prepare($countSql, ...$params));

        $rowsSql  = "SELECT * FROM {$bookingsTable} WHERE {$whereSql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $bookings = (array) $wpdb->get_results($wpdb->prepare($rowsSql, ...array_merge($params, [$perPage, $offset])));

        $items = [];
        $ids   = array_values(array_filter(array_map(static fn ($b): int => (int) $b->id, $bookings)));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $rows = (array) $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$itemsTable} WHERE booking_id IN ({$placeholders}) ORDER BY id ASC",
                ...$ids
            ));
            foreach ($rows as $row) {
                $items[(int) $row->booking_id] ??= $row; // first line item per booking
            }
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return ['bookings' => $bookings, 'total' => $total, 'items' => $items];
    }

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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $statusFilter  = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'all';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $searchQuery   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        if (!in_array($statusFilter, self::STATUS_FILTERS, true)) {
            $statusFilter = 'all';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page     = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $perPage  = self::PER_PAGE;
        $result   = self::fetchPage($statusFilter, $searchQuery, $page, $perPage);
        $bookings = $result['bookings'];
        $total    = $result['total'];
        $itemsByBooking = $result['items'];
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
                    <button type="button" id="tourivo-open-export-modal-btn" class="page-title-action button tourivo-open-export-modal-btn" data-status="<?php echo esc_attr($statusFilter); ?>" data-search="<?php echo esc_attr($searchQuery); ?>">
                        📥 <?php esc_html_e('Export Bookings', 'tourivo'); ?>
                    </button>
                </div>
            </div>

            <!-- Filter Status Links -->
            <ul class="subsubsub">
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=all')); ?>" class="<?php echo ($statusFilter === 'all') ? 'current' : ''; ?>"><?php esc_html_e('All', 'tourivo'); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=pending')); ?>" class="<?php echo ($statusFilter === 'pending') ? 'current' : ''; ?>"><?php esc_html_e('Pending', 'tourivo'); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=confirmed')); ?>" class="<?php echo ($statusFilter === 'confirmed') ? 'current' : ''; ?>"><?php esc_html_e('Confirmed', 'tourivo'); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=cancel_requested')); ?>" class="<?php echo ($statusFilter === 'cancel_requested') ? 'current' : ''; ?>"><?php esc_html_e('🚨 Cancel Requested', 'tourivo'); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=on_hold')); ?>" class="<?php echo ($statusFilter === 'on_hold') ? 'current' : ''; ?>"><?php esc_html_e('On Hold', 'tourivo'); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-bookings&status=completed')); ?>" class="<?php echo ($statusFilter === 'completed') ? 'current' : ''; ?>"><?php esc_html_e('Completed', 'tourivo'); ?></a> |</li>
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
                    <?php /** @var array<int, \stdClass> $bookings */ ?>
                    <?php if (!empty($bookings)) : foreach ($bookings as $b) : 
                        $lineItem = $itemsByBooking[(int) $b->id] ?? null;
                        if ($lineItem) {
                            // Expose the item to the "Details" modal data-attributes below.
                            $b->item_title = $lineItem->item_title;
                            $b->check_in   = $lineItem->check_in;
                            $b->check_out  = $lineItem->check_out;
                        }
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
                                    <br><small><?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: Booking check-in date */
                                            __('Date: %s', 'tourivo'),
                                            gmdate('M d, Y', strtotime($lineItem->check_in))
                                        )
                                    );
                                    if ($lineItem->check_out) {
                                        echo esc_html(' &rarr; ' . gmdate('M d, Y', strtotime($lineItem->check_out)));
                                    }
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %d: Quantity of travelers */
                                            __(' | %d Travelers', 'tourivo'),
                                            $lineItem->quantity
                                        )
                                    );
                                    ?></small>
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
                                <?php if (!empty($b->cancel_requested_at) && $b->booking_status !== 'cancelled') : ?>
                                    <br><span class="tourivo-badge" style="background:#fee2e2; color:#991b1b; margin-top:4px; display:inline-block; font-size: var(--trv-admin-text-xs);">🚨 <?php esc_html_e('Cancel Requested', 'tourivo'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons-group">
                                    <button type="button" class="button button-small open-timeline-btn" data-id="<?php echo esc_attr((string)$b->id); ?>" data-code="<?php echo esc_attr($b->booking_code); ?>" data-name="<?php echo esc_attr($b->customer_name); ?>" data-email="<?php echo esc_attr($b->customer_email); ?>" data-phone="<?php echo esc_attr($b->customer_phone); ?>" data-item="<?php echo esc_attr($b->item_title ?? ''); ?>" data-checkin="<?php echo esc_attr($b->check_in ?? ''); ?>" data-checkout="<?php echo esc_attr($b->check_out ?? ''); ?>" data-amount="<?php echo esc_attr(\Tourivo\Support\Money::format((float)$b->total_amount)); ?>" data-status="<?php echo esc_attr($b->booking_status); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_timeline_nonce')); ?>" title="<?php esc_attr_e('View Booking Details & Timeline Notes', 'tourivo'); ?>">
                                        👁️ <?php esc_html_e('Details', 'tourivo'); ?>
                                    </button>
                                    <a href="<?php echo esc_url(add_query_arg(['action' => 'tourivo_print_voucher', 'code' => $b->booking_code], admin_url('admin-post.php'))); ?>" target="_blank" rel="noopener" class="button button-small" title="<?php esc_attr_e('Print Booking Voucher', 'tourivo'); ?>">
                                        🖨️ <?php esc_html_e('Voucher', 'tourivo'); ?>
                                    </a>
                                    <?php do_action('tourivo_booking_row_actions', $b); ?>
                                    <button type="button" class="button button-small tourivo-open-payment-modal-btn" data-id="<?php echo esc_attr((string)$b->id); ?>" data-code="<?php echo esc_attr($b->booking_code); ?>" data-customer="<?php echo esc_attr($b->customer_name); ?>" data-total="<?php echo esc_attr((string)$b->total_amount); ?>" data-paid="<?php echo esc_attr((string)($b->paid_amount ?? 0)); ?>" data-due="<?php echo esc_attr((string)($b->due_amount ?? $b->total_amount)); ?>" data-payment-status="<?php echo esc_attr($b->payment_status); ?>" data-payment-method="<?php echo esc_attr($b->payment_method); ?>" data-transaction-id="<?php echo esc_attr($b->transaction_id ?? ''); ?>" data-booking-status="<?php echo esc_attr($b->booking_status); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" title="<?php esc_attr_e('Record received offline payment or mark paid', 'tourivo'); ?>">
                                        💳 <?php esc_html_e('Payment', 'tourivo'); ?>
                                    </button>
                                    <button type="button" class="button button-small revoke-links-btn" data-id="<?php echo esc_attr((string)$b->id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" title="<?php esc_attr_e('Invalidate previously shared voucher / calendar links', 'tourivo'); ?>">🔒 <?php esc_html_e('Revoke Links', 'tourivo'); ?></button>
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

            <?php
            $totalPages = (int) ceil($total / $perPage);
            if ($totalPages > 1) :
                $pageLinks = paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'current'   => $page,
                    'total'     => $totalPages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ]);
                ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(sprintf(
                            /* translators: %s: Number of bookings */
                            _n('%s booking', '%s bookings', $total, 'tourivo'),
                            number_format_i18n($total)
                        )); ?></span>
                        <?php echo wp_kses_post((string) $pageLinks); ?>
                    </div>
                </div>
            <?php endif; ?>

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
                        <div class="timeline-summary-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; font-size: var(--trv-admin-text-sm);">
                            <div><strong><?php esc_html_e('Customer:', 'tourivo'); ?></strong> <span id="timeline-modal-name"></span></div>
                            <div><strong><?php esc_html_e('Email:', 'tourivo'); ?></strong> <span id="timeline-modal-email"></span></div>
                            <div><strong><?php esc_html_e('Phone:', 'tourivo'); ?></strong> <span id="timeline-modal-phone"></span></div>
                            <div><strong><?php esc_html_e('Total Amount:', 'tourivo'); ?></strong> <span id="timeline-modal-amount" style="color: #16a34a; font-weight: 700;"></span></div>
                            <div style="grid-column: 1 / -1;"><strong><?php esc_html_e('Trip / Room:', 'tourivo'); ?></strong> <span id="timeline-modal-item"></span></div>
                        </div>

                        <!-- Activity Timeline -->
                        <h3 style="margin: 0 0 10px 0; font-size: var(--trv-admin-text-lg);">⏱️ <?php esc_html_e('Activity History & Internal Notes', 'tourivo'); ?></h3>
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

            <!-- Export & Reporting Modal -->
            <?php self::renderExportModal($tours, $rooms, $statusFilter, $searchQuery); ?>
        </div>
        <?php
    }

    /**
     * Render the Export Bookings & Reporting Modal.
     *
     * @param array<int, \WP_Post> $tours
     * @param array<int, \WP_Post> $rooms
     * @param string               $defaultStatus
     * @param string               $defaultSearch
     * @return void
     */
    public static function renderExportModal(array $tours = [], array $rooms = [], string $defaultStatus = 'all', string $defaultSearch = ''): void
    {
        $nonce = wp_create_nonce('tourivo_export_csv_action');
        ?>
        <div id="tourivo-export-modal" class="tourivo-admin-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-dialog" style="max-width: 650px;">
                <div class="modal-header">
                    <h2>📥 <?php esc_html_e('Export Bookings & Generate Reports', 'tourivo'); ?></h2>
                    <button type="button" class="close-modal-btn">&times;</button>
                </div>
                <form id="tourivo-export-form" method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="tourivo_export_bookings_csv">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">

                    <div class="modal-body">
                        <!-- Date Preset Quick Filters -->
                        <div class="tourivo-form-group">
                            <label><strong><?php esc_html_e('Date Range Preset', 'tourivo'); ?></strong></label>
                            <div class="export-date-presets" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                                <button type="button" class="button button-small export-preset-btn active" data-range="all"><?php esc_html_e('All Time', 'tourivo'); ?></button>
                                <button type="button" class="button button-small export-preset-btn" data-range="today"><?php esc_html_e('Today', 'tourivo'); ?></button>
                                <button type="button" class="button button-small export-preset-btn" data-range="7days"><?php esc_html_e('Last 7 Days', 'tourivo'); ?></button>
                                <button type="button" class="button button-small export-preset-btn" data-range="this_month"><?php esc_html_e('This Month', 'tourivo'); ?></button>
                                <button type="button" class="button button-small export-preset-btn" data-range="last_month"><?php esc_html_e('Last Month', 'tourivo'); ?></button>
                                <button type="button" class="button button-small export-preset-btn" data-range="custom"><?php esc_html_e('Custom Range', 'tourivo'); ?></button>
                            </div>
                        </div>

                        <!-- Date Criteria & Custom Range -->
                        <div class="tourivo-row" style="display: flex; gap: 12px; margin-top: 10px;">
                            <div class="tourivo-col" style="flex: 1;">
                                <label for="export_date_type"><?php esc_html_e('Filter Dates By', 'tourivo'); ?></label>
                                <select name="date_type" id="export_date_type" style="width: 100%;">
                                    <option value="booking_date"><?php esc_html_e('Booking Date (Created)', 'tourivo'); ?></option>
                                    <option value="travel_date"><?php esc_html_e('Travel Date (Check-in)', 'tourivo'); ?></option>
                                </select>
                            </div>
                            <div class="tourivo-col" style="flex: 1;">
                                <label for="export_start_date"><?php esc_html_e('Start Date', 'tourivo'); ?></label>
                                <input type="date" name="start_date" id="export_start_date" style="width: 100%;">
                            </div>
                            <div class="tourivo-col" style="flex: 1;">
                                <label for="export_end_date"><?php esc_html_e('End Date', 'tourivo'); ?></label>
                                <input type="date" name="end_date" id="export_end_date" style="width: 100%;">
                            </div>
                        </div>

                        <!-- Status & Payment Status -->
                        <div class="tourivo-row" style="display: flex; gap: 12px; margin-top: 12px;">
                            <div class="tourivo-col" style="flex: 1;">
                                <label for="export_status"><?php esc_html_e('Booking Status', 'tourivo'); ?></label>
                                <select name="status" id="export_status" style="width: 100%;">
                                    <option value="all" <?php selected($defaultStatus, 'all'); ?>><?php esc_html_e('All Statuses', 'tourivo'); ?></option>
                                    <option value="confirmed" <?php selected($defaultStatus, 'confirmed'); ?>><?php esc_html_e('Confirmed', 'tourivo'); ?></option>
                                    <option value="pending" <?php selected($defaultStatus, 'pending'); ?>><?php esc_html_e('Pending', 'tourivo'); ?></option>
                                    <option value="cancel_requested" <?php selected($defaultStatus, 'cancel_requested'); ?>><?php esc_html_e('Cancel Requested', 'tourivo'); ?></option>
                                    <option value="on_hold" <?php selected($defaultStatus, 'on_hold'); ?>><?php esc_html_e('On Hold', 'tourivo'); ?></option>
                                    <option value="completed" <?php selected($defaultStatus, 'completed'); ?>><?php esc_html_e('Completed', 'tourivo'); ?></option>
                                    <option value="cancelled" <?php selected($defaultStatus, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'tourivo'); ?></option>
                                </select>
                            </div>
                            <div class="tourivo-col" style="flex: 1;">
                                <label for="export_payment_status"><?php esc_html_e('Payment Status', 'tourivo'); ?></label>
                                <select name="payment_status" id="export_payment_status" style="width: 100%;">
                                    <option value="all"><?php esc_html_e('All Payment Statuses', 'tourivo'); ?></option>
                                    <option value="paid"><?php esc_html_e('Paid', 'tourivo'); ?></option>
                                    <option value="pending"><?php esc_html_e('Pending (Unpaid)', 'tourivo'); ?></option>
                                    <option value="refunded"><?php esc_html_e('Refunded', 'tourivo'); ?></option>
                                    <option value="failed"><?php esc_html_e('Failed', 'tourivo'); ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- Item / Tour & Search -->
                        <div class="tourivo-row" style="display: flex; gap: 12px; margin-top: 12px;">
                            <div class="tourivo-col" style="flex: 1;">
                                <label for="export_item_id"><?php esc_html_e('Specific Tour or Room', 'tourivo'); ?></label>
                                <select name="item_id" id="export_item_id" style="width: 100%;">
                                    <option value="0"><?php esc_html_e('-- All Tours & Rooms --', 'tourivo'); ?></option>
                                    <?php if (!empty($tours)) : ?>
                                        <optgroup label="<?php esc_attr_e('Tour Packages', 'tourivo'); ?>">
                                            <?php foreach ($tours as $t) : ?>
                                                <option value="<?php echo esc_attr((string) $t->ID); ?>">🌴 <?php echo esc_html($t->post_title); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <?php if (!empty($rooms)) : ?>
                                        <optgroup label="<?php esc_attr_e('Hotel Rooms', 'tourivo'); ?>">
                                            <?php foreach ($rooms as $r) : ?>
                                                <option value="<?php echo esc_attr((string) $r->ID); ?>">🏨 <?php echo esc_html($r->post_title); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="tourivo-col" style="flex: 1;">
                                <label for="export_search"><?php esc_html_e('Search Keyword', 'tourivo'); ?></label>
                                <input type="text" name="s" id="export_search" value="<?php echo esc_attr($defaultSearch); ?>" placeholder="<?php esc_attr_e('Name, email, phone, code...', 'tourivo'); ?>" style="width: 100%;">
                            </div>
                        </div>

                        <!-- Report Format / Type -->
                        <div class="tourivo-form-group" style="margin-top: 14px;">
                            <label><strong><?php esc_html_e('Report Format & Detail Level', 'tourivo'); ?></strong></label>
                            <div class="export-report-type-selector" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px;">
                                <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; background: #f8fafc; padding: 10px 14px; border-radius: 6px; border: 1px solid #cbd5e1; flex: 1; min-width: 180px;">
                                    <input type="radio" name="export_type" value="bookings" checked style="margin-top: 3px;">
                                    <span><strong><?php esc_html_e('Bookings List', 'tourivo'); ?></strong><br><small style="color: #64748b;"><?php esc_html_e('Standard manifest with customer & financial totals', 'tourivo'); ?></small></span>
                                </label>
                                <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; background: #f8fafc; padding: 10px 14px; border-radius: 6px; border: 1px solid #cbd5e1; flex: 1; min-width: 180px;">
                                    <input type="radio" name="export_type" value="items" style="margin-top: 3px;">
                                    <span><strong><?php esc_html_e('Line-Item Manifest', 'tourivo'); ?></strong><br><small style="color: #64748b;"><?php esc_html_e('One row per room/tour line with travelers breakdown', 'tourivo'); ?></small></span>
                                </label>
                                <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; background: #f8fafc; padding: 10px 14px; border-radius: 6px; border: 1px solid #cbd5e1; flex: 1; min-width: 180px;">
                                    <input type="radio" name="export_type" value="financial_summary" style="margin-top: 3px;">
                                    <span><strong><?php esc_html_e('Financial Summary', 'tourivo'); ?></strong><br><small style="color: #64748b;"><?php esc_html_e('Aggregated metrics: Gross value, taxes & net revenue', 'tourivo'); ?></small></span>
                                </label>
                            </div>
                        </div>

                        <!-- Live Match Preview Badge -->
                        <div id="tourivo-export-preview-badge" style="margin-top: 14px; padding: 12px 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; font-size: var(--trv-admin-text-sm);">
                            <span>📊 <strong id="export-preview-count">...</strong> <?php esc_html_e('matching bookings found', 'tourivo'); ?> (<span id="export-preview-revenue">...</span>)</span>
                            <span style="color: #166534; font-size: var(--trv-admin-text-xs); font-weight: 600;"><?php esc_html_e('UTF-8 Excel Ready', 'tourivo'); ?></span>
                        </div>
                    </div>

                    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                        <button type="button" class="button close-modal-btn"><?php esc_html_e('Cancel', 'tourivo'); ?></button>
                        <button type="submit" id="tourivo-submit-export-btn" class="button button-primary">
                            📥 <?php esc_html_e('Download CSV Report', 'tourivo'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 3. Record Offline Payment Modal -->
        <div id="tourivo-record-payment-modal" class="tourivo-modal-wrap" style="display: none;">
            <div class="tourivo-modal-overlay"></div>
            <div class="tourivo-modal-content" style="max-width: 520px; padding: 0; overflow: hidden; border-radius: 8px;">
                <div class="modal-header" style="background: #0f172a; color: #ffffff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; color: #ffffff; font-size: var(--trv-admin-text-lg);">💳 <?php esc_html_e('Record Payment', 'tourivo'); ?> &mdash; <span id="payment-modal-booking-code">#...</span></h3>
                    <button type="button" class="close-modal-btn" style="background: none; border: none; color: #ffffff; font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
                </div>
                <form id="tourivo-record-payment-form">
                    <input type="hidden" name="booking_id" id="payment-modal-booking-id" value="0">
                    <input type="hidden" name="nonce" id="payment-modal-nonce" value="">

                    <div class="modal-body" style="padding: 20px;">
                        <!-- Booking financial summary banner -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; margin-bottom: 16px; font-size: var(--trv-admin-text-sm);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span><?php esc_html_e('Customer:', 'tourivo'); ?></span>
                                <strong id="payment-modal-customer">—</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span><?php esc_html_e('Total Amount:', 'tourivo'); ?></span>
                                <strong id="payment-modal-total">$0.00</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: #15803d; margin-bottom: 4px;">
                                <span><?php esc_html_e('Already Paid:', 'tourivo'); ?></span>
                                <strong id="payment-modal-already-paid">$0.00</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: #b45309; font-weight: 600;">
                                <span><?php esc_html_e('Current Due:', 'tourivo'); ?></span>
                                <strong id="payment-modal-due">$0.00</strong>
                            </div>
                        </div>

                        <!-- Amount to Record -->
                        <div class="tourivo-form-group" style="margin-bottom: 14px;">
                            <label for="payment-amount" style="display: block; font-weight: 600; margin-bottom: 4px;"><?php esc_html_e('Payment Amount to Record', 'tourivo'); ?> *</label>
                            <input type="number" step="0.01" min="0" name="amount" id="payment-amount" class="widefat" required style="font-size: var(--trv-admin-text-lg); font-weight: 600;">
                            <p class="description" style="margin-top: 4px;"><?php esc_html_e('Defaults to the full or remaining due balance.', 'tourivo'); ?></p>
                        </div>

                        <!-- Payment Status -->
                        <div class="tourivo-row" style="display: flex; gap: 12px; margin-bottom: 14px;">
                            <div class="tourivo-col" style="flex: 1;">
                                <label for="payment-status-select" style="display: block; font-weight: 600; margin-bottom: 4px;"><?php esc_html_e('Payment Status', 'tourivo'); ?></label>
                                <select name="payment_status" id="payment-status-select" style="width: 100%;">
                                    <option value="paid"><?php esc_html_e('Paid (Settled)', 'tourivo'); ?></option>
                                    <option value="partially_paid"><?php esc_html_e('Partially Paid (Deposit)', 'tourivo'); ?></option>
                                    <option value="pending"><?php esc_html_e('Pending (Unpaid)', 'tourivo'); ?></option>
                                    <option value="refunded"><?php esc_html_e('Refunded', 'tourivo'); ?></option>
                                </select>
                            </div>
                            <div class="tourivo-col" style="flex: 1;">
                                <label for="payment-method-select" style="display: block; font-weight: 600; margin-bottom: 4px;"><?php esc_html_e('Payment Method', 'tourivo'); ?></label>
                                <select name="payment_method" id="payment-method-select" style="width: 100%;">
                                    <option value="bank_transfer"><?php esc_html_e('Bank Transfer', 'tourivo'); ?></option>
                                    <option value="cash_on_arrival"><?php esc_html_e('Cash on Arrival', 'tourivo'); ?></option>
                                    <option value="offline"><?php esc_html_e('Manual / Other Offline', 'tourivo'); ?></option>
                                    <option value="pos_counter"><?php esc_html_e('POS / Reception Counter', 'tourivo'); ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- Transaction Reference / ID -->
                        <div class="tourivo-form-group" style="margin-bottom: 14px;">
                            <label for="payment-txn-id" style="display: block; font-weight: 600; margin-bottom: 4px;"><?php esc_html_e('Transaction / Deposit Reference', 'tourivo'); ?></label>
                            <input type="text" name="transaction_id" id="payment-txn-id" class="widefat" placeholder="<?php esc_attr_e('e.g. BANK-TXN-882299, Slip #, bKash TrxID', 'tourivo'); ?>">
                        </div>

                        <!-- Auto-confirm pending booking checkbox -->
                        <div class="tourivo-form-group" id="payment-auto-confirm-wrap" style="margin-bottom: 14px; background: #eff6ff; padding: 10px 12px; border-radius: 6px; border: 1px solid #bfdbfe;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: var(--trv-admin-text-sm); font-weight: 500;">
                                <input type="checkbox" name="auto_confirm" id="payment-auto-confirm" value="1" checked>
                                <span><?php esc_html_e('Automatically confirm reservation if currently in Pending status', 'tourivo'); ?></span>
                            </label>
                        </div>

                        <!-- Internal Audit Note -->
                        <div class="tourivo-form-group">
                            <label for="payment-note" style="display: block; font-weight: 600; margin-bottom: 4px;"><?php esc_html_e('Internal Audit Note', 'tourivo'); ?></label>
                            <textarea name="note" id="payment-note" rows="2" class="widefat" placeholder="<?php esc_attr_e('Optional memo (e.g. Verified via City Bank Online portal)', 'tourivo'); ?>"></textarea>
                        </div>

                        <div id="payment-modal-alert" style="display: none; margin-top: 14px; padding: 10px 12px; border-radius: 6px; font-size: var(--trv-admin-text-sm);"></div>
                    </div>

                    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                        <button type="button" class="button close-modal-btn"><?php esc_html_e('Cancel', 'tourivo'); ?></button>
                        <button type="submit" id="tourivo-submit-payment-btn" class="button button-primary">
                            ✓ <?php esc_html_e('Save Payment Record', 'tourivo'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle CSV Export of bookings.
     */
    public static function exportCsv(): void
    {
        \Tourivo\Services\ExportService::handleExportRequest();
    }
}
