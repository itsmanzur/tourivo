<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Admin\AdminDashboard;
use Tourivo\Admin\BookingsTable;
use Tourivo\Admin\DocumentationPage;
use Tourivo\Admin\InquiriesTable;
use Tourivo\Admin\SettingsPage;
use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\Common\Container;
use Tourivo\Database\Seeder;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\TourPostType;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InquiryService;
use Tourivo\Services\LogService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminServiceProvider
 *
 * Registers admin menus, admin scripts, and dashboard UI hooks.
 *
 * @package Tourivo\Providers
 */
class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin controllers & service bindings
    }

    public function boot(): void
    {
        // Public & Admin AJAX for Inquiry submission
        add_action('wp_ajax_tourivo_submit_inquiry', [$this, 'handleSubmitInquiry']);
        add_action('wp_ajax_nopriv_tourivo_submit_inquiry', [$this, 'handleSubmitInquiry']);

        if (!is_admin()) {
            return;
        }

        $this->addAction('admin_menu', [$this, 'registerAdminMenus'], 9);
        $this->addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        $this->addAction('admin_post_tourivo_export_bookings_csv', [BookingsTable::class, 'exportCsv']);

        // Admin Notices
        $this->addAction('admin_notices', [$this, 'renderWelcomeDemoNotice']);

        // AJAX handlers
        $this->addAction('wp_ajax_tourivo_import_sample_data', [$this, 'handleSampleDataImport']);
        $this->addAction('wp_ajax_tourivo_dismiss_welcome_notice', [$this, 'handleDismissWelcomeNotice']);
        $this->addAction('wp_ajax_tourivo_update_booking_status', [$this, 'handleUpdateBookingStatus']);
        $this->addAction('wp_ajax_tourivo_create_manual_booking', [$this, 'handleCreateManualBooking']);
        $this->addAction('wp_ajax_tourivo_update_inquiry_status', [$this, 'handleUpdateInquiryStatus']);
        $this->addAction('wp_ajax_tourivo_delete_inquiry', [$this, 'handleDeleteInquiry']);
        $this->addAction('wp_ajax_tourivo_send_test_email', [$this, 'handleSendTestEmail']);
        $this->addAction('wp_ajax_tourivo_get_booking_timeline', [$this, 'handleGetBookingTimeline']);
        $this->addAction('wp_ajax_tourivo_add_booking_note', [$this, 'handleAddBookingNote']);
    }

    /**
     * Register top-level Tourivo admin menu and submenus.
     *
     * @return void
     */
    public function registerAdminMenus(): void
    {
        add_menu_page(
            __('Tourivo', 'tourivo'),
            __('Tourivo', 'tourivo'),
            'manage_tourivo',
            'tourivo',
            [$this, 'renderDashboardPage'],
            'dashicons-palmtree',
            25
        );

        add_submenu_page(
            'tourivo',
            __('Dashboard', 'tourivo'),
            __('Dashboard', 'tourivo'),
            'manage_tourivo',
            'tourivo',
            [$this, 'renderDashboardPage']
        );

        add_submenu_page(
            'tourivo',
            __('Bookings', 'tourivo'),
            __('Bookings', 'tourivo'),
            'manage_tourivo_bookings',
            'tourivo-bookings',
            [$this, 'renderBookingsPage']
        );

        add_submenu_page(
            'tourivo',
            __('Inquiries', 'tourivo'),
            __('Inquiries', 'tourivo'),
            'manage_tourivo_bookings',
            'tourivo-inquiries',
            [$this, 'renderInquiriesPage']
        );

        add_submenu_page(
            'tourivo',
            __('Docs & Features', 'tourivo'),
            __('Docs & Features', 'tourivo'),
            'manage_tourivo',
            'tourivo-docs',
            [$this, 'renderDocumentationPage']
        );

        add_submenu_page(
            'tourivo',
            __('Settings', 'tourivo'),
            __('Settings', 'tourivo'),
            'manage_tourivo_settings',
            'tourivo-settings',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Enqueue admin styles and scripts.
     *
     * @param string $hook
     * @return void
     */
    public function enqueueAdminAssets(string $hook): void
    {
        $isTourivoScreen = str_contains($hook, 'tourivo') || in_array($hook, ['index.php', 'plugins.php', 'edit.php'], true);
        if (!$isTourivoScreen) {
            return;
        }

        $cssVer = file_exists(TOURIVO_PLUGIN_DIR . 'assets/css/admin.css') ? (string) filemtime(TOURIVO_PLUGIN_DIR . 'assets/css/admin.css') : TOURIVO_VERSION;
        $jsVer  = file_exists(TOURIVO_PLUGIN_DIR . 'assets/js/admin.js') ? (string) filemtime(TOURIVO_PLUGIN_DIR . 'assets/js/admin.js') : TOURIVO_VERSION;

        wp_enqueue_style(
            'tourivo-admin',
            TOURIVO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $cssVer
        );

        wp_enqueue_script(
            'tourivo-admin-js',
            TOURIVO_PLUGIN_URL . 'assets/js/admin.js',
            [],
            $jsVer,
            true
        );

        wp_localize_script('tourivo-admin-js', 'tourivoAdminConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tourivo_admin_nonce'),
        ]);
    }

    public function renderDashboardPage(): void
    {
        AdminDashboard::render();
    }

    public function renderBookingsPage(): void
    {
        BookingsTable::render();
    }

    public function renderInquiriesPage(): void
    {
        InquiriesTable::render();
    }

    public function renderDocumentationPage(): void
    {
        DocumentationPage::render();
    }

    public function renderSettingsPage(): void
    {
        SettingsPage::render();
    }

    /**
     * Render beginner-friendly welcome notice to import starter demo content if site is empty.
     */
    public function renderWelcomeDemoNotice(): void
    {
        if (!current_user_can('manage_tourivo')) {
            return;
        }

        // Check if user dismissed notice
        $dismissed = get_user_meta(get_current_user_id(), 'tourivo_dismiss_welcome_notice', true);
        if ($dismissed) {
            return;
        }

        // Check if there are already tours or hotels published
        $toursCount = (int) (wp_count_posts(TourPostType::POST_TYPE)->publish ?? 0);
        $hotelsCount = (int) (wp_count_posts(HotelPostType::POST_TYPE)->publish ?? 0);

        if ($toursCount > 0 || $hotelsCount > 0) {
            return;
        }

        $nonce = wp_create_nonce('tourivo_admin_nonce');
        ?>
        <div class="notice notice-info is-dismissible tourivo-welcome-notice" data-nonce="<?php echo esc_attr($nonce); ?>" style="border-left-color: #0284c7; background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%); border-radius: 8px; padding: 16px 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin: 20px 20px 15px 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="background: #0284c7; color: #fff; width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(2,132,199,0.3);">
                        🌴
                    </div>
                    <div>
                        <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: #0f172a;">
                            <?php esc_html_e('Welcome to Tourivo! Want to explore with ready-made demo content?', 'tourivo'); ?>
                        </h3>
                        <p style="margin: 0; color: #475569; font-size: 13.5px;">
                            <?php esc_html_e('Get started in seconds! Import 3 complete tours (Bali, Swiss Alps, Dubai) and 2 boutique hotels with 4 rooms to test booking forms, filters, and calendars immediately.', 'tourivo'); ?>
                        </p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="button button-primary tourivo-import-sample-btn" data-nonce="<?php echo esc_attr($nonce); ?>" style="background: #0284c7; border-color: #0284c7; padding: 6px 16px; height: auto; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(2,132,199,0.25);">
                        <span class="dashicons dashicons-cloud-upload"></span> <?php esc_html_e('⚡ Import Starter Demo Content', 'tourivo'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=tourivo-docs')); ?>" class="button button-secondary" style="height: auto; padding: 6px 14px;">
                        <?php esc_html_e('Docs & Features Hub', 'tourivo'); ?>
                    </a>
                </div>
            </div>
            <div class="tourivo-seeder-notice" style="display:none; margin-top:12px;"></div>
        </div>
        <?php
    }

    /**
     * AJAX handler to permanently dismiss welcome banner.
     */
    public function handleDismissWelcomeNotice(): void
    {
        check_ajax_referer('tourivo_admin_nonce', 'nonce');

        if (!current_user_can('manage_tourivo')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'tourivo')], 403);
        }

        update_user_meta(get_current_user_id(), 'tourivo_dismiss_welcome_notice', 1);

        wp_send_json_success();
    }

    /**
     * AJAX handler for 1-click sample data import.
     */
    public function handleSampleDataImport(): void
    {
        check_ajax_referer('tourivo_admin_nonce', 'nonce');

        if (!current_user_can('manage_tourivo')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'tourivo')], 403);
        }

        $res = Seeder::run();

        // Mark welcome notice dismissed since demo data is now imported
        update_user_meta(get_current_user_id(), 'tourivo_dismiss_welcome_notice', 1);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: Tours count, 2: Hotels count, 3: Rooms count */
                __('Sample data successfully imported! Created %1$d Tours, %2$d Hotels, and %3$d Rooms.', 'tourivo'),
                $res['tours_created'],
                $res['hotels_created'],
                $res['rooms_created']
            ),
        ]);
    }

    /**
     * AJAX handler for inline booking status update.
     */
    public function handleUpdateBookingStatus(): void
    {
        check_ajax_referer('tourivo_admin_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_bookings')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'tourivo')], 403);
        }

        $bookingId = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $status    = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

        if ($bookingId <= 0 || !in_array($status, ['pending', 'confirmed', 'completed', 'cancelled', 'on_hold'], true)) {
            wp_send_json_error(['message' => __('Invalid booking ID or status.', 'tourivo')], 400);
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $oldBooking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bookingsTable} WHERE id = %d", $bookingId));

        if (!$oldBooking) {
            wp_send_json_error(['message' => __('Booking not found.', 'tourivo')], 404);
        }

        $oldStatus = (string) $oldBooking->booking_status;

        if ($oldStatus === $status) {
            wp_send_json_success(['message' => __('Booking status unchanged.', 'tourivo')]);
        }

        $itemsTable = $wpdb->prefix . 'tourivo_booking_items';
        $lineItems = (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$itemsTable} WHERE booking_id = %d", $bookingId));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $inventoryService = Container::getInstance()->get(\Tourivo\Services\InventoryService::class);

        // Case 1: Reactivating from cancelled -> Try to commit inventory first
        if ($oldStatus === 'cancelled' && $status !== 'cancelled') {
            $committedItems = [];
            $allCommitted = true;

            if (!empty($lineItems)) {
                foreach ($lineItems as $item) {
                    $committed = $inventoryService->commitBooking(
                        (int) $item->item_id,
                        (string) $item->item_type,
                        substr((string) $item->check_in, 0, 10),
                        !empty($item->check_out) ? substr((string) $item->check_out, 0, 10) : null,
                        (string) ($item->time_slot ?: 'all_day'),
                        (int) $item->quantity
                    );

                    if (!$committed) {
                        $allCommitted = false;
                        break;
                    }
                    $committedItems[] = $item;
                }
            }

            if (!$allCommitted) {
                // Rollback any successfully committed items in this loop
                foreach ($committedItems as $cItem) {
                    $inventoryService->releaseBookingInventory(
                        (int) $cItem->item_id,
                        (string) $cItem->item_type,
                        substr((string) $cItem->check_in, 0, 10),
                        !empty($cItem->check_out) ? substr((string) $cItem->check_out, 0, 10) : null,
                        (string) ($cItem->time_slot ?: 'all_day'),
                        (int) $cItem->quantity
                    );
                }
                wp_send_json_error(['message' => __('Cannot reactivate booking. Required inventory spots are no longer available.', 'tourivo')], 409);
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $updated = $wpdb->update(
                $bookingsTable,
                ['booking_status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')],
                ['id' => $bookingId],
                ['%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                // Revert committed inventory on DB error
                foreach ($committedItems as $cItem) {
                    $inventoryService->releaseBookingInventory(
                        (int) $cItem->item_id,
                        (string) $cItem->item_type,
                        substr((string) $cItem->check_in, 0, 10),
                        !empty($cItem->check_out) ? substr((string) $cItem->check_out, 0, 10) : null,
                        (string) ($cItem->time_slot ?: 'all_day'),
                        (int) $cItem->quantity
                    );
                }
                wp_send_json_error(['message' => __('Database update error.', 'tourivo')]);
            }
        } else {
            // Case 2: Standard transition or Cancelling
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $updated = $wpdb->update(
                $bookingsTable,
                ['booking_status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')],
                ['id' => $bookingId],
                ['%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                wp_send_json_error(['message' => __('Database update error.', 'tourivo')]);
            }

            // If cancelled, release inventory once
            if ($status === 'cancelled' && $oldStatus !== 'cancelled') {
                if (!empty($lineItems)) {
                    foreach ($lineItems as $item) {
                        $inventoryService->releaseBookingInventory(
                            (int) $item->item_id,
                            (string) $item->item_type,
                            substr((string) $item->check_in, 0, 10),
                            !empty($item->check_out) ? substr((string) $item->check_out, 0, 10) : null,
                            (string) ($item->time_slot ?: 'all_day'),
                            (int) $item->quantity
                        );
                    }
                }
            }
        }

        LogService::log(
            $bookingId,
            'status_changed',
            sprintf(
                /* translators: 1: Old status, 2: New status */
                __('Booking status changed from %1$s to %2$s', 'tourivo'),
                ucfirst($oldStatus),
                ucfirst($status)
            )
        );
        do_action('tourivo/booking_status_changed', $bookingId, $oldStatus, $status);

        wp_send_json_success(['message' => __('Booking status updated successfully.', 'tourivo')]);
    }

    /**
     * AJAX handler for creating manual booking.
     */
    public function handleCreateManualBooking(): void
    {
        check_ajax_referer('tourivo_admin_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_bookings')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'tourivo')], 403);
        }

        $itemId        = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        $itemType      = isset($_POST['item_type']) ? sanitize_text_field(wp_unslash($_POST['item_type'])) : 'tour';
        $checkIn       = isset($_POST['check_in']) ? sanitize_text_field(wp_unslash($_POST['check_in'])) : '';
        $checkOut      = isset($_POST['check_out']) ? sanitize_text_field(wp_unslash($_POST['check_out'])) : null;
        $adults        = isset($_POST['adults']) ? max(1, (int) $_POST['adults']) : 1;
        $children      = isset($_POST['children']) ? max(0, (int) $_POST['children']) : 0;
        $rooms         = isset($_POST['rooms']) ? max(1, (int) $_POST['rooms']) : 1;
        $totalAmount   = isset($_POST['total_amount']) ? (float) $_POST['total_amount'] : 0.0;
        $customerName  = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $customerEmail = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $customerPhone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
        $paymentStatus = isset($_POST['payment_status']) ? sanitize_text_field(wp_unslash($_POST['payment_status'])) : 'paid';
        $customerNotes = isset($_POST['customer_notes']) ? sanitize_textarea_field(wp_unslash($_POST['customer_notes'])) : '';

        if ($itemId <= 0 || empty($checkIn) || empty($customerName) || empty($customerEmail)) {
            wp_send_json_error(['message' => __('Please fill in all required fields.', 'tourivo')], 400);
        }

        $customerId = 0;
        $foundUser = get_user_by('email', $customerEmail);
        if ($foundUser) {
            $customerId = (int) $foundUser->ID;
        }

        $bookingService = Container::getInstance()->get(BookingService::class);
        $result = $bookingService->createBooking([
            'item_id'        => $itemId,
            'item_type'      => $itemType,
            'check_in'       => $checkIn,
            'check_out'      => $checkOut,
            'adults'         => $adults,
            'children'       => $children,
            'rooms'          => $rooms,
            'total_amount'   => $totalAmount,
            'customer_id'    => $customerId,
            'customer_name'  => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'customer_notes' => $customerNotes,
            'payment_method' => 'manual_admin',
            'payment_status' => $paymentStatus,
            'booking_status' => 'confirmed',
        ], true);

        if (!empty($result['success'])) {
            wp_send_json_success([
                /* translators: %s: Booking Reference Code */
                'message'      => sprintf(__('Manual booking created successfully! Reference Code: #%s', 'tourivo'), $result['booking_code']),
                'booking_code' => $result['booking_code'],
            ]);
        } else {
            wp_send_json_error(['message' => $result['message'] ?? __('Failed to create manual booking.', 'tourivo')], 400);
        }
    }

    /**
     * AJAX handler for customer submitting inquiry from frontend modal.
     */
    public function handleSubmitInquiry(): void
    {
        check_ajax_referer('tourivo_frontend_nonce', 'nonce');

        $inquiryService = Container::getInstance()->get(InquiryService::class);
        $res = $inquiryService->createInquiry($_POST);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res, 400);
        }
    }

    /**
     * AJAX handler for updating inquiry status.
     */
    public function handleUpdateInquiryStatus(): void
    {
        check_ajax_referer('tourivo_inquiry_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_bookings')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'tourivo')], 403);
        }

        $inquiryId = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $status    = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

        $inquiryService = Container::getInstance()->get(InquiryService::class);
        if ($inquiryService->updateStatus($inquiryId, $status)) {
            wp_send_json_success(['message' => __('Inquiry status updated.', 'tourivo')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update inquiry status.', 'tourivo')]);
        }
    }

    /**
     * AJAX handler for deleting an inquiry.
     */
    public function handleDeleteInquiry(): void
    {
        check_ajax_referer('tourivo_inquiry_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_bookings')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'tourivo')], 403);
        }

        $inquiryId = isset($_POST['id']) ? absint($_POST['id']) : 0;

        $inquiryService = Container::getInstance()->get(InquiryService::class);
        if ($inquiryService->deleteInquiry($inquiryId)) {
            wp_send_json_success(['message' => __('Inquiry deleted.', 'tourivo')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete inquiry.', 'tourivo')]);
        }
    }

    /**
     * AJAX handler for sending test email verification.
     */
    public function handleSendTestEmail(): void
    {
        check_ajax_referer('tourivo_test_email_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_settings')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tourivo')], 403);
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'tourivo')], 400);
        }

        $emailService = new EmailService();
        $sent = $emailService->sendTestEmail($email);

        if ($sent) {
            wp_send_json_success([
                /* translators: %s: Email address */
                'message' => sprintf(__('Test email successfully dispatched to %s!', 'tourivo'), $email),
            ]);
        } else {
            wp_send_json_error(['message' => __('Mail sending failed. Please verify your WordPress SMTP settings.', 'tourivo')], 500);
        }
    }

    /**
     * AJAX handler for getting booking timeline history.
     */
    public function handleGetBookingTimeline(): void
    {
        check_ajax_referer('tourivo_timeline_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_bookings')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tourivo')], 403);
        }

        $bookingId = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        if ($bookingId <= 0) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'tourivo')], 400);
        }

        $logs = LogService::getTimeline($bookingId);

        ob_start();
        if (!empty($logs)) : ?>
            <ul class="timeline-list" style="list-style: none; margin: 0; padding: 0;">
                <?php foreach ($logs as $log) : 
                    $user = $log['user_id'] ? get_userdata((int)$log['user_id']) : null;
                    $userName = $user ? $user->display_name : __('System / Guest', 'tourivo');
                    $isNote = $log['action'] === 'internal_note';
                ?>
                    <li class="timeline-item" style="padding: 8px 10px; margin-bottom: 8px; border-left: 3px solid <?php echo $isNote ? '#8b5cf6' : '#0284c7'; ?>; background: #f8fafc; border-radius: 0 6px 6px 0; font-size: 13px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                            <strong><?php echo $isNote ? '📝 ' . esc_html__('Internal Staff Note', 'tourivo') : '📌 ' . esc_html(ucwords(str_replace('_', ' ', $log['action']))); ?></strong>
                            <small style="color: #64748b;"><?php echo esc_html(gmdate('M d, Y g:i A', strtotime($log['created_at']))); ?></small>
                        </div>
                        <div style="color: #334155; line-height: 1.4;"><?php echo nl2br(esc_html($log['details'])); ?></div>
                        <small style="color: #94a3b8; font-size: 11px;"><?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: Staff or user display name */
                                __('Logged by: %s', 'tourivo'),
                                $userName
                            )
                        );
                        ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <div style="text-align: center; color: #64748b; padding: 12px;"><?php esc_html_e('No activity logs recorded yet for this booking.', 'tourivo'); ?></div>
        <?php endif;

        $html = ob_get_clean() ?: '';
        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX handler for adding internal note to a booking.
     */
    public function handleAddBookingNote(): void
    {
        check_ajax_referer('tourivo_timeline_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_bookings')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tourivo')], 403);
        }

        $bookingId = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $note      = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        if ($bookingId <= 0 || empty($note)) {
            wp_send_json_error(['message' => __('Note content cannot be empty.', 'tourivo')], 400);
        }

        $logId = LogService::log($bookingId, 'internal_note', $note, get_current_user_id());

        if ($logId > 0) {
            wp_send_json_success(['message' => __('Internal note added successfully.', 'tourivo')]);
        } else {
            wp_send_json_error(['message' => __('Failed to record note.', 'tourivo')]);
        }
    }
}

