<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Common\Container;
use Tourivo\Config\Config;
use Tourivo\Shortcodes\BookingLookupShortcode;
use Tourivo\Shortcodes\ThankYouShortcode;
use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PaymentService
 *
 * Manages offline payment methods (Structured Bank Transfer, Cash on Arrival),
 * customer transaction reference submissions, and admin payment status updates.
 *
 * @package Tourivo\Services
 */
class PaymentService
{
    /**
     * Retrieve all active offline payment methods and their display configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getOfflinePaymentMethods(): array
    {
        $bankEnabled = (bool) Config::get('bank_transfer_enabled', true);
        $cashEnabled = (bool) Config::get('cash_on_arrival_enabled', true);

        $methods = [];

        if ($bankEnabled) {
            $methods['bank_transfer'] = [
                'id'          => 'bank_transfer',
                'enabled'     => true,
                'title'       => __('Direct Bank Transfer', 'tourivo'),
                'description' => __('Make your payment directly into our bank account. Please use your Booking Reference Code as the payment reference.', 'tourivo'),
                'details'     => $this->getBankDetails(),
            ];
        }

        if ($cashEnabled) {
            $methods['cash_on_arrival'] = [
                'id'           => 'cash_on_arrival',
                'enabled'      => true,
                'title'        => __('Cash on Arrival / Pay at Check-in', 'tourivo'),
                'description'  => __('Pay in cash or card at our welcome desk or tour guide upon arrival.', 'tourivo'),
                'instructions' => (string) Config::get('cash_on_arrival_instructions', ''),
            ];
        }

        // Generic fallback if neither specific method is configured
        if (empty($methods)) {
            $methods['offline'] = [
                'id'           => 'offline',
                'enabled'      => true,
                'title'        => __('Offline / Manual Payment', 'tourivo'),
                'description'  => __('Manual payment arrangement with tour operator.', 'tourivo'),
                'instructions' => (string) Config::get('offline_payment_instructions', ''),
            ];
        }

        return $methods;
    }

    /**
     * Get structured bank transfer account details from configuration.
     *
     * @return array{
     *     bank_name: string,
     *     account_name: string,
     *     account_number: string,
     *     iban: string,
     *     swift_routing: string,
     *     branch: string,
     *     instructions: string
     * }
     */
    public function getBankDetails(): array
    {
        return [
            'bank_name'      => (string) Config::get('bank_name', ''),
            'account_name'   => (string) Config::get('bank_account_name', ''),
            'account_number' => (string) Config::get('bank_account_number', ''),
            'iban'           => (string) Config::get('bank_iban', ''),
            'swift_routing'  => (string) Config::get('bank_swift_routing', ''),
            'branch'         => (string) Config::get('bank_branch', ''),
            'instructions'   => (string) Config::get('bank_instructions', ''),
        ];
    }

