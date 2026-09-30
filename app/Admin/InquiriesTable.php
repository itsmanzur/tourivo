<?php

declare(strict_types=1);

namespace Tourivo\Admin;

use Tourivo\Common\Container;
use Tourivo\Services\InquiryService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class InquiriesTable
 *
 * Renders the Admin Trip Inquiries & Consultation Requests management view.
 *
 * @package Tourivo\Admin
 */
class InquiriesTable
{
    /**
     * Render Inquiries table page.
     */
    public static function render(): void
    {
        $inquiryService = Container::getInstance()->get(InquiryService::class);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $statusFilter   = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $inquiries      = $inquiryService->getInquiries(['status' => $statusFilter, 'limit' => 50]);

        global $wpdb;
        $table = $wpdb->prefix . 'tourivo_inquiries';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $countAll     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $countNew     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'new'");
        $countReplied = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'replied'");
        $countClosed  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'closed'");
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $baseUrl = admin_url('admin.php?page=tourivo-inquiries');
        ?>
        <div class="wrap tourivo-admin-wrap">
            <div class="tourivo-page-header">
                <div>
                    <h1 class="wp-heading-inline"><?php esc_html_e('Travel Inquiries & Consultation Requests', 'tourivo'); ?></h1>
                    <p class="description"><?php esc_html_e('Manage leads and custom trip questions submitted by travelers through the "Ask an Expert" inquiry modal.', 'tourivo'); ?></p>
                </div>
            </div>

            <!-- Status Filter Bar -->
            <ul class="subsubsub">
                <li><a href="<?php echo esc_url($baseUrl); ?>" class="<?php echo empty($statusFilter) ? 'current' : ''; ?>"><?php esc_html_e('All', 'tourivo'); ?> <span class="count">(<?php echo esc_html((string)$countAll); ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url($baseUrl . '&status=new'); ?>" class="<?php echo $statusFilter === 'new' ? 'current' : ''; ?>"><?php esc_html_e('New Leads', 'tourivo'); ?> <span class="count">(<?php echo esc_html((string)$countNew); ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url($baseUrl . '&status=replied'); ?>" class="<?php echo $statusFilter === 'replied' ? 'current' : ''; ?>"><?php esc_html_e('Replied', 'tourivo'); ?> <span class="count">(<?php echo esc_html((string)$countReplied); ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url($baseUrl . '&status=closed'); ?>" class="<?php echo $statusFilter === 'closed' ? 'current' : ''; ?>"><?php esc_html_e('Closed', 'tourivo'); ?> <span class="count">(<?php echo esc_html((string)$countClosed); ?>)</span></a></li>
            </ul>

            <table class="wp-list-table widefat fixed striped tourivo-inquiries-table">
                <thead>
                    <tr>
                        <th style="width: 140px;"><?php esc_html_e('Date', 'tourivo'); ?></th>
                        <th style="width: 160px;"><?php esc_html_e('Traveler', 'tourivo'); ?></th>
                        <th style="width: 180px;"><?php esc_html_e('Trip / Stay', 'tourivo'); ?></th>
                        <th style="width: 140px;"><?php esc_html_e('Travel Date & Party', 'tourivo'); ?></th>
                        <th><?php esc_html_e('Inquiry Message', 'tourivo'); ?></th>
                        <th style="width: 110px;"><?php esc_html_e('Status', 'tourivo'); ?></th>
                        <th style="width: 160px;"><?php esc_html_e('Quick Actions', 'tourivo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inquiries)) : ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #64748b;">
                                <?php esc_html_e('No inquiries found.', 'tourivo'); ?>
                            </td>
                        </tr>
                    <?php else : foreach ($inquiries as $inq) :
                        $itemPost = get_post((int) $inq['item_id']);
                        $itemTitle = $itemPost ? $itemPost->post_title : esc_html__('General Inquiry', 'tourivo');
                        $mailtoSubject = rawurlencode(
                            sprintf(
                                /* translators: 1: Item title, 2: Site name */
                                __('Re: Your Inquiry for %1$s — %2$s', 'tourivo'),
                                $itemTitle,
                                get_bloginfo('name')
                            )
                        );
                        $mailtoUrl = 'mailto:' . esc_attr($inq['customer_email']) . '?subject=' . $mailtoSubject;
                        ?>
                        <tr id="inquiry-row-<?php echo esc_attr($inq['id']); ?>">
                            <td>
                                <strong><?php echo esc_html(gmdate('M d, Y', strtotime($inq['created_at']))); ?></strong>
                                <br><small><?php echo esc_html(gmdate('g:i A', strtotime($inq['created_at']))); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($inq['customer_name']); ?></strong>
                                <br><a href="mailto:<?php echo esc_attr($inq['customer_email']); ?>"><?php echo esc_html($inq['customer_email']); ?></a>
                                <?php if (!empty($inq['customer_phone'])) : ?>
                                    <br><small>📞 <?php echo esc_html($inq['customer_phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($itemPost) : ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($itemPost->ID)); ?>" target="_blank">
                                        <strong><?php echo esc_html($itemTitle); ?></strong>
                                    </a>
                                <?php else : ?>
                                    <span><?php echo esc_html($itemTitle); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span>📅 <?php echo esc_html($inq['travel_date'] ?: __('Flexible', 'tourivo')); ?></span>
                                <br><small>👥 <?php echo esc_html((string) $inq['guests']); ?> <?php esc_html_e('Guests', 'tourivo'); ?></small>
                            </td>
                            <td>
                                <div style="max-height: 80px; overflow-y: auto; font-size: 13px; line-height: 1.4; background: #f8fafc; padding: 8px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                    <?php echo nl2br(esc_html($inq['message'])); ?>
                                </div>
                            </td>
                            <td>
                                <select class="change-inquiry-status-select" data-id="<?php echo esc_attr($inq['id']); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_inquiry_nonce')); ?>">
                                    <option value="new" <?php selected($inq['status'], 'new'); ?>><?php esc_html_e('New', 'tourivo'); ?></option>
                                    <option value="replied" <?php selected($inq['status'], 'replied'); ?>><?php esc_html_e('Replied', 'tourivo'); ?></option>
                                    <option value="closed" <?php selected($inq['status'], 'closed'); ?>><?php esc_html_e('Closed', 'tourivo'); ?></option>
                                </select>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($mailtoUrl); ?>" class="button button-small button-primary" target="_blank">
                                    ✉️ <?php esc_html_e('Reply', 'tourivo'); ?>
                                </a>
                                <button type="button" class="button button-small delete-inquiry-btn" data-id="<?php echo esc_attr($inq['id']); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('tourivo_inquiry_nonce')); ?>" style="color: #ef4444;">
                                    <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
