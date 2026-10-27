<?php

declare(strict_types=1);

namespace Tourivo\Tests\Integration;

use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\InventoryService;
use Tourivo\Services\PricingService;
use Tourivo\Tests\TestCase;

/**
 * Class PricingAdjustmentsTest
 *
 * Price changes from tourivo/booking/price filters are itemised on the quote.
 */
class PricingAdjustmentsTest extends TestCase
{
    /**
     * @param callable $filter
     * @return array<string, mixed>
     */
    private function quoteWith(callable $filter): array
    {
        update_option('tourivo_settings', []);
        $tourId = $this->createTour(100.0, 10);
        add_filter('tourivo/booking/price', $filter, 10, 2);
        try {
            return (new PricingService(new InventoryService(new InventoryRepository())))->quote([
                'item_id'   => $tourId,
                'item_type' => 'tour',
                'check_in'  => gmdate('Y-m-d', strtotime('+4 days')),
                'adults'    => 2,
            ]);
        } finally {
            remove_filter('tourivo/booking/price', $filter);
        }
    }

    public function test_adjustments_become_lines_and_unlisted_changes_are_shown(): void
    {
        $quote = $this->quoteWith(static function (array $data): array {
            $data['adjustments'][] = ['title' => 'Weekend rate', 'total' => 40.0];
            $data['adjustments'][] = ['title' => 'Loyalty discount', 'total' => -30.0];
            $data['total_price'] = $data['total_price'] + 40.0 - 30.0 - 5.0; // 5 changed without a line

            return $data;
        });

        $lines = array_column($quote['lines'], 'total', 'title');
        $this->assertEquals(40.0, (float) $lines['Weekend rate']);
        $this->assertEquals(-30.0, (float) $lines['Loyalty discount']);
        $this->assertEquals(-5.0, (float) $lines['Other adjustments']);
        $this->assertEquals(205.0, (float) $quote['subtotal']);
        $this->assertEquals(205.0, round(array_sum(array_column($quote['lines'], 'total')), 2));
        $this->assertEquals(35.0, (float) $quote['discount']);
        $this->assertEquals(35.0, (float) $quote['line_discount']);
    }

    public function test_filters_without_adjustments_keep_the_old_discount(): void
    {
        $quote = $this->quoteWith(static function (array $data): array {
            $data['total_price'] = $data['total_price'] - 20.0;

            return $data;
        });

        $this->assertCount(1, $quote['lines'], 'No extra lines when the filter does not itemise.');
        $this->assertEquals(180.0, (float) $quote['subtotal']);
        $this->assertEquals(20.0, (float) $quote['discount']);
        $this->assertEquals(0.0, (float) $quote['line_discount']);
    }
}
