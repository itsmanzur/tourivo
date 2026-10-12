<?php

declare(strict_types=1);

namespace Tourivo\Services;

use Tourivo\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ReportingService
 *
 * Provides business analytics, sales metrics, tax filing calculations, occupancy stats,
 * and accounting CSV exports for Tourivo administrators.
 *
 * @package Tourivo\Services
 */
class ReportingService
{
    /**
     * Resolve date range from a preset key or custom date parameters.
     *
     * @param string      $period    One of: today, yesterday, last_7_days, this_month, last_month, this_year, custom.
     * @param string|null $startDate Custom start date (Y-m-d).
     * @param string|null $endDate   Custom end date (Y-m-d).
     * @return array{start: string, end: string, label: string, period: string}
     */
    public function resolveDateRange(string $period = 'this_month', ?string $startDate = null, ?string $endDate = null): array
    {
        $today = date('Y-m-d');

        switch ($period) {
            case 'today':
                return [
                    'start'  => $today . ' 00:00:00',
                    'end'    => $today . ' 23:59:59',
                    'label'  => __('Today', 'tourivo'),
                    'period' => 'today',
                ];

            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return [
                    'start'  => $yesterday . ' 00:00:00',
                    'end'    => $yesterday . ' 23:59:59',
                    'label'  => __('Yesterday', 'tourivo'),
                    'period' => 'yesterday',
                ];

            case 'last_7_days':
                $sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));
                return [
                    'start'  => $sevenDaysAgo . ' 00:00:00',
                    'end'    => $today . ' 23:59:59',
                    'label'  => __('Last 7 Days', 'tourivo'),
                    'period' => 'last_7_days',
                ];

            case 'last_month':
                $startLastMonth = date('Y-m-01', strtotime('first day of last month'));
                $endLastMonth   = date('Y-m-t', strtotime('last day of last month'));
                return [
                    'start'  => $startLastMonth . ' 00:00:00',
                    'end'    => $endLastMonth . ' 23:59:59',
                    'label'  => __('Last Month', 'tourivo'),
                    'period' => 'last_month',
                ];

            case 'this_year':
                $startYear = date('Y-01-01');
                return [
                    'start'  => $startYear . ' 00:00:00',
                    'end'    => $today . ' 23:59:59',
                    'label'  => __('This Year', 'tourivo'),
                    'period' => 'this_year',
                ];

