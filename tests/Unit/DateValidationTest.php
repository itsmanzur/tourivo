<?php
/**
 * Date Validation Unit Tests
 */

declare(strict_types=1);

namespace Tourivo\Tests\Unit;

use Tourivo\Repositories\InventoryRepository;
use Tourivo\Services\InventoryService;
use Tourivo\Tests\TestCase;

class DateValidationTest extends TestCase
{
    protected InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $repo = new InventoryRepository();
        $this->service = new InventoryService($repo);
    }

    /**
     * Test valid future date list generation.
     */
    public function test_generate_date_list_valid_future_range(): void
    {
        $start = gmdate('Y-m-d', strtotime('+10 days'));
        $end   = gmdate('Y-m-d', strtotime('+13 days'));

        $dates = $this->service->generateDateList($start, $end);
        $this->assertCount(3, $dates);
        $this->assertEquals($start, $dates[0]);
    }

    /**
     * Test past date is rejected when enforceFuture is true.
     */
    public function test_generate_date_list_rejects_past_dates(): void
    {
        $pastDate = '2020-01-01';
        $dates = $this->service->generateDateList($pastDate, null, true);
        $this->assertEmpty($dates);
    }

    /**
     * Test invalid Y-m-d format is rejected.
     */
    public function test_generate_date_list_rejects_invalid_format(): void
    {
        $invalid = '12/31/2026';
        $dates = $this->service->generateDateList($invalid);
        $this->assertEmpty($dates);
    }

    /**
     * Test date ranges larger than 365 days are rejected.
     */
    public function test_generate_date_list_rejects_over_365_days(): void
    {
        $start = gmdate('Y-m-d', strtotime('+1 day'));
        $end   = gmdate('Y-m-d', strtotime('+400 days'));

        $dates = $this->service->generateDateList($start, $end);
        $this->assertEmpty($dates);
    }

    /**
     * Test check_out earlier than check_in returns empty.
     */
    public function test_generate_date_list_rejects_reversed_dates(): void
    {
        $start = gmdate('Y-m-d', strtotime('+10 days'));
        $end   = gmdate('Y-m-d', strtotime('+5 days'));

        $dates = $this->service->generateDateList($start, $end);
        $this->assertEmpty($dates);
    }
}
