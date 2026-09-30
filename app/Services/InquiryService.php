<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Common\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class InquiryService
 *
 * Manages traveler trip inquiries, expert consultation requests, and notifications.
 *
 * @package Tourivo\Services
 */
class InquiryService
{
    use Singleton;

    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Submit a new customer inquiry.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, inquiry_id?: int, message: string}
     */
    public function createInquiry(array $data): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tourivo_inquiries';

        // 1. Honeypot defense
        if (!empty($data['tourivo_inq_hp'])) {
            return ['success' => false, 'message' => __('Invalid submission.', 'tourivo')];
        }

        // 2. IP Rate Limiting (5 inquiries per 10 minutes)
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $rateLimitKey = 'trv_rl_inq_' . md5($ip);
        $attempts = (int) get_transient($rateLimitKey);

        if ($attempts >= 5) {
            return [
                'success' => false,
                'message' => __('Too many inquiries submitted. Please wait a few minutes before trying again.', 'tourivo'),
            ];
        }
        set_transient($rateLimitKey, $attempts + 1, 600);

        // 3. Unslash and Sanitize
        $itemId    = isset($data['item_id']) ? (int) $data['item_id'] : 0;
        $itemType  = isset($data['item_type']) ? sanitize_text_field(wp_unslash((string) $data['item_type'])) : 'tour';
        $name      = isset($data['customer_name']) ? sanitize_text_field(wp_unslash((string) $data['customer_name'])) : '';
        $email     = isset($data['customer_email']) ? sanitize_email(wp_unslash((string) $data['customer_email'])) : '';
        $phone     = isset($data['customer_phone']) ? sanitize_text_field(wp_unslash((string) $data['customer_phone'])) : '';
        $rawDate   = !empty($data['travel_date']) ? sanitize_text_field(wp_unslash((string) $data['travel_date'])) : null;
        $guests    = isset($data['guests']) ? max(1, min(50, (int) $data['guests'])) : 1;
        $message   = isset($data['message']) ? sanitize_textarea_field(wp_unslash((string) $data['message'])) : '';

        // 4. Validate Travel Date (strict Y-m-d and not in the past)
        $travelDate = null;
        if (!empty($rawDate)) {
            $parsedDate = \DateTime::createFromFormat('Y-m-d', trim($rawDate));
            if ($parsedDate && $parsedDate->format('Y-m-d') === trim($rawDate)) {
                $today = new \DateTime(wp_date('Y-m-d'));
                if ($parsedDate >= $today) {
                    $travelDate = $parsedDate->format('Y-m-d');
                }
            }
        }

        if (empty($name) || empty($email) || empty($message)) {
            return [
                'success' => false,
                'message' => __('Please fill in all required fields (Name, Email, Message).', 'tourivo'),
            ];
        }

        if (!is_email($email)) {
            return [
                'success' => false,
                'message' => __('Please provide a valid email address.', 'tourivo'),
            ];
        }

        $nowGmt = gmdate('Y-m-d H:i:s');
        $inserted = $wpdb->insert(
            $table,
            [
                'item_id'        => $itemId,
                'item_type'      => ($itemType === 'hotel_room' || $itemType === 'room') ? 'room' : 'tour',
                'customer_name'  => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'travel_date'    => $travelDate,
                'guests'         => $guests,
                'message'        => $message,
                'status'         => 'new',
                'ip_address'     => $ip,
                'created_at'     => $nowGmt,
                'updated_at'     => $nowGmt,
            ]
        );

        if (!$inserted) {
            return [
                'success' => false,
                'message' => __('Failed to save your inquiry. Please try again later.', 'tourivo'),
            ];
        }

        $inquiryId = (int) $wpdb->insert_id;

        // Dispatch admin notification email
        $itemPost = get_post($itemId);
        $itemTitle = $itemPost ? $itemPost->post_title : __('General Travel Inquiry', 'tourivo');
        $adminEmail = (string) \Tourivo\Config\Config::get('email_notification_address', get_option('admin_email'));

        /* translators: 1: Site name, 2: Item title */
        $subject = sprintf(__('[%1$s] New Trip Inquiry for: %2$s', 'tourivo'), get_bloginfo('name'), $itemTitle);
        $body  = "<h2>" . esc_html__('New Traveler Inquiry Received', 'tourivo') . "</h2>\n";
        $body .= "<p><strong>" . esc_html__('Trip / Stay:', 'tourivo') . "</strong> " . esc_html($itemTitle) . "</p>\n";
        $body .= "<p><strong>" . esc_html__('Traveler Name:', 'tourivo') . "</strong> " . esc_html($name) . "</p>\n";
        $body .= "<p><strong>" . esc_html__('Email:', 'tourivo') . "</strong> <a href=\"mailto:" . esc_attr($email) . "\">" . esc_html($email) . "</a></p>\n";
        $body .= "<p><strong>" . esc_html__('Phone / WhatsApp:', 'tourivo') . "</strong> " . esc_html($phone ?: 'N/A') . "</p>\n";
        if ($travelDate) {
            $body .= "<p><strong>" . esc_html__('Expected Travel Date:', 'tourivo') . "</strong> " . esc_html($travelDate) . "</p>\n";
        }
        $body .= "<p><strong>" . esc_html__('Travelers:', 'tourivo') . "</strong> " . esc_html((string) $guests) . "</p>\n";
        $body .= "<p><strong>" . esc_html__('Question / Request:', 'tourivo') . "</strong><br>" . nl2br(esc_html($message)) . "</p>\n";
        $body .= "<hr><p><small>" . esc_html__('Manage inquiries in WP Admin -> Tourivo -> Inquiries', 'tourivo') . "</small></p>";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $name . ' <' . $email . '>',
        ];

        wp_mail($adminEmail, $subject, $body, $headers);

        return [
            'success'    => true,
            'inquiry_id' => $inquiryId,
            'message'    => __('Thank you! Your inquiry has been sent to our travel specialists. We will get back to you shortly.', 'tourivo'),
        ];
    }

    /**
     * Retrieve inquiries with filtering.
     *
     * @param array<string, mixed> $args
     * @return array<int, array<string, mixed>>
     */
    public function getInquiries(array $args = []): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tourivo_inquiries';

        $status = $args['status'] ?? '';
        $limit  = isset($args['limit']) ? (int) $args['limit'] : 20;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

        $where = '1=1';
        $params = [];

        if (!empty($status) && in_array($status, ['new', 'replied', 'closed'], true)) {
            $where .= ' AND status = %s';
            $params[] = $status;
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (array) $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Update inquiry status (new, replied, closed).
     */
    public function updateStatus(int $id, string $status): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tourivo_inquiries';

        if (!in_array($status, ['new', 'replied', 'closed'], true)) {
            return false;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $table,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return $updated !== false;
    }

    /**
     * Delete an inquiry.
     */
    public function deleteInquiry(int $id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tourivo_inquiries';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->delete($table, ['id' => $id]);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
