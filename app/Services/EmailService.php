<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Config\Config;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EmailService
 *
 * Fully asynchronous, hook-driven email notification engine for Tourivo.
 * Supports multipart responsive HTML + Plain-text (AltBody), trip reminders cron,
 * template overrides, deduplication, retry queues, and header injection defense.
 *
 * @package Tourivo\Services
 */
class EmailService
{
    /**
     * Register lifecycle event hooks, queue delivery action, and reminder cron.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('tourivo/booking_created', [$this, 'onBookingCreated'], 20, 2);
        add_action('tourivo/booking_status_changed', [$this, 'onBookingStatusChanged'], 20, 4);
        add_action('tourivo/booking_email_verified', [$this, 'onBookingEmailVerified'], 20, 1);
        add_action('tourivo/inquiry_created', [$this, 'onInquiryCreated'], 20, 2);
        add_action('tourivo_process_email_delivery', [$this, 'dispatch'], 10, 4);
        add_action('tourivo_daily_reminders', [$this, 'processDailyReminders']);

        // Schedule daily reminders cron if enabled and not already scheduled
        $reminderDays = (int) Config::get('reminder_days_before', 2);
        if ($reminderDays > 0 && function_exists('wp_next_scheduled') && !wp_next_scheduled('tourivo_daily_reminders')) {
            wp_schedule_event(time(), 'daily', 'tourivo_daily_reminders');
        }
    }

    /**
     * Retrieve admin notification recipient email address.
     *
     * @return string
     */
    public function getAdminEmail(): string
    {
        $email = (string) Config::get('email_notification_address', '');
        if (empty($email) || !is_email($email)) {
            $email = (string) get_option('admin_email', '');
        }
        if (empty($email) || !is_email($email)) {
            $email = 'admin@example.com';
        }
        return $email;
    }

    /**
     * Triggered when a new booking is created.
     *
     * @param int                  $bookingId
     * @param array<string, mixed> $bookingData
     * @return void
     */
    public function onBookingCreated(int $bookingId, array $bookingData): void
    {
        $status = (string) ($bookingData['booking_status'] ?? 'pending');

        // Unverified public booking: the only mail that may go out is the confirmation link. Staff and the
        // regular "received" mail follow once the address is confirmed (see onBookingEmailVerified()).
        if (array_key_exists('email_verified', $bookingData) && $bookingData['email_verified'] === false) {
            $customerEmail = (string) ($bookingData['customer_email'] ?? '');
            if (!empty($customerEmail) && is_email($customerEmail)) {
                $this->enqueue('customer_verify_email', $customerEmail, $bookingData);
            }
            return;
        }

        // 1. Enqueue Admin Notification
        $adminEmail = $this->getAdminEmail();
        if (!empty($adminEmail) && is_email($adminEmail)) {
            $this->enqueue('admin_new_booking', $adminEmail, $bookingData);
        }

        // 2. Check if customer email should be sent (respect manual booking suppression flag)
        $sendCustomer = !isset($bookingData['send_customer_email']) || (bool) $bookingData['send_customer_email'];
        if (!$sendCustomer) {
            return;
        }

        $customerEmail = (string) ($bookingData['customer_email'] ?? '');
        if (empty($customerEmail) || !is_email($customerEmail)) {
            return;
        }

        // 3. Enqueue Customer Notification based on status
        if ($status === 'confirmed') {
            $this->enqueue('customer_booking_confirmed', $customerEmail, $bookingData);
        } else {
            $this->enqueue('customer_booking_received', $customerEmail, $bookingData);
        }
    }

