<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Config\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EmailService
 *
 * Sends responsive HTML emails for booking confirmations, cancellations, and status updates.
 *
 * @package Tourivo\Services
 */
class EmailService
{
    /**
     * Send booking confirmation email to customer and admin.
     *
     * @param array<string, mixed> $bookingData
     * @return bool
     */
    public function sendBookingConfirmation(array $bookingData): bool
    {
        $to = $bookingData['customer_email'] ?? '';
        if (empty($to) || !is_email($to)) {
            return false;
        }

        $siteName    = get_bloginfo('name');
        $fromName    = Config::get('email_from_name', $siteName);
        $fromEmail   = Config::get('email_from_address', get_option('admin_email'));

        // Customer Subject
        $customSubject = Config::get('email_customer_subject', '');
        if (empty($customSubject)) {
            $subject = sprintf(
                /* translators: 1: Booking Code, 2: Site Name */
                __('Your Booking Confirmation #%1$s - %2$s', 'tourivo'),
                $bookingData['booking_code'] ?? '',
                $siteName
            );
        } else {
            $subject = $this->replacePlaceholders($customSubject, $bookingData);
        }

        $body = $this->renderTemplate('emails/booking-confirmation.php', [
            'booking'  => $bookingData,
            'siteName' => $siteName,
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromName . ' <' . $fromEmail . '>',
        ];

        // 1. Send to customer
        $sent = wp_mail($to, $subject, $body, $headers);

        // 2. Send notification to admin
        $adminEmail = (string) Config::get('email_notification_address', get_option('admin_email'));
        if (!empty($adminEmail) && is_email($adminEmail)) {
            $customAdminSubject = Config::get('email_admin_subject', '');
            if (empty($customAdminSubject)) {
                $adminSubject = sprintf(
                    /* translators: 1: Booking Code, 2: Customer Name */
                    __('[New Booking] #%1$s by %2$s', 'tourivo'),
                    $bookingData['booking_code'] ?? '',
                    $bookingData['customer_name'] ?? ''
                );
            } else {
                $adminSubject = $this->replacePlaceholders($customAdminSubject, $bookingData);
            }

            wp_mail($adminEmail, $adminSubject, $body, $headers);
        }

        return $sent;
    }

    /**
     * Send a test verification email to administrator.
     *
     * @param string $recipientEmail
     * @return bool
     */
    public function sendTestEmail(string $recipientEmail): bool
    {
        if (empty($recipientEmail) || !is_email($recipientEmail)) {
            return false;
        }

        $siteName  = get_bloginfo('name');
        $fromName  = Config::get('email_from_name', $siteName);
        $fromEmail = Config::get('email_from_address', get_option('admin_email'));

        /* translators: %s: Website name */
        $subject = sprintf(__('[Tourivo Test] Email Dispatch Verification - %s', 'tourivo'), $siteName);
        
        $mockBooking = [
            'id'             => 999,
            'booking_code'   => 'TRV-TEST-8888',
            'customer_name'  => 'John Traveler',
            'customer_email' => $recipientEmail,
            'customer_phone' => '+1 234 567 890',
            'item_title'     => 'Tropical Paradise Island Tour (Sample)',
            'check_in'       => gmdate('Y-m-d', strtotime('+7 days')),
            'check_out'      => gmdate('Y-m-d', strtotime('+10 days')),
            'adults'         => 2,
            'children'       => 1,
            'total_amount'   => '$450.00',
            'payment_status' => 'confirmed',
            'customer_notes' => 'Vegetarian meal requested.',
        ];

        $body = $this->renderTemplate('emails/booking-confirmation.php', [
            'booking'   => $mockBooking,
            'siteName'  => $siteName,
            'isTest'    => true,
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
        ];

        return wp_mail($recipientEmail, $subject, $body, $headers);
    }

    /**
     * Replace template tags like {customer_name}, {booking_code}, etc.
     *
     * @param string $text
     * @param array<string, mixed> $data
     * @return string
     */
    public function replacePlaceholders(string $text, array $data): string
    {
        $placeholders = [
            '{customer_name}'    => $data['customer_name'] ?? '',
            '{customer_email}'   => $data['customer_email'] ?? '',
            '{booking_code}'     => $data['booking_code'] ?? '',
            '{item_title}'       => $data['item_title'] ?? '',
            '{check_in}'         => $data['check_in'] ?? '',
            '{check_out}'        => $data['check_out'] ?? '',
            '{total_amount}'     => $data['total_amount'] ?? '',
            '{site_name}'        => get_bloginfo('name'),
            '{site_url}'         => home_url('/'),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }

    /**
     * Render an email template with arguments.
     *
     * @param string               $templatePath
     * @param array<string, mixed> $args
     * @return string
     */
    protected function renderTemplate(string $templatePath, array $args = []): string
    {
        $template = untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/' . ltrim($templatePath, '/');

        $themeTemplate = locate_template(['tourivo/' . ltrim($templatePath, '/')]);
        if (!empty($themeTemplate) && file_exists($themeTemplate)) {
            $template = $themeTemplate;
        }

        if (!file_exists($template)) {
            return '';
        }

        extract($args, EXTR_SKIP);

        ob_start();
        include $template;
        return ob_get_clean() ?: '';
    }
}
