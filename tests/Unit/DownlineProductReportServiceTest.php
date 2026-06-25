<?php

namespace Tests\Unit;

use App\Models\UserProduct;
use App\Services\DownlineProductReportService;
use PHPUnit\Framework\TestCase;

class DownlineProductReportServiceTest extends TestCase
{
    private DownlineProductReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DownlineProductReportService;
    }

    public function test_effective_total_claims_uses_positive_snapshot(): void
    {
        $userProduct = new UserProduct([
            'duration_days_snapshot' => 30,
            'claim_interval_hours_snapshot' => 6,
            'total_claims_snapshot' => 7,
        ]);

        $this->assertSame(7, $this->service->effectiveTotalClaims($userProduct));
    }

    public function test_effective_total_claims_supports_hourly_intervals(): void
    {
        $userProduct = new UserProduct([
            'duration_days_snapshot' => 2,
            'claim_interval_hours_snapshot' => 6,
            'total_claims_snapshot' => null,
        ]);

        $this->assertSame(6, $this->service->effectiveClaimIntervalHours($userProduct));
        $this->assertSame(8, $this->service->effectiveTotalClaims($userProduct));
    }

    public function test_summary_uses_grouped_rows_and_claim_income_commission_values(): void
    {
        $rows = collect([
            [
                'level' => 1,
                'price' => 500,
                'earning' => 15,
                'claimed_commission_estimate' => 5,
                'commission_per_claim' => 0.5,
            ],
            [
                'level' => 1,
                'price' => 300,
                'earning' => 9,
                'claimed_commission_estimate' => 0,
                'commission_per_claim' => 0.3,
            ],
            [
                'level' => 2,
                'price' => 200,
                'earning' => 4,
                'claimed_commission_estimate' => 1,
                'commission_per_claim' => 0.2,
            ],
        ]);
        $groups = collect([
            ['level' => 1, 'products' => $rows->take(2)->all()],
            ['level' => 2, 'products' => $rows->slice(2)->all()],
        ]);

        $summary = $this->service->summaryForGroups($groups, $rows);

        $this->assertSame(2, $summary['total_users']);
        $this->assertSame(3, $summary['total_products']);
        $this->assertSame(1000.0, $summary['total_sales']);
        $this->assertSame(28.0, $summary['total_estimated_commission']);
        $this->assertSame(6.0, $summary['total_claimed_commission_estimate']);
        $this->assertSame(1, $summary['b_users']);
        $this->assertSame(2, $summary['b_products']);
        $this->assertSame(24.0, $summary['b_estimated_commission']);
        $this->assertSame(4.0, $summary['c_estimated_commission']);
    }

    public function test_pagination_operates_on_groups(): void
    {
        $groups = collect([
            ['user_id' => 10, 'products' => [['product_name' => 'A'], ['product_name' => 'B']]],
            ['user_id' => 20, 'products' => [['product_name' => 'C']]],
            ['user_id' => 30, 'products' => [['product_name' => 'D']]],
        ]);

        $paginator = $this->service->paginateGroups($groups, 2, 2, '/api/v1/downline-products');

        $this->assertSame(3, $paginator->total());
        $this->assertSame(2, $paginator->lastPage());
        $this->assertSame([30], $paginator->getCollection()->pluck('user_id')->all());
    }
}
