<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ExportService
 *
 * Handles advanced filtering, summary calculations, CSV/Excel generation,
 * and formula-injection-safe reporting for customer bookings.
 *
 * @package Tourivo\Services
 */
class ExportService
{
    /**
     * Dangerous initial characters for spreadsheet formula injection.
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Query bookings based on advanced filters.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function queryBookings(array $filters = []): array
    {
        global $wpdb;

        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        $status        = sanitize_text_field((string) ($filters['status'] ?? 'all'));
        $paymentStatus = sanitize_text_field((string) ($filters['payment_status'] ?? 'all'));
        $dateType      = sanitize_text_field((string) ($filters['date_type'] ?? 'booking_date'));
        $startDate     = !empty($filters['start_date']) ? sanitize_text_field((string) $filters['start_date']) : '';
        $endDate       = !empty($filters['end_date']) ? sanitize_text_field((string) $filters['end_date']) : '';
        $itemId        = isset($filters['item_id']) ? (int) $filters['item_id'] : 0;
        $itemType      = !empty($filters['item_type']) ? sanitize_text_field((string) $filters['item_type']) : 'all';
        $search        = !empty($filters['search']) ? sanitize_text_field((string) $filters['search']) : '';

        // 1. If filtering by travel dates, specific item ID, or item type, resolve matching booking IDs first
        $matchingBookingIds = null;
        $needsItemFilter = ($dateType === 'travel_date' && ($startDate !== '' || $endDate !== ''))
            || $itemId > 0
            || ($itemType !== 'all' && $itemType !== '');

        if ($needsItemFilter) {
            $itemWhere = ['1=1'];
            $itemParams = [];

            if ($dateType === 'travel_date') {
                if ($startDate !== '') {
                    $itemWhere[]  = 'check_in >= %s';
                    $itemParams[] = $startDate . ' 00:00:00';
                }
                if ($endDate !== '') {
                    $itemWhere[]  = 'check_in <= %s';
                    $itemParams[] = $endDate . ' 23:59:59';
                }
            }

            if ($itemId > 0) {
                $itemWhere[]  = 'item_id = %d';
                $itemParams[] = $itemId;
            }

            if ($itemType !== 'all' && $itemType !== '') {
                $itemWhere[]  = 'item_type = %s';
                $itemParams[] = $itemType;
            }

            $itemWhereSql = implode(' AND ', $itemWhere);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $rawItemSql = "SELECT DISTINCT booking_id FROM {$itemsTable} WHERE {$itemWhereSql}";
            $itemSql    = !empty($itemParams) ? $wpdb->prepare($rawItemSql, ...$itemParams) : $rawItemSql;
            $itemRows   = (array) $wpdb->get_results($itemSql);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            $matchingBookingIds = array_values(array_filter(array_map(
                static fn ($r): int => (int) ($r->booking_id ?? 0),
                $itemRows
            )));

            if (empty($matchingBookingIds)) {
                return [];
            }
        }

        // 2. Build Bookings Query
        $where  = ['1=1'];
        $params = [];

        if ($status === 'cancel_requested') {
            $where[] = "cancel_requested_at IS NOT NULL AND booking_status != 'cancelled'";
        } elseif ($status !== 'all' && $status !== '') {
            $where[]  = 'booking_status = %s';
            $params[] = $status;
        }

        if ($paymentStatus !== 'all' && $paymentStatus !== '') {
            $where[]  = 'payment_status = %s';
            $params[] = $paymentStatus;
        }

        if ($dateType === 'booking_date') {
            if ($startDate !== '') {
                $where[]  = 'created_at >= %s';
                $params[] = $startDate . ' 00:00:00';
            }
            if ($endDate !== '') {
                $where[]  = 'created_at <= %s';
                $params[] = $endDate . ' 23:59:59';
            }
        }

        if ($search !== '') {
            $where[] = '(booking_code LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)';
            $like    = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if ($matchingBookingIds !== null) {
            $placeholders = implode(',', array_fill(0, count($matchingBookingIds), '%d'));
            $where[]      = "id IN ({$placeholders})";
            foreach ($matchingBookingIds as $bId) {
                $params[] = $bId;
            }
        }

        $whereSql = implode(' AND ', $where);
        $orderSql = 'ORDER BY id DESC';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rawBookingsSql = "SELECT * FROM {$bookingsTable} WHERE {$whereSql} {$orderSql}";
        $bookingsSql    = !empty($params) ? $wpdb->prepare($rawBookingsSql, ...$params) : $rawBookingsSql;
        $bookings       = (array) $wpdb->get_results($bookingsSql, ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (empty($bookings)) {
            return [];
        }

        // 3. Fetch Line Items in Bulk
        $bookingIds = array_values(array_filter(array_map(
            static fn ($b): int => (int) ($b['id'] ?? 0),
            $bookings
        )));

        $itemsByBooking = [];
        if (!empty($bookingIds)) {
            $placeholders = implode(',', array_fill(0, count($bookingIds), '%d'));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $itemsSql  = "SELECT * FROM {$itemsTable} WHERE booking_id IN ({$placeholders}) ORDER BY id ASC";
            $itemRows  = (array) $wpdb->get_results($wpdb->prepare($itemsSql, ...$bookingIds), ARRAY_A);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            foreach ($itemRows as $item) {
                $bId = (int) ($item['booking_id'] ?? 0);
                $itemsByBooking[$bId][] = $item;
            }
        }

        // 4. Merge line items into bookings
        $results = [];
        foreach ($bookings as $b) {
            $bId       = (int) ($b['id'] ?? 0);
            $lineItems = $itemsByBooking[$bId] ?? [];
            $firstItem = $lineItems[0] ?? null;

            $totalGuests = 0;
            foreach ($lineItems as $li) {
                $adults   = (int) ($li['adults_count'] ?? 1);
                $children = (int) ($li['children_count'] ?? 0);
                $infants  = (int) ($li['infants_count'] ?? 0);
                // The same party travels on every item of a package, so count the largest party, not the sum.
                $totalGuests = max($totalGuests, $adults + $children + $infants);
            }

            if ($totalGuests === 0 && $firstItem) {
                $totalGuests = (int) ($firstItem['quantity'] ?? 1);
            }

            $b['items']        = $lineItems;
            $span = \Tourivo\Support\BookingItems::span($lineItems);
            $b['item_title']   = $lineItems !== [] ? \Tourivo\Support\BookingItems::title($lineItems, $bId) : 'N/A';
            $b['item_type']    = $firstItem['item_type'] ?? 'tour';
            $b['check_in']     = count($lineItems) > 1 ? $span['check_in'] : ($firstItem['check_in'] ?? '');
            $b['check_out']    = count($lineItems) > 1 ? $span['check_out'] : ($firstItem['check_out'] ?? '');
            $b['total_guests'] = max(1, $totalGuests);

            $results[] = $b;
        }

        return $results;
    }

    /**
     * Get summary metrics for a given filter set.
     *
     * @param array<string, mixed> $filters
     * @return array{total_bookings: int, confirmed_bookings: int, total_revenue: float, confirmed_revenue: float, total_travelers: int, total_tax: float, total_discount: float}
     */
    public function getExportSummary(array $filters = []): array
    {
        $bookings = $this->queryBookings($filters);

        $totalBookings     = count($bookings);
        $confirmedBookings = 0;
        $totalRevenue      = 0.0;
        $confirmedRevenue  = 0.0;
        $totalTravelers    = 0;
        $totalTax          = 0.0;
        $totalDiscount     = 0.0;

        foreach ($bookings as $b) {
            $amount   = (float) ($b['total_amount'] ?? 0.0);
            $tax      = (float) ($b['tax_amount'] ?? 0.0);
            $discount = (float) ($b['discount_amount'] ?? 0.0);
            $status   = (string) ($b['booking_status'] ?? '');
            $guests   = (int) ($b['total_guests'] ?? 1);

            $totalRevenue   += $amount;
            $totalTax       += $tax;
            $totalDiscount  += $discount;
            $totalTravelers += $guests;

            if ($status !== 'cancelled') {
                $confirmedBookings++;
                $confirmedRevenue += $amount;
            }
        }

        return [
            'total_bookings'     => $totalBookings,
            'confirmed_bookings' => $confirmedBookings,
            'total_revenue'      => round($totalRevenue, 2),
            'confirmed_revenue'  => round($confirmedRevenue, 2),
            'total_travelers'    => $totalTravelers,
            'total_tax'          => round($totalTax, 2),
            'total_discount'     => round($totalDiscount, 2),
        ];
    }