    /**
     * Triggered when a booking status changes (pending -> confirmed, -> cancelled, etc.).
     *
     * @param int    $bookingId
     * @param string $oldStatus
     * @param string $newStatus
     * @param array<string, mixed> $context
     * @return void
     */
    public function onBookingStatusChanged(int $bookingId, string $oldStatus, string $newStatus, array $context = []): void
    {
        if ($bookingId <= 0 || $oldStatus === $newStatus) {
            return;
        }

        // Never mail (or alert staff about) an address that did not prove ownership of the booking.
        if (($context['source'] ?? '') === 'unverified_expired') {
            return;
        }

        // Idempotency / Deduplication: prevent duplicate email for same (booking_id, new_status)
        $dedupKey = "tourivo_email_dedup_{$bookingId}_{$newStatus}";
        if (get_transient($dedupKey)) {
            return;
        }
        set_transient($dedupKey, 1, DAY_IN_SECONDS * 30);

        $bookingData = $this->getBookingData($bookingId);
        if (empty($bookingData)) {
            return;
        }

        $customerEmail = (string) ($bookingData['customer_email'] ?? '');
        $adminEmail    = $this->getAdminEmail();

        if ($newStatus === 'confirmed') {
            if (!empty($customerEmail) && is_email($customerEmail)) {
                $this->enqueue('customer_booking_confirmed', $customerEmail, $bookingData);
            }
        } elseif ($newStatus === 'cancelled') {
            if (!empty($customerEmail) && is_email($customerEmail)) {
                $this->enqueue('customer_booking_cancelled', $customerEmail, $bookingData);
            }
            if (!empty($adminEmail) && is_email($adminEmail)) {
                $this->enqueue('admin_booking_cancelled', $adminEmail, $bookingData);
            }
        }
    }

    /**
     * Triggered once the customer confirmed their email address: release the notifications that were held back.
     *
     * @param int $bookingId
     * @return void
     */
    public function onBookingEmailVerified(int $bookingId): void
    {
        $bookingData = $this->getBookingData($bookingId);
        if (empty($bookingData)) {
            return;
        }

        $adminEmail = $this->getAdminEmail();
        if (!empty($adminEmail) && is_email($adminEmail)) {
            $this->enqueue('admin_new_booking', $adminEmail, $bookingData);
        }

        $customerEmail = (string) ($bookingData['customer_email'] ?? '');
        if (empty($customerEmail) || !is_email($customerEmail)) {
            return;
        }

        $this->enqueue(
            ($bookingData['booking_status'] ?? 'pending') === 'confirmed' ? 'customer_booking_confirmed' : 'customer_booking_received',
            $customerEmail,
            $bookingData
        );
    }

    /**
     * Triggered when a customer trip inquiry is submitted.
     *
     * @param int                  $inquiryId
     * @param array<string, mixed> $inquiryData
     * @return void
     */
    public function onInquiryCreated(int $inquiryId, array $inquiryData): void
    {
        $adminEmail = $this->getAdminEmail();
        if (!empty($adminEmail) && is_email($adminEmail)) {
            $this->enqueue('admin_new_inquiry', $adminEmail, $inquiryData);
        }
    }

