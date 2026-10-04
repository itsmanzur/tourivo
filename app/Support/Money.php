<?php

declare(strict_types=1);

namespace Tourivo\Support;

use Tourivo\Config\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Money
 *
 * Centralized currency and price formatting helper according to global Tourivo settings.
 *
 * @package Tourivo\Support
 */
class Money
{
    /**
     * Format a price float with configured currency symbol, decimal places, and position.
     *
     * @param float|int|string $amount
     * @param string|null      $customCurrency
     * @return string
     */
    public static function format(float|int|string $amount, ?string $customCurrency = null): string
    {
        $num       = (float) $amount;
        $defSymbol = $customCurrency ?: (string) Config::get('currency_symbol', '$');
        $symbol    = (string) apply_filters('tourivo/currency_symbol', $defSymbol);
        $position  = (string) apply_filters('tourivo/currency_position', (string) Config::get('currency_position', 'left'));
        $decimals  = (int) apply_filters('tourivo/number_of_decimals', (int) Config::get('number_of_decimals', 2));
        $decPoint  = (string) apply_filters('tourivo/decimal_separator', (string) Config::get('decimal_separator', '.'));
        $thousands = (string) apply_filters('tourivo/thousand_separator', (string) Config::get('thousand_separator', ','));

        $formattedNum = number_format($num, $decimals, $decPoint, $thousands);

        $useBangla = (bool) apply_filters('tourivo/use_bangla_digits', (bool) Config::get('use_bangla_digits', false));
        if ($useBangla && function_exists('tourivo_bn_number')) {
            $formattedNum = tourivo_bn_number($formattedNum);
        }

        return match ($position) {
            'right'       => $formattedNum . $symbol,
            'left_space'  => $symbol . ' ' . $formattedNum,
            'right_space' => $formattedNum . ' ' . $symbol,
            default       => $symbol . $formattedNum,
        };
    }
}
