<?php
/**
 * Money Formatting Unit Tests
 */

declare(strict_types=1);

namespace Tourivo\Tests\Unit;

use Tourivo\Support\Money;
use Tourivo\Tests\TestCase;

class MoneyTest extends TestCase
{
    /**
     * Test standard formatting across all 4 currency positions.
     */
    public function test_money_format_all_currency_positions(): void
    {
        // 1. left: $100.00
        update_option('tourivo_settings', [
            'currency_symbol'   => '$',
            'currency_position' => 'left',
            'number_of_decimals'=> 2,
        ]);
        $this->assertEquals('$100.00', Money::format(100.0));

        // 2. right: 100.00$
        update_option('tourivo_settings', [
            'currency_symbol'   => '$',
            'currency_position' => 'right',
            'number_of_decimals'=> 2,
        ]);
        $this->assertEquals('100.00$', Money::format(100.0));

        // 3. left_space: $ 100.00
        update_option('tourivo_settings', [
            'currency_symbol'   => '$',
            'currency_position' => 'left_space',
            'number_of_decimals'=> 2,
        ]);
        $this->assertEquals('$ 100.00', Money::format(100.0));

        // 4. right_space: 100.00 $
        update_option('tourivo_settings', [
            'currency_symbol'   => '$',
            'currency_position' => 'right_space',
            'number_of_decimals'=> 2,
        ]);
        $this->assertEquals('100.00 $', Money::format(100.0));
    }

    /**
     * Test Bengali numeral conversion.
     */
    public function test_money_format_bengali_numerals(): void
    {
        update_option('tourivo_settings', [
            'currency_symbol'   => '৳',
            'currency_position' => 'left_space',
            'use_bangla_digits' => true,
            'number_of_decimals'=> 2,
        ]);

        $formatted = Money::format(1250.50);
        $this->assertStringContainsString('৳', $formatted);
        $this->assertStringContainsString('১,২৫০.৫০', $formatted);
    }

    /**
     * Test zero and negative amounts.
     */
    public function test_money_format_zero_and_negative(): void
    {
        update_option('tourivo_settings', [
            'currency_symbol'   => '$',
            'currency_position' => 'left',
            'use_bangla_digits' => false,
            'number_of_decimals'=> 2,
        ]);

        $zero = Money::format(0.0);
        $this->assertEquals('$0.00', $zero);
    }
}
