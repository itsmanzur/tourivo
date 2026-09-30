<?php

declare(strict_types=1);

namespace Tourivo\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ClientIp
 *
 * Resolves client IP address with secure proxy/Cloudflare verification and anti-spoofing checks.
 *
 * @package Tourivo\Support
 */
class ClientIp
{
    /**
     * Get validated client IP address.
     *
     * @return string
     */
    public static function get(): string
    {
        $remoteAddr = sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')));
        $settings = get_option('tourivo_settings', []);

        $rawMode = (string) ($settings['proxy_mode'] ?? '');
        $proxyMode = in_array($rawMode, ['cloudflare', 'reverse_proxy'], true) ? $rawMode : 'disabled';

        // Direct connection mode: never trust incoming proxy headers
        if ($proxyMode === 'disabled') {
            return (string) apply_filters('tourivo/client_ip', $remoteAddr);
        }

        $ip = '';

        // 1. Cloudflare Mode: ONLY trust CF-Connecting-IP if REMOTE_ADDR is an actual Cloudflare IP or trusted proxy
        if ($proxyMode === 'cloudflare') {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && (self::isCloudflareIp($remoteAddr) || self::isTrustedProxy($remoteAddr))) {
                $candidate = trim(sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_CF_CONNECTING_IP'])));
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    $ip = $candidate;
                }
            }

            if (empty($ip)) {
                $ip = $remoteAddr;
            }

            return (string) apply_filters('tourivo/client_ip', $ip);
        }

        // 2. Reverse Proxy Mode: ONLY trust headers if REMOTE_ADDR is an authenticated/trusted proxy
        if ($proxyMode === 'reverse_proxy') {
            if (!self::isTrustedProxy($remoteAddr)) {
                return (string) apply_filters('tourivo/client_ip', $remoteAddr);
            }

            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $rawHeader = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_X_FORWARDED_FOR']));
                $parts = array_map('trim', explode(',', $rawHeader));
                $parts = array_reverse($parts);

                foreach ($parts as $candidate) {
                    if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
                        continue;
                    }
                    if (!self::isTrustedProxy($candidate)) {
                        $ip = $candidate;
                        break;
                    }
                }

                if (empty($ip) && !empty($parts)) {
                    $leftmost = end($parts);
                    if (filter_var($leftmost, FILTER_VALIDATE_IP)) {
                        $ip = $leftmost;
                    }
                }
            }

            if (empty($ip) && !empty($_SERVER['HTTP_X_REAL_IP'])) {
                $realIp = trim(sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_X_REAL_IP'])));
                if (filter_var($realIp, FILTER_VALIDATE_IP)) {
                    $ip = $realIp;
                }
            }
        }

        if (empty($ip)) {
            $ip = $remoteAddr;
        }

        return (string) apply_filters('tourivo/client_ip', $ip);
    }

    /**
     * Check if an IP address belongs to trusted proxy ranges.
     *
     * @param string $ip
     * @return bool
     */
    public static function isTrustedProxy(string $ip): bool
    {
        $defaultProxies = [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            'fc00::/7',
        ];

        $settings = get_option('tourivo_settings', []);
        $customProxies = [];
        if (!empty($settings['trusted_proxies']) && is_string($settings['trusted_proxies'])) {
            $customProxies = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $settings['trusted_proxies'])));
        }

        $merged = array_values(array_unique(array_merge($defaultProxies, $customProxies)));
        $trusted = (array) apply_filters('tourivo/trusted_proxies', $merged);

        foreach ($trusted as $range) {
            if (self::ipMatchesRange($ip, (string) $range)) {
                return true;
            }
        }

        return self::isCloudflareIp($ip);
    }

    /**
     * Check if an IP address is a known Cloudflare edge server.
     *
     * @param string $ip
     * @return bool
     */
    public static function isCloudflareIp(string $ip): bool
    {
        $defaultRanges = [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ];

        $cfRanges = (array) apply_filters('tourivo/cloudflare_ranges', $defaultRanges);

        foreach ($cfRanges as $range) {
            if (self::ipMatchesRange($ip, (string) $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match an IP against a single IP or CIDR range.
     *
     * @param string $ip
     * @param string $range
     * @return bool
     */
    public static function ipMatchesRange(string $ip, string $range): bool
    {
        if ($ip === $range) {
            return true;
        }

        if (!str_contains($range, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;

        // IPv4 CIDR match
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
                return false;
            }
            $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        // IPv6 CIDR match
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = @inet_pton($ip);
            $subnetBin = @inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false || $bits < 0 || $bits > 128) {
                return false;
            }
            $bytes = (int) ($bits / 8);
            $remainder = $bits % 8;
            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }
            if ($remainder > 0) {
                $mask = 0xFF << (8 - $remainder);
                $ipByte = ord($ipBin[$bytes]);
                $subnetByte = ord($subnetBin[$bytes]);
                if (($ipByte & $mask) !== ($subnetByte & $mask)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }
}
