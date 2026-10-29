<?php

declare(strict_types=1);

namespace Tourivo\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LegacyHooks
 *
 * Keeps code written against the pre-1.5.0 hook names working. Tourivo 1.5.0 renamed tourivo/booking_* to
 * tourivo/booking/* (and tourivo/inquiry_created to tourivo/inquiry/created); Tourivo itself only fires the new
 * names. For every old name that has a listener, one bridge is attached to the new name which runs the old
 * listeners through do_action_deprecated() / apply_filters_deprecated(), so they still run, exactly once per
 * event, and WP_DEBUG sites get a _deprecated_hook() notice naming the replacement. Old names nobody listens
 * to cost nothing.
 *
 * Old listeners run at priority 10 of the new hook, whatever priority they were added with.
 *
 * @package Tourivo\Support
 */
final class LegacyHooks
{
    /**
     * Version that renamed the hooks (shown in the deprecation notice).
     */
    public const SINCE = '1.5.0';

    /**
     * Old action name => new action name.
     *
     * @var array<string, string>
     */
    public const ACTIONS = [
        'tourivo/booking_created'          => 'tourivo/booking/created',
        'tourivo/booking_status_changed'   => 'tourivo/booking/status_changed',
        'tourivo/booking_email_verified'   => 'tourivo/booking/email_verified',
        'tourivo/booking_cancel_requested' => 'tourivo/booking/cancel_requested',
        'tourivo/booking_coupon_rollback'  => 'tourivo/booking/coupon_rollback',
        'tourivo/booking_error'            => 'tourivo/booking/error',
        'tourivo/inquiry_created'          => 'tourivo/inquiry/created',
    ];

    /**
     * Old filter name => new filter name.
     *
     * @var array<string, string>
     */
    public const FILTERS = [
        'tourivo/booking_price'                     => 'tourivo/booking/price',
        'tourivo/booking_adjustments'               => 'tourivo/booking/adjustments',
        'tourivo/booking_title'                     => 'tourivo/booking/title',
        'tourivo/booking_panel_payment_options'     => 'tourivo/booking/panel_payment_options',
        'tourivo/booking_coupon_commit'             => 'tourivo/booking/coupon_commit',
        'tourivo/booking_email_rate_limit'          => 'tourivo/booking/email_rate_limit',
        'tourivo/booking_status_transition_allowed' => 'tourivo/booking/status_transition_allowed',
    ];

    /**
     * Old names already bridged in this request.
     *
     * @var array<string, true>
     */
    private static array $bridged = [];

    /**
     * Bridge the old names that have listeners now (other plugins are loaded by plugins_loaded), and again once
     * the theme and init callbacks had their turn. Booking and inquiry events only fire after wp_loaded.
     *
     * @return void
     */
    public static function register(): void
    {
        self::bridge();
        add_action('wp_loaded', [self::class, 'bridge'], PHP_INT_MAX);
    }

    /**
     * Attach one bridge per old name that has a listener and is not bridged yet.
     *
     * @return void
     */
    public static function bridge(): void
    {
        foreach (self::ACTIONS as $old => $new) {
            if (isset(self::$bridged[$old]) || !has_action($old)) {
                continue;
            }
            self::$bridged[$old] = true;

            add_action($new, static function (...$args) use ($old, $new): void {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Old Tourivo hook names from self::ACTIONS.
                do_action_deprecated($old, $args, self::SINCE, $new);
            }, 10, 99);
        }

        foreach (self::FILTERS as $old => $new) {
            if (isset(self::$bridged[$old]) || !has_filter($old)) {
                continue;
            }
            self::$bridged[$old] = true;

            add_filter($new, static function ($value, ...$args) use ($old, $new) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Old Tourivo hook names from self::FILTERS.
                return apply_filters_deprecated($old, array_merge([$value], $args), self::SINCE, $new);
            }, 10, 99);
        }
    }

    /**
     * Forget which names were bridged (tests only).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$bridged = [];
    }
}