    /**
     * Sanitize a single CSV cell value to guard against Spreadsheet Formula Injection.
     *
     * @param mixed $value
     * @return string
     */
    public function sanitizeCsvCell(mixed $value): string
    {
        $str = (string) ($value ?? '');
        if ($str === '') {
            return '';
        }

        // If cell starts with formula prefix, prefix with a single quote '
        if (in_array($str[0], self::FORMULA_PREFIXES, true)) {
            return "'" . $str;
        }

        return $str;
    }

    /**
     * Generate CSV file content and stream to destination resource.
     *
     * @param array<string, mixed> $filters
     * @param string               $mode         'bookings', 'items', or 'financial_summary'
     * @param resource|null        $outputStream Destination stream or null for php://output
     * @return void
     */
    public function generateCsv(array $filters = [], string $mode = 'bookings', $outputStream = null): void
    {
        $closeStream = false;
        if (!is_resource($outputStream)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
            $outputStream = fopen('php://output', 'w');
            $closeStream  = true;
        }

        if (!$outputStream) {
            return;
        }

        // 1. Emit UTF-8 BOM for Microsoft Excel Unicode support
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
        fwrite($outputStream, "\xEF\xBB\xBF");

        $bookings = $this->queryBookings($filters);

        if ($mode === 'financial_summary') {
            $this->writeFinancialSummaryCsv($outputStream, $filters, $bookings);
        } elseif ($mode === 'items') {
            $this->writeItemizedCsv($outputStream, $bookings);
        } else {
            $this->writeBookingsCsv($outputStream, $bookings);
        }

        if ($closeStream) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            fclose($outputStream);
        }
    }

    /**
     * Write standard bookings CSV rows.
     *
     * @param resource                      $stream
     * @param array<int, array<string, mixed>> $bookings
     * @return void
     */
    protected function writeBookingsCsv($stream, array $bookings): void
    {
        // Header
        fputcsv($stream, [
            __('Booking Code', 'tourivo'),
            __('Customer Name', 'tourivo'),
            __('Customer Email', 'tourivo'),
            __('Customer Phone', 'tourivo'),
            __('Trip / Stay Title', 'tourivo'),
            __('Type', 'tourivo'),
            __('Check-in Date', 'tourivo'),
            __('Check-out Date', 'tourivo'),
            __('Travelers', 'tourivo'),
            __('Subtotal', 'tourivo'),
            __('Tax Amount', 'tourivo'),
            __('Discount Amount', 'tourivo'),
            __('Total Amount', 'tourivo'),
            __('Currency', 'tourivo'),
            __('Payment Method', 'tourivo'),
            __('Payment Status', 'tourivo'),
            __('Booking Status', 'tourivo'),
            __('Customer Notes', 'tourivo'),
            __('Booking Created At', 'tourivo'),
        ]);

        foreach ($bookings as $row) {
            $totalAmount    = (float) ($row['total_amount'] ?? 0.0);
            $taxAmount      = (float) ($row['tax_amount'] ?? 0.0);
            $discountAmount = (float) ($row['discount_amount'] ?? 0.0);
            $subtotal       = max(0.0, ($totalAmount - $taxAmount + $discountAmount));

            fputcsv($stream, [
                $this->sanitizeCsvCell($row['booking_code'] ?? ''),
                $this->sanitizeCsvCell($row['customer_name'] ?? ''),
                $this->sanitizeCsvCell($row['customer_email'] ?? ''),
                $this->sanitizeCsvCell($row['customer_phone'] ?? ''),
                $this->sanitizeCsvCell($row['item_title'] ?? 'N/A'),
                $this->sanitizeCsvCell(ucfirst((string) ($row['item_type'] ?? 'Tour'))),
                $this->sanitizeCsvCell($row['check_in'] ?? ''),
                $this->sanitizeCsvCell($row['check_out'] ?? ''),
                $this->sanitizeCsvCell((string) ($row['total_guests'] ?? 1)),
                $this->sanitizeCsvCell(number_format($subtotal, 2, '.', '')),
                $this->sanitizeCsvCell(number_format($taxAmount, 2, '.', '')),
                $this->sanitizeCsvCell(number_format($discountAmount, 2, '.', '')),
                $this->sanitizeCsvCell(number_format($totalAmount, 2, '.', '')),
                $this->sanitizeCsvCell($row['currency'] ?? 'USD'),
                $this->sanitizeCsvCell($row['payment_method'] ?? 'offline'),
                $this->sanitizeCsvCell($row['payment_status'] ?? 'pending'),
                $this->sanitizeCsvCell($row['booking_status'] ?? 'pending'),
                $this->sanitizeCsvCell($row['customer_notes'] ?? ''),
                $this->sanitizeCsvCell($row['created_at'] ?? ''),
            ]);
        }
    }

    /**
     * Write itemized line-item CSV rows.
     *
     * @param resource                      $stream
     * @param array<int, array<string, mixed>> $bookings
     * @return void
     */
    protected function writeItemizedCsv($stream, array $bookings): void
    {
        fputcsv($stream, [
            __('Booking Code', 'tourivo'),
            __('Customer Name', 'tourivo'),
            __('Customer Email', 'tourivo'),
            __('Item ID', 'tourivo'),
            __('Item Title', 'tourivo'),
            __('Item Type', 'tourivo'),
            __('Check-in Date', 'tourivo'),
            __('Check-out Date', 'tourivo'),
            __('Adults', 'tourivo'),
            __('Children', 'tourivo'),
            __('Infants', 'tourivo'),
            __('Total Guests', 'tourivo'),
            __('Unit Price', 'tourivo'),
            __('Line Total', 'tourivo'),
            __('Booking Status', 'tourivo'),
            __('Payment Status', 'tourivo'),
            __('Booking Date', 'tourivo'),
        ]);

        foreach ($bookings as $b) {
            $lineItems = $b['items'] ?? [];
            if (empty($lineItems)) {
                continue;
            }

            foreach ($lineItems as $item) {
                $adults   = (int) ($item['adults_count'] ?? 1);
                $children = (int) ($item['children_count'] ?? 0);
                $infants  = (int) ($item['infants_count'] ?? 0);
                $guests   = max(1, $adults + $children + $infants);

                fputcsv($stream, [
                    $this->sanitizeCsvCell($b['booking_code'] ?? ''),
                    $this->sanitizeCsvCell($b['customer_name'] ?? ''),
                    $this->sanitizeCsvCell($b['customer_email'] ?? ''),
                    $this->sanitizeCsvCell((string) ($item['item_id'] ?? '')),
                    $this->sanitizeCsvCell($item['item_title'] ?? ''),
                    $this->sanitizeCsvCell(ucfirst((string) ($item['item_type'] ?? 'Tour'))),
                    $this->sanitizeCsvCell($item['check_in'] ?? ''),
                    $this->sanitizeCsvCell($item['check_out'] ?? ''),
                    $this->sanitizeCsvCell((string) $adults),
                    $this->sanitizeCsvCell((string) $children),
                    $this->sanitizeCsvCell((string) $infants),
                    $this->sanitizeCsvCell((string) $guests),
                    $this->sanitizeCsvCell(number_format((float) ($item['unit_price'] ?? 0.0), 2, '.', '')),
                    $this->sanitizeCsvCell(number_format((float) ($item['total_price'] ?? 0.0), 2, '.', '')),
                    $this->sanitizeCsvCell($b['booking_status'] ?? ''),
                    $this->sanitizeCsvCell($b['payment_status'] ?? ''),
                    $this->sanitizeCsvCell($b['created_at'] ?? ''),
                ]);
            }
        }
    }

    /**
     * Write high-level financial summary CSV.
     *
     * @param resource                      $stream
     * @param array<string, mixed>          $filters
     * @param array<int, array<string, mixed>> $bookings
     * @return void
     */
    protected function writeFinancialSummaryCsv($stream, array $filters, array $bookings): void
    {
        $summary = $this->getExportSummary($filters);

        fputcsv($stream, [__('Tourivo Bookings Financial Summary Report', 'tourivo')]);
        fputcsv($stream, [__('Generated On', 'tourivo'), gmdate('Y-m-d H:i:s') . ' UTC']);
        fputcsv($stream, [
            __('Date Filter', 'tourivo'),
            (!empty($filters['start_date']) || !empty($filters['end_date']))
                ? (($filters['start_date'] ?? 'All') . ' to ' . ($filters['end_date'] ?? 'All'))
                : __('All Time', 'tourivo')
        ]);
        fputcsv($stream, [__('Status Filter', 'tourivo'), ucfirst((string) ($filters['status'] ?? 'all'))]);
        fputcsv($stream, []); // Empty spacing line

        fputcsv($stream, [__('Metric', 'tourivo'), __('Value', 'tourivo')]);
        fputcsv($stream, [__('Total Bookings Recorded', 'tourivo'), (string) $summary['total_bookings']]);
        fputcsv($stream, [__('Confirmed / Active Bookings', 'tourivo'), (string) $summary['confirmed_bookings']]);
        fputcsv($stream, [__('Total Travelers / Guests', 'tourivo'), (string) $summary['total_travelers']]);
        fputcsv($stream, [__('Gross Total Booking Value', 'tourivo'), number_format($summary['total_revenue'], 2, '.', '')]);
        fputcsv($stream, [__('Total Revenue (Excluding Cancelled)', 'tourivo'), number_format($summary['confirmed_revenue'], 2, '.', '')]);
        fputcsv($stream, [__('Total Tax Collected', 'tourivo'), number_format($summary['total_tax'], 2, '.', '')]);
        fputcsv($stream, [__('Total Discounts Granted', 'tourivo'), number_format($summary['total_discount'], 2, '.', '')]);
    }

    /**
     * Handle HTTP GET export download request from admin-post.php.
     *
     * @return void
     */
    public static function handleExportRequest(): void
    {
        if (!current_user_can('manage_tourivo_bookings')) {
            wp_die(esc_html__('Unauthorized access.', 'tourivo'), 403);
        }

        check_admin_referer('tourivo_export_csv_action');

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $filters = [
            'status'         => isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'all',
            'payment_status' => isset($_GET['payment_status']) ? sanitize_text_field(wp_unslash($_GET['payment_status'])) : 'all',
            'date_type'      => isset($_GET['date_type']) ? sanitize_text_field(wp_unslash($_GET['date_type'])) : 'booking_date',
            'start_date'     => isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : '',
            'end_date'       => isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : '',
            'item_id'        => isset($_GET['item_id']) ? absint($_GET['item_id']) : 0,
            'item_type'      => isset($_GET['item_type']) ? sanitize_text_field(wp_unslash($_GET['item_type'])) : 'all',
            'search'         => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        ];
        $mode = isset($_GET['export_type']) ? sanitize_text_field(wp_unslash($_GET['export_type'])) : 'bookings';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if (!in_array($mode, ['bookings', 'items', 'financial_summary'], true)) {
            $mode = 'bookings';
        }

        $filename = 'tourivo-bookings-report-' . gmdate('Y-m-d') . '.csv';

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $service = new self();
        $service->generateCsv($filters, $mode);

        if (!defined('TOURIVO_TEST_MODE')) {
            exit;
        }
    }

    /**
     * AJAX handler for live export preview (count and totals).
     *
     * @return void
     */
    public function handlePreviewAjax(): void
    {
        if (!current_user_can('manage_tourivo_bookings')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tourivo')], 403);
        }

        check_ajax_referer('tourivo_admin_nonce', 'nonce');

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
        $req = !empty($_POST) ? $_POST : $_GET;
        $filters = [
            'status'         => isset($req['status']) ? sanitize_text_field(wp_unslash($req['status'])) : 'all',
            'payment_status' => isset($req['payment_status']) ? sanitize_text_field(wp_unslash($req['payment_status'])) : 'all',
            'date_type'      => isset($req['date_type']) ? sanitize_text_field(wp_unslash($req['date_type'])) : 'booking_date',
            'start_date'     => isset($req['start_date']) ? sanitize_text_field(wp_unslash($req['start_date'])) : '',
            'end_date'       => isset($req['end_date']) ? sanitize_text_field(wp_unslash($req['end_date'])) : '',
            'item_id'        => isset($req['item_id']) ? absint($req['item_id']) : 0,
            'item_type'      => isset($req['item_type']) ? sanitize_text_field(wp_unslash($req['item_type'])) : 'all',
            'search'         => isset($req['s']) ? sanitize_text_field(wp_unslash($req['s'])) : '',
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

        $summary = $this->getExportSummary($filters);

        wp_send_json_success([
            'count'           => $summary['total_bookings'],
            'total_amount'    => $summary['total_revenue'],
            'total_travelers' => $summary['total_travelers'],
            'formatted_total' => Money::format($summary['total_revenue']),
        ]);
    }
}
