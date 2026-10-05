<?php

declare(strict_types=1);

namespace Tourivo\Providers;

use Tourivo\Admin\AdminDashboard;
use Tourivo\Admin\BookingsTable;
use Tourivo\Admin\DocumentationPage;
use Tourivo\Admin\InquiriesTable;
use Tourivo\Admin\SettingsPage;
use Tourivo\Admin\SetupWizard;
use Tourivo\Common\Abstracts\ServiceProvider;
use Tourivo\Common\Container;
use Tourivo\Database\Seeder;
use Tourivo\PostTypes\HotelPostType;
use Tourivo\PostTypes\TourPostType;
use Tourivo\Services\BookingService;
use Tourivo\Services\EmailService;
use Tourivo\Services\InquiryService;
use Tourivo\Services\InventoryService;
use Tourivo\Services\LogService;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Shortcodes\ThankYouShortcode;

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

        // Printable Voucher & Calendar .ics handlers (Admin & Public with token)
        add_action('admin_post_tourivo_print_voucher', [$this, 'handlePrintVoucher']);
        add_action('admin_post_nopriv_tourivo_print_voucher', [$this, 'handlePrintVoucher']);
        add_action('admin_post_tourivo_download_ics', [$this, 'handleDownloadIcs']);
        add_action('admin_post_nopriv_tourivo_download_ics', [$this, 'handleDownloadIcs']);

        if (!is_admin()) {
            return;
        }

        $this->addAction('admin_menu', [$this, 'registerAdminMenus'], 9);
        $this->addAction('admin_enqueue_scripts', [$this, 'maybeSwitchUiLocale'], 1);
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
        $this->addAction('wp_ajax_tourivo_send_test_webhook', [$this, 'handleSendTestWebhook']);
        $this->addAction('wp_ajax_tourivo_get_booking_timeline', [$this, 'handleGetBookingTimeline']);
        $this->addAction('wp_ajax_tourivo_add_booking_note', [$this, 'handleAddBookingNote']);
        $this->addAction('wp_ajax_tourivo_get_admin_calendar', [$this, 'handleGetAdminCalendar']);
        $this->addAction('wp_ajax_tourivo_update_availability', [$this, 'handleUpdateAvailability']);
        $this->addAction('wp_ajax_tourivo_retention_dry_run', [$this, 'handleRetentionDryRun']);

        // Setup Wizard AJAX Handlers
        $this->addAction('wp_ajax_tourivo_wizard_save_step1', [SetupWizard::class, 'handleSaveStep1']);
        $this->addAction('wp_ajax_tourivo_wizard_create_pages', [SetupWizard::class, 'handleCreatePages']);
        $this->addAction('wp_ajax_tourivo_wizard_import_demo', [SetupWizard::class, 'handleImportDemo']);
    }

    /**
     * Switch WordPress locale on Tourivo admin pages if plugin_language is customized.
     *
     * @return void
     */
    public function maybeSwitchUiLocale(): void
    {
        $lang = (string) \Tourivo\Config\Config::get('plugin_language', 'default');
        if ('default' === $lang) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $isTourivo = str_contains((string) $screen->id, 'tourivo')
            || in_array((string) $screen->post_type, ['tourivo_tour', 'tourivo_hotel', 'tourivo_room'], true);

        if (!$isTourivo) {
            return;
        }

        $locale = ('bn' === $lang) ? 'bn_BD' : 'en_US';
        tourivo_load_plugin_textdomain($locale);
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
            __('Setup Wizard', 'tourivo'),
            __('Setup Wizard', 'tourivo'),
            'manage_tourivo_settings',
            'tourivo-setup-wizard',
            [SetupWizard::class, 'render']
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

        if (function_exists('tourivo_is_bengali') && tourivo_is_bengali()) {
            wp_enqueue_style(
                'tourivo-bengali-font',
                TOURIVO_PLUGIN_URL . 'assets/css/tourivo-bengali-font.css',
                [],
                TOURIVO_VERSION
            );
        }

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

        if (empty($res['success'])) {
            wp_send_json_error([
                'message' => $res['message'] ?? __('Failed to import sample demo data. Please check server logs.', 'tourivo'),
            ], 500);
        }

        if (!empty($res['skipped'])) {
            wp_send_json_success([
                'message' => $res['message'] ?? __('Demo content is already imported.', 'tourivo'),
                'skipped' => true,
            ]);
        }

        // Mark welcome notice dismissed only on genuine new demo data import
        update_user_meta(get_current_user_id(), 'tourivo_dismiss_welcome_notice', 1);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: Tours count, 2: Hotels count, 3: Rooms count */
                __('Sample data successfully imported! Created %1$d Tours, %2$d Hotels, and %3$d Rooms.', 'tourivo'),
                $res['tours_created'],
                $res['hotels_created'],
                $res['rooms_created']
            ),
            'tours_created'  => $res['tours_created'],
            'hotels_created' => $res['hotels_created'],
            'rooms_created'  => $res['rooms_created'],
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

        $bookingService = Container::getInstance()->get(\Tourivo\Services\BookingService::class);
        $result = $bookingService->changeStatus($bookingId, $status, ['source' => 'admin_ajax']);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']], 400);
        }

        wp_send_json_success(['message' => $result['message']]);
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
        $sendCustomerEmail = isset($_POST['send_customer_email']) && ($_POST['send_customer_email'] === '1' || $_POST['send_customer_email'] === 'true' || $_POST['send_customer_email'] === true);

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
            'item_id'             => $itemId,
            'item_type'           => $itemType,
            'check_in'            => $checkIn,
            'check_out'           => $checkOut,
            'adults'              => $adults,
            'children'            => $children,
            'rooms'               => $rooms,
            'total_amount'        => $totalAmount,
            'customer_id'         => $customerId,
            'customer_name'       => $customerName,
            'customer_email'      => $customerEmail,
            'customer_phone'      => $customerPhone,
            'customer_notes'      => $customerNotes,
            'payment_method'      => 'manual_admin',
            'payment_status'      => $paymentStatus,
            'booking_status'      => 'confirmed',
            'send_customer_email' => $sendCustomerEmail,
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
        $emailType = isset($_POST['email_type']) ? sanitize_key(wp_unslash($_POST['email_type'])) : 'customer_booking_confirmed';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'tourivo')], 400);
        }

        $emailService = new EmailService();
        $sent = $emailService->sendTestEmail($email, $emailType);

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
     * AJAX handler to dispatch a test webhook payload.
     */
    public function handleSendTestWebhook(): void
    {
        check_ajax_referer('tourivo_test_webhook_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_settings')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tourivo')], 403);
        }

        $url    = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        $secret = isset($_POST['secret']) ? sanitize_text_field(wp_unslash($_POST['secret'])) : '';

        $webhookService = Container::getInstance()->get(\Tourivo\Services\WebhookService::class);
        $res = $webhookService->sendTestWebhook($url, $secret);

        if ($res['success']) {
            wp_send_json_success(['message' => $res['message']]);
        } else {
            wp_send_json_error(['message' => $res['message']]);
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

    /**
     * Handle public and admin printable booking voucher rendering.
     *
     * @return void
     */
    public function handlePrintVoucher(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $code  = isset($_GET['code']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_GET['code']))) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash((string) $_GET['token'])) : '';

        if (empty($code)) {
            wp_die(esc_html__('Invalid or missing booking reference code.', 'tourivo'), 400);
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $tourivoBooking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE booking_code = %s LIMIT 1",
            $code
        ));

        if (!$tourivoBooking) {
            wp_die(esc_html__('Booking not found.', 'tourivo'), 404);
        }

        // Security verification: Either valid HMAC token OR logged-in administrator with capability
        $expectedToken = BookingLookupShortcode::generateVoucherToken((int) $tourivoBooking->id, (string) $tourivoBooking->customer_email);
        $hasValidToken = hash_equals($expectedToken, $token);
        $isAdmin       = current_user_can('manage_tourivo_bookings');

        if (!$hasValidToken && !$isAdmin) {
            wp_die(esc_html__('Access denied. Invalid voucher security token.', 'tourivo'), 403);
        }

        $tourivoItems = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$itemsTable} WHERE booking_id = %d",
            $tourivoBooking->id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $tourivoCurrencySymbol = (string) apply_filters('tourivo/currency_symbol', '$');
        $tourivoSiteName       = get_bloginfo('name');

        include untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/booking-voucher.php';
        exit;
    }

    /**
     * Handle iCalendar (.ics) download for bookings.
     *
     * @return void
     */
    public function handleDownloadIcs(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $code  = isset($_GET['code']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_GET['code']))) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash((string) $_GET['token'])) : '';

        if (empty($code)) {
            wp_die(esc_html__('Invalid or missing booking reference code.', 'tourivo'), 400);
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $tourivoBooking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE booking_code = %s LIMIT 1",
            $code
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$tourivoBooking) {
            wp_die(esc_html__('Booking not found.', 'tourivo'), 404);
        }

        $bookingId            = (int) $tourivoBooking->id;
        $isValidThankYou      = ThankYouShortcode::verifyThankYouToken($bookingId, $token);
        $expectedVoucherToken = BookingLookupShortcode::generateVoucherToken($bookingId, (string) $tourivoBooking->customer_email);
        $isValidVoucher       = hash_equals($expectedVoucherToken, $token);
        $isAdmin              = current_user_can('manage_tourivo_bookings');

        if (!$isValidThankYou && !$isValidVoucher && !$isAdmin) {
            wp_die(esc_html__('Access denied. Invalid calendar security token.', 'tourivo'), 403);
        }

        $ics = ThankYouShortcode::generateIcsContent($bookingId);
        if (empty($ics)) {
            wp_die(esc_html__('Could not generate calendar file.', 'tourivo'), 500);
        }

        $filename = 'booking-' . sanitize_file_name((string) $tourivoBooking->booking_code) . '.ics';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $ics;
        exit;
    }

    /**
     * AJAX handler to fetch admin monthly availability calendar grid.
     *
     * @return void
     */
    public function handleGetAdminCalendar(): void
    {
        check_ajax_referer('tourivo_admin_nonce', 'nonce');

        $itemId   = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        $itemType = isset($_POST['item_type']) ? sanitize_text_field(wp_unslash($_POST['item_type'])) : 'tour';
        $year     = isset($_POST['year']) ? absint($_POST['year']) : (int) wp_date('Y');
        $month    = isset($_POST['month']) ? absint($_POST['month']) : (int) wp_date('n');
        $timeSlot = isset($_POST['time_slot']) ? sanitize_text_field(wp_unslash($_POST['time_slot'])) : 'all_day';

        if ($itemId <= 0) {
            wp_send_json_error(['message' => __('Invalid item ID.', 'tourivo')], 400);
        }

        if (!current_user_can('edit_post', $itemId) && !current_user_can('manage_tourivo_tours') && !current_user_can('manage_tourivo_hotels') && !current_user_can('manage_tourivo')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'tourivo')], 403);
        }

        $invService = Container::getInstance()->get(InventoryService::class);
        $calendar   = $invService->getAdminCalendarAvailability($itemId, $itemType, $year, $month, $timeSlot);
        $defaultCap = $invService->getDefaultCapacity($itemId, $itemType);
        $unitPrice  = $invService->getUnitPrice($itemId, $itemType);

        wp_send_json_success([
            'year'             => $year,
            'month'            => $month,
            'days'             => $calendar,
            'default_capacity' => $defaultCap,
            'base_price'       => $unitPrice,
            'currency_symbol'  => (string) apply_filters('tourivo/currency_symbol', '$'),
        ]);
    }

    /**
     * AJAX handler to update item availability, capacity, pricing and blocking status.
     *
     * @return void
     */
    public function handleUpdateAvailability(): void
    {
        check_ajax_referer('tourivo_admin_nonce', 'nonce');

        $itemId    = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        $itemType  = isset($_POST['item_type']) ? sanitize_text_field(wp_unslash($_POST['item_type'])) : 'tour';
        $startDate = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $endDate   = (isset($_POST['end_date']) && !empty($_POST['end_date'])) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : null;
        $timeSlot  = isset($_POST['time_slot']) ? sanitize_text_field(wp_unslash($_POST['time_slot'])) : 'all_day';
        $force     = !empty($_POST['force']) && ($_POST['force'] === '1' || $_POST['force'] === 'true' || $_POST['force'] === true);

        if ($itemId <= 0 || empty($startDate)) {
            wp_send_json_error(['message' => __('Please provide a valid item ID and date.', 'tourivo')], 400);
        }

        if (!current_user_can('edit_post', $itemId) && !current_user_can('manage_tourivo_tours') && !current_user_can('manage_tourivo_hotels') && !current_user_can('manage_tourivo')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'tourivo')], 403);
        }

        $changes = [];

        if (isset($_POST['status']) && in_array($_POST['status'], ['available', 'blocked'], true)) {
            $changes['status'] = sanitize_text_field(wp_unslash($_POST['status']));
        }

        if (isset($_POST['capacity']) && $_POST['capacity'] !== '') {
            $changes['capacity'] = (int) $_POST['capacity'];
        }

        if (!empty($_POST['reset_price'])) {
            $changes['reset_price'] = true;
        } elseif (isset($_POST['price_override']) && $_POST['price_override'] !== '') {
            $changes['price_override'] = (float) $_POST['price_override'];
        }

        if (isset($_POST['days_of_week']) && is_array($_POST['days_of_week'])) {
            $changes['days_of_week'] = array_map('intval', wp_unslash($_POST['days_of_week']));
        }

        $invService = Container::getInstance()->get(InventoryService::class);
        $result = $invService->updateAvailability($itemId, $itemType, $startDate, $endDate, $changes, $force, $timeSlot);

        if (!empty($result['success'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['message'] ?? __('Failed to update availability.', 'tourivo')], 400);
        }
    }

    /**
     * AJAX handler to calculate retention dry-run counts.
     *
     * @return void
     */
    public function handleRetentionDryRun(): void
    {
        check_ajax_referer('tourivo_admin_nonce', 'nonce');

        if (!current_user_can('manage_tourivo_settings')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'tourivo')], 403);
        }

        $counts = \Tourivo\Services\PrivacyService::getRetentionDryRunCounts();
        wp_send_json_success($counts);
    }
}

