<?php

declare(strict_types=1);

namespace Tourivo\Support;

use DateTimeImmutable;
use DateTimeZone;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SiteTime
 *
 * Trip dates (check_in / check_out) are stored as the site's local calendar date; booking timestamps
 * (created_at, updated_at) are stored in UTC. These helpers keep the two apart so nothing shifts by the
 * site's UTC offset.
 *
 * @package Tourivo\Support
 */
final class SiteTime
{
    /**
     * Start of a trip as a moment in the site's timezone.
     *
     * @param string $checkIn Y-m-d or Y-m-d H:i:s, local site time.
     * @return DateTimeImmutable
     */
    public static function checkIn(string $checkIn): DateTimeImmutable
    {
        return new DateTimeImmutable($checkIn, wp_timezone());
    }

    /**
     * Last moment a traveler may still cancel: N hours before the trip starts (site time).
     *
     * @param string $checkIn Y-m-d or Y-m-d H:i:s, local site time.
     * @param int    $hours
     * @return DateTimeImmutable
     */
    public static function cancelCutoff(string $checkIn, int $hours): DateTimeImmutable
    {
        return self::checkIn($checkIn)->modify(sprintf('-%d hours', max(0, $hours)));
    }

    /**
     * The calendar date of a stored trip date, without any timezone conversion.
     *
     * @param string $value Y-m-d or Y-m-d H:i:s.
     * @return string Y-m-d
     */
    public static function tripDate(string $value): string
    {
        return substr(trim($value), 0, 10);
    }

    /**
     * Today's date in the site's timezone.
     *
     * @return string Y-m-d
     */
    public static function today(): string
    {
        return current_datetime()->format('Y-m-d');
    }

    /**
     * Format a trip date for display (translated month names, no timezone shift).
     *
     * @param string $value  Y-m-d or Y-m-d H:i:s.
     * @param string $format
     * @return string
     */
    public static function formatTripDate(string $value, string $format = 'M d, Y'): string
    {
        $date = self::tripDate($value);
        $ts   = strtotime($date . ' 00:00:00 UTC');
        return $ts === false ? $date : wp_date($format, $ts, new DateTimeZone('UTC'));
    }

    /**
     * Format a UTC timestamp column (created_at, ...) in the site's timezone.
     *
     * @param string $gmt    Y-m-d H:i:s, UTC.
     * @param string $format
     * @return string
     */
    public static function formatGmt(string $gmt, string $format = 'M d, Y H:i'): string
    {
        $ts = strtotime($gmt . ' UTC');
        return $ts === false ? $gmt : wp_date($format, $ts);
    }
}