    /**
     * Enqueue asynchronous email delivery via Action Scheduler or WP-Cron.
     *
     * @param string               $emailType
     * @param string               $recipient
     * @param array<string, mixed> $data
     * @param int                  $attempt
     * @return void
     */
    public function enqueue(string $emailType, string $recipient, array $data, int $attempt = 1): void
    {
        if (empty($recipient) || !is_email($recipient)) {
            return;
        }

        // Check if email template type is enabled in settings
        $isEnabled = (bool) Config::get("email_{$emailType}_enabled", true);
        if (!$isEnabled) {
            return;
        }

        // Pro & Third-party filter to cancel or customize dispatch
        $shouldSend = (bool) apply_filters('tourivo/send_email', true, $emailType, $data);
        if (!$shouldSend) {
            return;
        }

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('tourivo_process_email_delivery', [$emailType, $recipient, $data, $attempt], 'tourivo-emails');
        } else {
            wp_schedule_single_event(time(), 'tourivo_process_email_delivery', [$emailType, $recipient, $data, $attempt]);
            if (function_exists('spawn_cron')) {
                spawn_cron();
            }
        }
    }

    /**
     * Dispatch email worker execution with logging, plain-text AltBody, and exponential retry.
     *
     * @param string               $emailType
     * @param string               $recipient
     * @param array<string, mixed> $data
     * @param int                  $attempt
     * @return bool
     */
    public function dispatch(string $emailType, string $recipient, array $data, int $attempt = 1): bool
    {
        if (empty($recipient) || !is_email($recipient)) {
            return false;
        }

        $isEnabled = (bool) Config::get("email_{$emailType}_enabled", true);
        if (!$isEnabled) {
            return false;
        }

        $shouldSend = (bool) apply_filters('tourivo/send_email', true, $emailType, $data);
        if (!$shouldSend) {
            return false;
        }

        $siteName     = get_bloginfo('name');
        $primaryColor = Config::get('primary_color', '#0d9488');
        $fromName     = $this->sanitizeHeader((string) Config::get('email_from_name', $siteName));
        $fromEmail    = $this->sanitizeHeader((string) Config::get('email_from_address', get_option('admin_email')));
        $bookingId    = (int) ($data['id'] ?? $data['booking_id'] ?? 0);

        // Generate voucher URL & lookup URL
        $voucherUrl = '';
        if ($bookingId > 0 && !empty($data['customer_email'])) {
            $bookingRow = function_exists('tourivo_get_booking') ? tourivo_get_booking($bookingId) : null;
            $token = BookingLookupShortcode::generateVoucherToken($bookingId, (string) $data['customer_email'], $bookingRow->access_key ?? null);
            $voucherUrl = add_query_arg([
                'action' => 'tourivo_print_voucher',
                'code'   => (string) ($data['booking_code'] ?? ''),
                'token'  => $token,
            ], admin_url('admin-post.php'));
        }

        $lookupPageId = (int) Config::get('lookup_page_id', 0);
        $lookupUrl    = ($lookupPageId > 0 && function_exists('get_permalink')) ? get_permalink($lookupPageId) : home_url('/track-booking');

        // Merge runtime variables into data array for placeholder replacement
        $enrichedData = array_merge($data, [
            'voucher_url' => $voucherUrl,
            'lookup_url'  => $lookupUrl,
        ]);

        // Resolve Subject & Heading from Settings with backward-compatibility fallbacks
        $subject = $this->resolveSubject($emailType, $enrichedData);
        $heading = $this->resolveHeading($emailType, $enrichedData);
        $extraContent = (string) Config::get("email_{$emailType}_additional_content", '');
        if (!empty($extraContent)) {
            $extraContent = $this->replacePlaceholders($extraContent, $enrichedData);
        }

        $offlineInstructions = (string) Config::get('offline_payment_instructions', '');

        // Render template body
        $templateFile = str_replace('_', '-', $emailType) . '.php';
        $bodyArgs = [
            'booking'                      => $enrichedData,
            'inquiry'                      => $enrichedData,
            'voucherUrl'                   => $voucherUrl,
            'lookupUrl'                    => $lookupUrl,
            'offlinePaymentInstructions'   => $offlineInstructions,
            'additionalContent'            => $extraContent,
            'siteName'                     => $siteName,
            'isTest'                       => !empty($data['isTest']),
        ];

        $innerHtml = $this->renderTemplate($templateFile, $bodyArgs);
        $fullHtml  = $this->renderTemplate('base-layout.php', [
            'emailHeading'     => $heading,
            'emailBodyContent' => $innerHtml,
            'siteName'         => $siteName,
            'primaryColor'     => $primaryColor,
            'isTest'           => !empty($data['isTest']),
        ]);

        // Generate clean plain-text version for AltBody
        $plainText = $this->htmlToPlainText($innerHtml, $heading);

        // Header injection sanitization
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromName . ' <' . $fromEmail . '>',
        ];

        // Attach plain text AltBody via phpmailer_init action hook
        $altBodyHook = static function ($phpmailer) use ($plainText) {
            if (is_object($phpmailer) && property_exists($phpmailer, 'AltBody')) {
                $phpmailer->AltBody = $plainText;
            }
        };
        add_action('phpmailer_init', $altBodyHook);

        $lastError = '';
        $mailFailedHook = static function ($error) use (&$lastError) {
            if (is_wp_error($error)) {
                $lastError = $error->get_error_message();
            }
        };
        add_action('wp_mail_failed', $mailFailedHook);

        $sent = wp_mail($recipient, $subject, $fullHtml, $headers);

        remove_action('phpmailer_init', $altBodyHook);
        remove_action('wp_mail_failed', $mailFailedHook);

        // Log outcome in LogService
        if ($bookingId > 0) {
            if ($sent) {
                LogService::log(
                    $bookingId,
                    'email_sent',
                    sprintf(
                        /* translators: 1: Email type, 2: Recipient, 3: Attempt number */
                        __('Email [%1$s] sent successfully to %2$s (attempt %3$d).', 'tourivo'),
                        $emailType,
                        $recipient,
                        $attempt
                    )
                );
            } else {
                LogService::log(
                    $bookingId,
                    'email_failed',
                    sprintf(
                        /* translators: 1: Email type, 2: Recipient, 3: Attempt number, 4: Error details */
                        __('Email [%1$s] failed to send to %2$s (attempt %3$d). Reason: %4$s', 'tourivo'),
                        $emailType,
                        $recipient,
                        $attempt,
                        $lastError ?: __('Unknown mail error', 'tourivo')
                    )
                );
            }
        }

        // Handle retry schedule up to 3 attempts (attempt 2 delay: 120s, attempt 3 delay: 300s)
        if (!$sent && $attempt < 3) {
            $nextAttempt = $attempt + 1;
            $delay = ($nextAttempt === 2) ? 120 : 300;

            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + $delay, 'tourivo_process_email_delivery', [$emailType, $recipient, $data, $nextAttempt], 'tourivo-emails');
            } else {
                wp_schedule_single_event(time() + $delay, 'tourivo_process_email_delivery', [
                    $emailType,
                    $recipient,
                    $data,
                    $nextAttempt,
                ]);
            }
        }

        return $sent;
    }

    /**
     * Process daily trip reminders for confirmed bookings occurring in X days.
     *
     * @return void
     */
    public function processDailyReminders(): void
    {
        $reminderDays = (int) Config::get('reminder_days_before', 2);
        if ($reminderDays <= 0 || !Config::get('email_customer_trip_reminder_enabled', true)) {
            return;
        }

        global $wpdb;
        $targetDate = wp_date('Y-m-d', strtotime("+{$reminderDays} days"));

        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.*, i.item_title, i.check_in, i.check_out, i.adults_count, i.children_count, i.infants_count 
                 FROM {$bookingsTable} b
                 INNER JOIN {$itemsTable} i ON b.id = i.booking_id
                 WHERE b.booking_status = 'confirmed' 
                 AND DATE(i.check_in) = %s",
                $targetDate
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (empty($bookings)) {
            return;
        }

        foreach ($bookings as $booking) {
            $bookingId = (int) $booking->id;
            $reminderKey = "tourivo_reminder_sent_{$bookingId}";

            if (get_transient($reminderKey)) {
                continue;
            }
            set_transient($reminderKey, 1, DAY_IN_SECONDS * 14);

            $payload = [
                'id'              => $bookingId,
                'booking_code'    => (string) $booking->booking_code,
                'customer_name'   => (string) $booking->customer_name,
                'customer_email'  => (string) $booking->customer_email,
                'customer_phone'  => (string) $booking->customer_phone,
                'item_title'      => (string) $booking->item_title,
                'check_in'        => substr((string) $booking->check_in, 0, 10),
                'check_out'       => !empty($booking->check_out) ? substr((string) $booking->check_out, 0, 10) : '',
                'adults'          => (int) ($booking->adults_count ?? 1),
                'children'        => (int) ($booking->children_count ?? 0),
                'infants'         => (int) ($booking->infants_count ?? 0),
                'total_amount'    => Money::format((float) $booking->total_amount),
                'booking_status'  => (string) $booking->booking_status,
                'payment_status'  => (string) $booking->payment_status,
            ];

            $this->enqueue('customer_trip_reminder', (string) $booking->customer_email, $payload);
        }
    }

    /**
     * Send a synchronous test email to verify settings & layout in WP Admin.
     *
     * @param string $recipientEmail
     * @param string $emailType
     * @return bool
     */
    public function sendTestEmail(string $recipientEmail, string $emailType = 'customer_booking_confirmed'): bool
    {
        if (empty($recipientEmail) || !is_email($recipientEmail)) {
            return false;
        }

        $mockData = [
            'id'             => 999,
            'booking_code'   => 'TRV-TEST-8888',
            'customer_name'  => 'John Traveler',
            'customer_email' => $recipientEmail,
            'customer_phone' => '+1 234 567 890',
            'name'           => 'John Traveler',
            'email'          => $recipientEmail,
            'phone'          => '+1 234 567 890',
            'item_title'     => 'Tropical Paradise Island Tour (Sample)',
            'check_in'       => wp_date('Y-m-d', strtotime('+7 days')),
            'check_out'      => wp_date('Y-m-d', strtotime('+10 days')),
            'travel_date'    => wp_date('Y-m-d', strtotime('+7 days')),
            'adults'         => 2,
            'children'       => 1,
            'infants'        => 0,
            'guests'         => 3,
            'total_amount'   => Money::format(450.00),
            'tax_amount'     => Money::format(45.00),
            'discount_amount'=> Money::format(20.00),
            'booking_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'offline',
            'customer_notes' => 'Vegetarian meal requested.',
            'message'        => 'Is airport pickup included in this tour package?',
            'isTest'         => true,
        ];

        return $this->dispatch($emailType, $recipientEmail, $mockData, 1);
    }

    /**
     * Sanitize header strings against CRLF injection attacks.
     *
     * @param string $header
     * @return string
     */
    public function sanitizeHeader(string $header): string
    {
        return preg_replace('/[\r\n]+/', ' ', trim($header)) ?: '';
    }

    /**
     * Replace template placeholders in text strings.
     *
     * @param string               $text
     * @param array<string, mixed> $data
     * @return string
     */
    public function replacePlaceholders(string $text, array $data): string
    {
        $siteName = get_bloginfo('name');
        $siteUrl  = home_url('/');

        $guestsCount = (int) ($data['guests'] ?? (($data['adults'] ?? 1) + ($data['children'] ?? 0) + ($data['infants'] ?? 0)));

        $placeholders = [
            '{booking_code}'   => (string) ($data['booking_code'] ?? ''),
            '{customer_name}'  => (string) ($data['customer_name'] ?? $data['name'] ?? ''),
            '{customer_email}' => (string) ($data['customer_email'] ?? $data['email'] ?? ''),
            '{customer_phone}' => (string) ($data['customer_phone'] ?? $data['phone'] ?? ''),
            '{item_title}'     => (string) ($data['item_title'] ?? ''),
            '{check_in}'       => (string) ($data['check_in'] ?? $data['travel_date'] ?? ''),
            '{check_out}'      => (string) ($data['check_out'] ?? ''),
            '{travel_date}'    => (string) ($data['travel_date'] ?? $data['check_in'] ?? ''),
            '{travelers}'      => (string) $guestsCount,
            '{guests}'         => (string) $guestsCount,
            '{adults}'         => (string) ($data['adults'] ?? 1),
            '{children}'       => (string) ($data['children'] ?? 0),
            '{infants}'        => (string) ($data['infants'] ?? 0),
            '{subtotal}'       => (string) ($data['subtotal'] ?? ''),
            '{tax}'            => (string) ($data['tax_amount'] ?? ''),
            '{discount}'       => (string) ($data['discount_amount'] ?? ''),
            '{total}'          => (string) ($data['total_amount'] ?? ''),
            '{total_amount}'   => (string) ($data['total_amount'] ?? ''),
            '{status}'         => ucfirst((string) ($data['booking_status'] ?? 'pending')),
            '{payment_status}' => ucfirst((string) ($data['payment_status'] ?? 'pending')),
            '{payment_method}' => ucwords(str_replace('_', ' ', (string) ($data['payment_method'] ?? 'offline'))),
            '{voucher_url}'    => (string) ($data['voucher_url'] ?? ''),
            '{lookup_url}'     => (string) ($data['lookup_url'] ?? ''),
            '{inquiry_message}'=> (string) ($data['message'] ?? ''),
            '{site_name}'      => $siteName,
            '{site_url}'       => $siteUrl,
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }

    /**
     * Convert HTML email content to clean plain text.
     *
     * @param string $html
     * @param string $heading
     * @return string
     */
    public function htmlToPlainText(string $html, string $heading = ''): string
    {
        // Replace link tags with text (URL)
        $text = preg_replace('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);
        
        // Convert breaks, paragraphs, headers and divs to newlines
        $text = preg_replace('/<(br|p|div|tr|h[1-6])[^>]*>/i', "\n", (string) $text);
        
        // Strip remaining HTML tags
        $text = wp_strip_all_tags((string) $text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Clean up excess whitespace & empty lines
        $text = preg_replace("/\n\s+\n/", "\n\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);
        $text = trim((string) $text);

        if (!empty($heading)) {
            $text = "=== " . $heading . " ===\n\n" . $text;
        }

        return $text;
    }

    /**
     * Render an email template with theme override support.
     *
     * @param string               $templateFile
     * @param array<string, mixed> $args
     * @return string
     */
    public function renderTemplate(string $templateFile, array $args = []): string
    {
        $cleanFile = ltrim($templateFile, '/');
        $pluginTemplate = untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/emails/' . $cleanFile;

        // Theme override check (your-theme/tourivo/emails/filename.php or your-theme/tourivo/filename.php)
        $themeTemplate = function_exists('locate_template') ? locate_template([
            'tourivo/emails/' . $cleanFile,
            'tourivo/' . $cleanFile,
        ]) : '';

        $templateToLoad = (!empty($themeTemplate) && file_exists($themeTemplate)) ? $themeTemplate : $pluginTemplate;

        if (!file_exists($templateToLoad)) {
            return '';
        }

        extract($args, EXTR_SKIP);

        ob_start();
        include $templateToLoad;
        return ob_get_clean() ?: '';
    }

    /**
     * Resolve email subject with placeholders and fallback to old settings.
     *
     * @param string               $emailType
     * @param array<string, mixed> $data
     * @return string
     */
    protected function resolveSubject(string $emailType, array $data): string
    {
        $settingKey = "email_{$emailType}_subject";
        $subject = (string) Config::get($settingKey, '');

        // Backward compatibility fallbacks
        if (empty($subject)) {
            if ($emailType === 'customer_booking_received' || $emailType === 'customer_booking_confirmed') {
                $subject = (string) Config::get('email_customer_subject', '');
            } elseif ($emailType === 'admin_new_booking') {
                $subject = (string) Config::get('email_admin_subject', '');
            }
        }

        if (empty($subject)) {
            $defaults = Config::getDefaults();
            $subject = (string) ($defaults[$settingKey] ?? 'Notification from {site_name}');
        }

        $replaced = $this->replacePlaceholders($subject, $data);
        return $this->sanitizeHeader($replaced);
    }

    /**
     * Resolve email heading with placeholders.
     *
     * @param string               $emailType
     * @param array<string, mixed> $data
     * @return string
     */
    protected function resolveHeading(string $emailType, array $data): string
    {
        $settingKey = "email_{$emailType}_heading";
        $heading = (string) Config::get($settingKey, '');

        if (empty($heading)) {
            $defaults = Config::getDefaults();
            $heading = (string) ($defaults[$settingKey] ?? 'Notification');
        }

        $replaced = $this->replacePlaceholders($heading, $data);
        return $this->sanitizeHeader($replaced);
    }

    /**
     * Helper to load booking array and item details by ID.
     *
     * @param int $bookingId
     * @return array<string, mixed>|null
     */
    protected function getBookingData(int $bookingId): ?array
    {
        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bookingsTable} WHERE id = %d", $bookingId));
        if (!$booking) {
            return null;
        }

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$itemsTable} WHERE booking_id = %d LIMIT 1", $bookingId));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return [
            'id'              => (int) $booking->id,
            'booking_code'    => (string) $booking->booking_code,
            'customer_name'   => (string) $booking->customer_name,
            'customer_email'  => (string) $booking->customer_email,
            'customer_phone'  => (string) $booking->customer_phone,
            'billing_address' => (string) ($booking->billing_address ?? ''),
            'item_id'         => $item ? (int) $item->item_id : 0,
            'item_type'       => $item ? (string) $item->item_type : 'tour',
            'item_title'      => $item ? (string) $item->item_title : '',
            'check_in'        => $item ? substr((string) $item->check_in, 0, 10) : '',
            'check_out'       => ($item && !empty($item->check_out)) ? substr((string) $item->check_out, 0, 10) : '',
            'adults'          => $item ? (int) $item->adults_count : 1,
            'children'        => $item ? (int) $item->children_count : 0,
            'infants'         => $item ? (int) $item->infants_count : 0,
            'total_amount'    => Money::format((float) $booking->total_amount),
            'raw_total'       => (float) $booking->total_amount,
            'tax_amount'      => Money::format((float) ($booking->tax_amount ?? 0.0)),
            'raw_tax'         => (float) ($booking->tax_amount ?? 0.0),
            'discount_amount' => Money::format((float) ($booking->discount_amount ?? 0.0)),
            'raw_discount'    => (float) ($booking->discount_amount ?? 0.0),
            'currency'        => (string) $booking->currency,
            'payment_status'  => (string) $booking->payment_status,
            'booking_status'  => (string) $booking->booking_status,
            'payment_method'  => (string) $booking->payment_method,
            'customer_notes'  => (string) ($booking->customer_notes ?? ''),
        ];
    }
}