    /**
     * Render structured, copy-friendly HTML card for Bank Transfer details.
     *
     * @return string
     */
    public function getBankDetailsHtml(): string
    {
        $b = $this->getBankDetails();

        if (empty($b['bank_name']) && empty($b['account_number'])) {
            $generic = (string) Config::get('offline_payment_instructions', '');
            return !empty($generic) ? '<div class="tourivo-offline-text">' . nl2br(esc_html($generic)) . '</div>' : '';
        }

        ob_start();
        ?>
        <div class="tourivo-bank-details-card">
            <div class="bank-card-header">
                <h4>🏦 <?php esc_html_e('Bank Account Details', 'tourivo'); ?></h4>
            </div>
            <table class="tourivo-bank-table">
                <tbody>
                    <?php if (!empty($b['bank_name'])) : ?>
                        <tr>
                            <th><?php esc_html_e('Bank Name:', 'tourivo'); ?></th>
                            <td><strong><?php echo esc_html($b['bank_name']); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($b['account_name'])) : ?>
                        <tr>
                            <th><?php esc_html_e('Account Name:', 'tourivo'); ?></th>
                            <td><?php echo esc_html($b['account_name']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($b['account_number'])) : ?>
                        <tr>
                            <th><?php esc_html_e('Account Number:', 'tourivo'); ?></th>
                            <td>
                                <code class="copyable-account"><?php echo esc_html($b['account_number']); ?></code>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($b['iban'])) : ?>
                        <tr>
                            <th><?php esc_html_e('IBAN:', 'tourivo'); ?></th>
                            <td><code><?php echo esc_html($b['iban']); ?></code></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($b['swift_routing'])) : ?>
                        <tr>
                            <th><?php esc_html_e('SWIFT / BIC / Routing:', 'tourivo'); ?></th>
                            <td><code><?php echo esc_html($b['swift_routing']); ?></code></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($b['branch'])) : ?>
                        <tr>
                            <th><?php esc_html_e('Branch:', 'tourivo'); ?></th>
                            <td><?php echo esc_html($b['branch']); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!empty($b['instructions'])) : ?>
                <div class="bank-instructions-note">
                    <p>💡 <em><?php echo nl2br(esc_html($b['instructions'])); ?></em></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Submit customer transaction / bank deposit reference number.
     *
     * @param string      $bookingCode
     * @param string      $transactionId
     * @param string|null $token
     * @param string|null $customerEmail
     * @param string|null $notes
     * @return array{success: bool, message: string}
     */
    public function submitTransactionReference(
        string $bookingCode,
        string $transactionId,
        ?string $token = null,
        ?string $customerEmail = null,
        ?string $notes = null
    ): array {
        global $wpdb;

        $cleanCode = strtoupper(sanitize_text_field(trim($bookingCode)));
        $cleanTxn  = sanitize_text_field(trim($transactionId));

        if (empty($cleanCode) || empty($cleanTxn)) {
            return [
                'success' => false,
                'message' => __('Please provide a valid booking code and transaction reference.', 'tourivo'),
            ];
        }

        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE booking_code = %s LIMIT 1",
            $cleanCode
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$booking) {
            return [
                'success' => false,
                'message' => __('No reservation was found matching this reference code.', 'tourivo'),
            ];
        }

        // Security Authentication
        $isAuthorized = false;

        // 1. HMAC Thank You token verification
        if (!empty($token) && ThankYouShortcode::verifyThankYouToken((int) $booking->id, $token)) {
            $isAuthorized = true;
        }

        // 2. Voucher token verification
        if (!$isAuthorized && !empty($token)) {
            $expectedVoucher = BookingLookupShortcode::generateVoucherToken((int) $booking->id, (string) $booking->customer_email, $booking->access_key ?? null);
            if (hash_equals($expectedVoucher, $token)) {
                $isAuthorized = true;
            }
        }

        // 3. Customer Email verification
        if (!$isAuthorized && !empty($customerEmail) && strcasecmp((string) $booking->customer_email, trim($customerEmail)) === 0) {
            $isAuthorized = true;
        }

        // 4. Logged-in customer check
        if (!$isAuthorized && is_user_logged_in()) {
            $currentUser = wp_get_current_user();
            if ((int) $booking->customer_id === (int) $currentUser->ID || strcasecmp((string) $booking->customer_email, (string) $currentUser->user_email) === 0) {
                $isAuthorized = true;
            }
        }

        // 5. Admin check
        if (!$isAuthorized && current_user_can('manage_tourivo_bookings')) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            return [
                'success' => false,
                'message' => __('Unauthorized access. Could not verify booking authorization.', 'tourivo'),
            ];
        }

        $now     = current_time('mysql');
        $oldNote = (string) ($booking->admin_notes ?? '');
        $noteMsg = sprintf('[%s] %s: %s', $now, __('Customer submitted payment reference', 'tourivo'), $cleanTxn);
        if (!empty($notes)) {
            $noteMsg .= ' (' . sanitize_text_field($notes) . ')';
        }
        $newNotes = trim($oldNote . "\n" . $noteMsg);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $bookingsTable,
            [
                'transaction_id' => $cleanTxn,
                'admin_notes'    => $newNotes,
                'updated_at'     => $now,
            ],
            ['id' => (int) $booking->id]
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        LogService::log(
            (int) $booking->id,
            'payment_reference_submitted',
            sprintf(__('Customer submitted transaction reference %1$s for booking #%2$s', 'tourivo'), $cleanTxn, $cleanCode)
        );

        do_action('tourivo/payment_reference_submitted', (int) $booking->id, $cleanTxn);

        return [
            'success' => true,
            'message' => __('Payment reference submitted successfully. Our accounts team will review and confirm your booking.', 'tourivo'),
        ];
    }

    /**
     * Admin action: Record received payment, adjust paid/due amounts, and update status.
     *
     * @param int                  $bookingId
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, booking?: array<string, mixed>}
     */
    public function recordPayment(int $bookingId, array $data): array
    {
        if (!current_user_can('manage_tourivo_bookings')) {
            return [
                'success' => false,
                'message' => __('Unauthorized access. You do not have permission to record payments.', 'tourivo'),
            ];
        }

        if ($bookingId <= 0) {
            return [
                'success' => false,
                'message' => __('Invalid booking ID.', 'tourivo'),
            ];
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bookingsTable} WHERE id = %d LIMIT 1", $bookingId));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$booking) {
            return [
                'success' => false,
                'message' => __('Booking record not found.', 'tourivo'),
            ];
        }

        $totalAmount   = (float) ($booking->total_amount ?? 0.0);
        $amount        = isset($data['amount']) ? max(0.0, (float) $data['amount']) : $totalAmount;
        $paymentStatus = in_array($data['payment_status'] ?? '', ['paid', 'partially_paid', 'pending', 'refunded'], true)
            ? (string) $data['payment_status']
            : ($amount >= $totalAmount ? 'paid' : 'partially_paid');

        $dueAmount     = max(0.0, $totalAmount - $amount);
        $paymentMethod = sanitize_text_field((string) ($data['payment_method'] ?? ($booking->payment_method ?: 'bank_transfer')));
        $transactionId = sanitize_text_field((string) ($data['transaction_id'] ?? ($booking->transaction_id ?: '')));
        $autoConfirm   = !empty($data['auto_confirm']);
        $note          = sanitize_textarea_field((string) ($data['note'] ?? ''));
        $now           = current_time('mysql');

        $oldNotes = (string) ($booking->admin_notes ?? '');
        $noteText = sprintf(
            '[%s] %s: %s (%s) via %s. %s',
            $now,
            __('Payment Recorded', 'tourivo'),
            Money::format($amount),
            ucfirst($paymentStatus),
            ucwords(str_replace('_', ' ', $paymentMethod)),
            $note
        );
        $newNotes = trim($oldNotes . "\n" . trim($noteText));

        // Update database record
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $bookingsTable,
            [
                'paid_amount'    => $amount,
                'due_amount'     => $dueAmount,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'admin_notes'    => $newNotes,
                'updated_at'     => $now,
            ],
            ['id' => $bookingId]
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        LogService::log(
            $bookingId,
            'payment_recorded',
            sprintf(
                /* translators: 1: Amount formatted, 2: Payment status, 3: Method */
                __('Admin recorded payment of %1$s (%2$s) via %3$s.', 'tourivo'),
                Money::format($amount),
                ucfirst($paymentStatus),
                ucwords(str_replace('_', ' ', $paymentMethod))
            )
        );

        // Auto-confirm booking status if pending
        if ($autoConfirm && $booking->booking_status === 'pending') {
            /** @var BookingService $bookingService */
            $bookingService = Container::getInstance()->get(BookingService::class);
            $bookingService->changeStatus($bookingId, 'confirmed', ['source' => 'admin_payment']);
        }

        do_action('tourivo/payment_recorded', $bookingId, [
            'amount'         => $amount,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
        ]);

        return [
            'success' => true,
            'message' => __('Payment record updated successfully.', 'tourivo'),
            'booking' => [
                'id'             => $bookingId,
                'paid_amount'    => $amount,
                'due_amount'     => $dueAmount,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
            ],
        ];
    }
}