            case 'custom':
                $cleanStart = !empty($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ? $startDate : date('Y-m-01');
                $cleanEnd   = !empty($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) ? $endDate : $today;
                if ($cleanStart > $cleanEnd) {
                    $tmp        = $cleanStart;
                    $cleanStart = $cleanEnd;
                    $cleanEnd   = $tmp;
                }
                return [
                    'start'  => $cleanStart . ' 00:00:00',
                    'end'    => $cleanEnd . ' 23:59:59',
                    'label'  => sprintf('%s - %s', $cleanStart, $cleanEnd),
                    'period' => 'custom',
                ];

            case 'this_month':
            default:
                $startMonth = date('Y-m-01');
                return [
                    'start'  => $startMonth . ' 00:00:00',
                    'end'    => $today . ' 23:59:59',
                    'label'  => __('This Month', 'tourivo'),
                    'period' => 'this_month',
                ];
        }
    }

    /**
     * Compute key performance indicator summary metrics for a date range.
     *
     * @param string $startDate ISO datetime string.
     * @param string $endDate   ISO datetime string.
     * @return array{
     *     total_bookings: int,
     *     confirmed_bookings: int,
     *     pending_bookings: int,
     *     cancelled_bookings: int,
     *     gross_revenue: float,
     *     net_revenue: float,
     *     total_tax: float,
     *     total_discount: float,
     *     due_amount: float,
     *     average_order_value: float,
     *     total_travelers: int
     * }
     */
    public function getKpiMetrics(string $startDate, string $endDate): array
    {
        global $wpdb;

        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bookings = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE created_at >= %s AND created_at <= %s ORDER BY id DESC",
            $startDate,
            $endDate
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $totalBookings     = count($bookings);
        $confirmedBookings = 0;
        $pendingBookings   = 0;
        $cancelledBookings = 0;
        $grossRevenue      = 0.0;
        $totalTax          = 0.0;
        $totalDiscount     = 0.0;
        $dueAmount         = 0.0;
        $activeBookingsIds = [];

        foreach ($bookings as $b) {
            $status = (string) ($b['booking_status'] ?? 'pending');
            $id     = (int) ($b['id'] ?? 0);

            if ($status === 'confirmed') {
                $confirmedBookings++;
            } elseif ($status === 'cancelled') {
                $cancelledBookings++;
            } elseif ($status === 'pending') {
                $pendingBookings++;
            }

            if ($status !== 'cancelled') {
                $activeBookingsIds[] = $id;
                $grossRevenue       += (float) ($b['total_amount'] ?? 0.0);
                $totalTax           += (float) ($b['tax_amount'] ?? 0.0);
                $totalDiscount      += (float) ($b['discount_amount'] ?? 0.0);
                $dueAmount          += (float) ($b['due_amount'] ?? 0.0);
            }
        }

        $activeCount = count($activeBookingsIds);
        $netRevenue  = max(0.0, $grossRevenue - $totalTax - $totalDiscount);
        $aov         = $activeCount > 0 ? round($grossRevenue / $activeCount, 2) : 0.0;

        // Total travelers count from active booking line items
        $totalTravelers = 0;
        if (!empty($activeBookingsIds)) {
            $placeholders = implode(',', array_fill(0, count($activeBookingsIds), '%d'));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $items = (array) $wpdb->get_results($wpdb->prepare(
                "SELECT adults_count, children_count, infants_count, quantity FROM {$itemsTable} WHERE booking_id IN ({$placeholders})",
                ...$activeBookingsIds
            ), ARRAY_A);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            foreach ($items as $it) {
                $adults   = (int) ($it['adults_count'] ?? 1);
                $children = (int) ($it['children_count'] ?? 0);
                $infants  = (int) ($it['infants_count'] ?? 0);
                $qty      = max(1, (int) ($it['quantity'] ?? 1));
                $guests   = ($adults + $children + $infants) ?: $qty;
                $totalTravelers += $guests;
            }
        }

        return [
            'total_bookings'      => $totalBookings,
            'confirmed_bookings'  => $confirmedBookings,
            'pending_bookings'    => $pendingBookings,
            'cancelled_bookings'  => $cancelledBookings,
            'gross_revenue'       => round($grossRevenue, 2),
            'net_revenue'         => round($netRevenue, 2),
            'total_tax'           => round($totalTax, 2),
            'total_discount'      => round($totalDiscount, 2),
            'due_amount'          => round($dueAmount, 2),
            'average_order_value' => $aov,
            'total_travelers'     => $totalTravelers,
        ];
    }

    /**
     * Compute average occupancy rate across scheduled tours and hotel rooms for a date range.
     *
     * @param string $startDate ISO datetime.
     * @param string $endDate   ISO datetime.
     * @return array{rate: float, total_booked: int, total_capacity: int}
     */
    public function getOccupancyRate(string $startDate, string $endDate): array
    {
        global $wpdb;

        $inventoriesTable = $wpdb->prefix . 'tourivo_inventories';
        $startDay = substr($startDate, 0, 10);
        $endDay   = substr($endDate, 0, 10);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT total_capacity, booked_count FROM {$inventoriesTable} WHERE event_date >= %s AND event_date <= %s",
            $startDay,
            $endDay
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $totalCapacity = 0;
        $totalBooked   = 0;

        foreach ($rows as $row) {
            $totalCapacity += (int) ($row['total_capacity'] ?? 0);
            $totalBooked   += (int) ($row['booked_count'] ?? 0);
        }

        $rate = $totalCapacity > 0 ? round(($totalBooked / $totalCapacity) * 100, 1) : 0.0;

        return [
            'rate'           => min(100.0, max(0.0, $rate)),
            'total_booked'   => $totalBooked,
            'total_capacity' => $totalCapacity,
        ];
    }

    /**
     * Retrieve top-performing packages and room types ranked by total revenue and bookings.
     *
     * @param string $startDate ISO datetime.
     * @param string $endDate   ISO datetime.
     * @param int    $limit     Maximum items to return per category.
     * @return array{
     *     tours: array<int, array{item_id: int, item_title: string, item_type: string, bookings_count: int, units_sold: int, total_sales: float, traveler_count: int}>,
     *     rooms: array<int, array{item_id: int, item_title: string, item_type: string, bookings_count: int, units_sold: int, total_sales: float, traveler_count: int}>
     * }
     */
    public function getTopPerformingItems(string $startDate, string $endDate, int $limit = 5): array
    {
        global $wpdb;

        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bookings = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$bookingsTable} WHERE booking_status != 'cancelled' AND created_at >= %s AND created_at <= %s",
            $startDate,
            $endDate
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (empty($bookings)) {
            return ['tours' => [], 'rooms' => []];
        }

        $bookingIds   = array_map(static fn ($b): int => (int) ($b['id'] ?? 0), $bookings);
        $placeholders = implode(',', array_fill(0, count($bookingIds), '%d'));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $items = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$itemsTable} WHERE booking_id IN ({$placeholders})",
            ...$bookingIds
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        /** @var array<int, array{item_id: int, item_title: string, item_type: string, bookings_count: int, units_sold: int, total_sales: float, traveler_count: int}> $tourMap */
        $tourMap = [];
        /** @var array<int, array{item_id: int, item_title: string, item_type: string, bookings_count: int, units_sold: int, total_sales: float, traveler_count: int}> $roomMap */
        $roomMap = [];

        foreach ($items as $it) {
            $type   = (string) ($it['item_type'] ?? 'tour');
            $itemId = (int) ($it['item_id'] ?? 0);
            $title  = (string) ($it['item_title'] ?? '');
            $qty    = (int) ($it['quantity'] ?? 1);
            $price  = (float) ($it['total_price'] ?? 0.0);
            $guests = (int) ($it['adults_count'] ?? 1) + (int) ($it['children_count'] ?? 0) + (int) ($it['infants_count'] ?? 0);

            $isRoom = ($type === 'room' || $type === 'hotel_room' || $type === 'hotel');

            if ($isRoom) {
                if (!isset($roomMap[$itemId])) {
                    $roomMap[$itemId] = [
                        'item_id'        => $itemId,
                        'item_title'     => $title ?: sprintf(__('Item #%d', 'tourivo'), $itemId),
                        'item_type'      => $type,
                        'bookings_count' => 0,
                        'units_sold'     => 0,
                        'total_sales'    => 0.0,
                        'traveler_count' => 0,
                    ];
                }
                $roomMap[$itemId]['bookings_count']++;
                $roomMap[$itemId]['units_sold']     += $qty;
                $roomMap[$itemId]['total_sales']    += $price;
                $roomMap[$itemId]['traveler_count'] += $guests;
            } else {
                if (!isset($tourMap[$itemId])) {
                    $tourMap[$itemId] = [
                        'item_id'        => $itemId,
                        'item_title'     => $title ?: sprintf(__('Item #%d', 'tourivo'), $itemId),
                        'item_type'      => $type,
                        'bookings_count' => 0,
                        'units_sold'     => 0,
                        'total_sales'    => 0.0,
                        'traveler_count' => 0,
                    ];
                }
                $tourMap[$itemId]['bookings_count']++;
                $tourMap[$itemId]['units_sold']     += $qty;
                $tourMap[$itemId]['total_sales']    += $price;
                $tourMap[$itemId]['traveler_count'] += $guests;
            }
        }

        $sortFn = static fn ($a, $b): int => ($b['total_sales'] <=> $a['total_sales']);

        usort($tourMap, $sortFn);
        usort($roomMap, $sortFn);

        return [
            'tours' => array_slice($tourMap, 0, $limit),
            'rooms' => array_slice($roomMap, 0, $limit),
        ];
    }

    /**
     * Compute timeline daily sales and booking counts for graphing.
     *
     * @param string $startDate ISO datetime.
     * @param string $endDate   ISO datetime.
     * @return array<string, array{date: string, label: string, booking_count: int, revenue: float}>
     */
    public function getTimelineData(string $startDate, string $endDate): array
    {
        global $wpdb;

        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bookings = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT created_at, total_amount, booking_status FROM {$bookingsTable} WHERE created_at >= %s AND created_at <= %s ORDER BY created_at ASC",
            $startDate,
            $endDate
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $timeline = [];

        foreach ($bookings as $b) {
            $created = (string) ($b['created_at'] ?? '');
            $day     = substr($created, 0, 10);
            if (empty($day)) {
                continue;
            }

            if (!isset($timeline[$day])) {
                $timeline[$day] = [
                    'date'          => $day,
                    'label'         => date('M j', strtotime($day)),
                    'booking_count' => 0,
                    'revenue'       => 0.0,
                ];
            }

            $timeline[$day]['booking_count']++;
            if (($b['booking_status'] ?? '') !== 'cancelled') {
                $timeline[$day]['revenue'] += (float) ($b['total_amount'] ?? 0.0);
            }
        }

        return $timeline;
    }

    /**
     * Export complete Accounting & Tax Filing CSV report to an output stream.
     *
     * @param string        $startDate ISO datetime.
     * @param string        $endDate   ISO datetime.
     * @param resource|null $stream    Target stream (defaults to php://output).
     * @return void
     */
    public function exportAccountingCsv(string $startDate, string $endDate, $stream = null): void
    {
        if ($stream === null) {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
        }

        global $wpdb;
        $bookingsTable = $wpdb->prefix . 'tourivo_bookings';
        $itemsTable    = $wpdb->prefix . 'tourivo_booking_items';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bookings = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$bookingsTable} WHERE created_at >= %s AND created_at <= %s ORDER BY id DESC",
            $startDate,
            $endDate
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $bookingIds = array_map(static fn ($b): int => (int) ($b['id'] ?? 0), $bookings);
        $itemsByBooking = [];

        if (!empty($bookingIds)) {
            $placeholders = implode(',', array_fill(0, count($bookingIds), '%d'));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $itemRows = (array) $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$itemsTable} WHERE booking_id IN ({$placeholders}) ORDER BY id ASC",
                ...$bookingIds
            ), ARRAY_A);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            foreach ($itemRows as $item) {
                $bId = (int) ($item['booking_id'] ?? 0);
                $itemsByBooking[$bId][] = $item;
            }
        }

        // 1. Output UTF-8 BOM for Microsoft Excel Unicode & Bengali rendering
        fwrite($stream, "\xEF\xBB\xBF");

        // 2. Accounting Report Headers
        $headers = [
            __('Booking Code', 'tourivo'),
            __('Booking Date', 'tourivo'),
            __('Check-In Date', 'tourivo'),
            __('Customer Name', 'tourivo'),
            __('Customer Email', 'tourivo'),
            __('Customer Phone', 'tourivo'),
            __('Item Title', 'tourivo'),
            __('Item Type', 'tourivo'),
            __('Quantity', 'tourivo'),
            __('Subtotal (Excl. Tax)', 'tourivo'),
            __('Tax Amount', 'tourivo'),
            __('Discount Amount', 'tourivo'),
            __('Gross Total', 'tourivo'),
            __('Payment Status', 'tourivo'),
            __('Payment Method', 'tourivo'),
            __('Transaction ID', 'tourivo'),
            __('Booking Status', 'tourivo'),
        ];
        fputcsv($stream, $headers);

        // 3. Write line items
        foreach ($bookings as $b) {
            $bId       = (int) ($b['id'] ?? 0);
            $items     = $itemsByBooking[$bId] ?? [];
            $totalAmt  = (float) ($b['total_amount'] ?? 0.0);
            $taxAmt    = (float) ($b['tax_amount'] ?? 0.0);
            $discAmt   = (float) ($b['discount_amount'] ?? 0.0);
            $subtotal  = max(0.0, $totalAmt - $taxAmt + $discAmt);

            if (empty($items)) {
                $row = [
                    $this->sanitizeCsvCell((string) ($b['booking_code'] ?? '')),
                    $this->sanitizeCsvCell((string) ($b['created_at'] ?? '')),
                    $this->sanitizeCsvCell(''),
                    $this->sanitizeCsvCell((string) ($b['customer_name'] ?? '')),
                    $this->sanitizeCsvCell((string) ($b['customer_email'] ?? '')),
                    $this->sanitizeCsvCell((string) ($b['customer_phone'] ?? '')),
                    $this->sanitizeCsvCell(__('Direct Booking', 'tourivo')),
                    $this->sanitizeCsvCell('booking'),
                    '1',
                    number_format($subtotal, 2, '.', ''),
                    number_format($taxAmt, 2, '.', ''),
                    number_format($discAmt, 2, '.', ''),
                    number_format($totalAmt, 2, '.', ''),
                    $this->sanitizeCsvCell((string) ($b['payment_status'] ?? 'pending')),
                    $this->sanitizeCsvCell((string) ($b['payment_method'] ?? 'offline')),
                    $this->sanitizeCsvCell((string) ($b['transaction_id'] ?? '')),
                    $this->sanitizeCsvCell((string) ($b['booking_status'] ?? 'pending')),
                ];
                fputcsv($stream, $row);
                continue;
            }

            foreach ($items as $idx => $it) {
                $isFirst = ($idx === 0);
                $row = [
                    $this->sanitizeCsvCell((string) ($b['booking_code'] ?? '')),
                    $this->sanitizeCsvCell((string) ($b['created_at'] ?? '')),
                    $this->sanitizeCsvCell((string) ($it['check_in'] ?? '')),
                    $this->sanitizeCsvCell((string) ($b['customer_name'] ?? '')),
                    $this->sanitizeCsvCell((string) ($b['customer_email'] ?? '')),
                    $this->sanitizeCsvCell((string) ($b['customer_phone'] ?? '')),
                    $this->sanitizeCsvCell((string) ($it['item_title'] ?? '')),
                    $this->sanitizeCsvCell((string) ($it['item_type'] ?? 'tour')),
                    (string) ($it['quantity'] ?? 1),
                    $isFirst ? number_format($subtotal, 2, '.', '') : '0.00',
                    $isFirst ? number_format($taxAmt, 2, '.', '') : '0.00',
                    $isFirst ? number_format($discAmt, 2, '.', '') : '0.00',
                    $isFirst ? number_format($totalAmt, 2, '.', '') : number_format((float) ($it['total_price'] ?? 0.0), 2, '.', ''),
                    $this->sanitizeCsvCell((string) ($b['payment_status'] ?? 'pending')),
                    $this->sanitizeCsvCell((string) ($b['payment_method'] ?? 'offline')),
                    $this->sanitizeCsvCell((string) ($b['transaction_id'] ?? '')),
                    $this->sanitizeCsvCell((string) ($b['booking_status'] ?? 'pending')),
                ];
                fputcsv($stream, $row);
            }
        }
    }

    /**
     * Sanitize cell against CSV Formula Injection.
     *
     * @param string $value Cell string.
     * @return string
     */
    public function sanitizeCsvCell(string $value): string
    {
        $dangerousChars = ['=', '+', '-', '@', "\t", "\r"];
        if ($value !== '' && in_array($value[0], $dangerousChars, true)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Handle Admin HTTP POST request for Accounting CSV export.
     *
     * @return void
     */
    public function handleExportRequest(): void
    {
        if (!current_user_can('manage_tourivo_bookings')) {
            wp_die(esc_html__('Unauthorized access. You do not have permission to export reports.', 'tourivo'), 403);
        }

        check_admin_referer('tourivo_reports_action', 'tourivo_reports_nonce');

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
        $req       = !empty($_POST) ? $_POST : $_GET;
        $period    = isset($req['period']) ? sanitize_text_field(wp_unslash($req['period'])) : 'this_month';
        $startDate = isset($req['start_date']) ? sanitize_text_field(wp_unslash($req['start_date'])) : null;
        $endDate   = isset($req['end_date']) ? sanitize_text_field(wp_unslash($req['end_date'])) : null;
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

        $range    = $this->resolveDateRange($period, $startDate, $endDate);
        $filename = 'tourivo-accounting-report-' . date('Ymd-His') . '.csv';

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $this->exportAccountingCsv($range['start'], $range['end']);

        if (!defined('TOURIVO_TEST_MODE')) {
            exit;
        }
    }
}
