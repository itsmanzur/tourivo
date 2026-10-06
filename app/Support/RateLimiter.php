<?php

declare(strict_types=1);

namespace Tourivo\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RateLimiter
 *
 * Fixed-window request counter shared by every public Tourivo endpoint.
 *
 * With a persistent object cache the counter is incremented atomically (`wp_cache_incr`), so a burst of
 * parallel requests cannot slip past the limit. Without one it falls back to a transient counter, which is
 * best-effort only (the stock WordPress transient API has no atomic increment).
 *
 * @package Tourivo\Support
 */
class RateLimiter
{
    private const CACHE_GROUP = 'tourivo_rate_limit';

    /**
     * Register one attempt against $key and report whether it is still within the limit.
     *
     * @param string $key    Unique bucket key (e.g. 'trv_rl_booking_' . md5($ip)).
     * @param int    $limit  Maximum attempts allowed per window.
     * @param int    $window Window length in seconds.
     * @return bool True when the attempt is allowed, false when the limit is exhausted.
     */
    public static function hit(string $key, int $limit, int $window): bool
    {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            wp_cache_add($key, 0, self::CACHE_GROUP, $window);
            $count = wp_cache_incr($key, 1, self::CACHE_GROUP);

            return $count !== false && (int) $count <= $limit;
        }

        $attempts = (int) get_transient($key);
        if ($attempts >= $limit) {
            return false;
        }

        set_transient($key, $attempts + 1, $window);

        return true;
    }

    /**
     * Clear a bucket (used after a successful, trusted action or in tests).
     */
    public static function reset(string $key): void
    {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            wp_cache_delete($key, self::CACHE_GROUP);
            return;
        }

        delete_transient($key);
    }
}
