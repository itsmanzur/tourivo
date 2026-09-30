<?php

declare(strict_types=1);

namespace Tourivo\Shortcodes;

use Tourivo\Config\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CurrencySwitcherShortcode
 *
 * Shortcode: [tourivo_currency_switcher style="dropdown"]
 *
 * @package Tourivo\Shortcodes
 */
class CurrencySwitcherShortcode
{
    public static function register(): void
    {
        add_shortcode('tourivo_currency_switcher', [self::class, 'render']);
    }

    /**
     * Get active configured currency rates list.
     *
     * @return array<string, array{name: string, symbol: string, rate: float, position: string}>
     */
    public static function getCurrencies(): array
    {
        $defaultCurrencies = [
            'USD' => ['name' => 'USD - US Dollar', 'symbol' => '$', 'rate' => 1.0, 'position' => 'left'],
            'EUR' => ['name' => 'EUR - Euro', 'symbol' => '€', 'rate' => 0.92, 'position' => 'left'],
            'GBP' => ['name' => 'GBP - British Pound', 'symbol' => '£', 'rate' => 0.79, 'position' => 'left'],
            'BDT' => ['name' => 'BDT - Bangladeshi Taka', 'symbol' => '৳', 'rate' => 120.0, 'position' => 'right'],
            'INR' => ['name' => 'INR - Indian Rupee', 'symbol' => '₹', 'rate' => 83.5, 'position' => 'left'],
            'AUD' => ['name' => 'AUD - Australian Dollar', 'symbol' => 'A$', 'rate' => 1.52, 'position' => 'left'],
            'CAD' => ['name' => 'CAD - Canadian Dollar', 'symbol' => 'CA$', 'rate' => 1.36, 'position' => 'left'],
            'AED' => ['name' => 'AED - UAE Dirham', 'symbol' => 'AED ', 'rate' => 3.67, 'position' => 'left'],
        ];

        return (array) apply_filters('tourivo/currency_rates', $defaultCurrencies);
    }

    /**
     * Render the currency switcher dropdown widget.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public static function render(array|string $atts = []): string
    {
        $attributes = shortcode_atts([
            'style' => 'dropdown', // 'dropdown' or 'pills'
        ], (array) $atts, 'tourivo_currency_switcher');

        $currencies = self::getCurrencies();
        $baseCurrency = Config::get('currency', 'USD');

        ob_start();
        ?>
        <div class="tourivo-currency-switcher-wrap style-<?php echo esc_attr($attributes['style']); ?>">
            <label for="tourivo-currency-select" class="currency-label">
                <span class="dashicons dashicons-money-alt"></span>
            </label>
            <select id="tourivo-currency-select" class="tourivo-currency-select" aria-label="<?php esc_attr_e('Select Currency', 'tourivo'); ?>">
                <?php foreach ($currencies as $code => $cur) : ?>
                    <option value="<?php echo esc_attr($code); ?>" 
                            data-symbol="<?php echo esc_attr($cur['symbol']); ?>" 
                            data-rate="<?php echo esc_attr((string)$cur['rate']); ?>" 
                            data-pos="<?php echo esc_attr($cur['position']); ?>" 
                            <?php selected($code, $baseCurrency); ?>>
                        <?php echo esc_html($code . ' (' . $cur['symbol'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }
}
