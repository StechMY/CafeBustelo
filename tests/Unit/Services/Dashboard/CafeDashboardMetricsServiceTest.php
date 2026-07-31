<?php

namespace Tests\Unit\Services\Dashboard;

use App\Data\Dashboard\DashboardFilterData;
use App\Services\Dashboard\CafeDashboardMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class CafeDashboardMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $connection = 'sqlite';
        $database = ':memory:';

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $database = (string) getenv('MANAGEMENT_DASHBOARD_TEST_DATABASE');

            if ($database === '') {
                $this->markTestSkipped(
                    'SQLite PDO is unavailable and MANAGEMENT_DASHBOARD_TEST_DATABASE is not set.',
                );
            }

            $connection = 'mysql';
        }

        putenv("DB_CONNECTION={$connection}");
        putenv("DB_DATABASE={$database}");
        $_ENV['DB_CONNECTION'] = $connection;
        $_ENV['DB_DATABASE'] = $database;
        $_SERVER['DB_CONNECTION'] = $connection;
        $_SERVER['DB_DATABASE'] = $database;

        parent::setUp();

        config([
            'app.timezone' => 'Asia/Kuala_Lumpur',
            'database.default' => $connection,
            "database.connections.{$connection}.database" => $database,
        ]);

    }

    public function test_finance_chart_uses_two_grouped_queries_regardless_of_range_length(): void
    {
        $service = app(CafeDashboardMetricsService::class);
        $shortFilters = new DashboardFilterData(
            CarbonImmutable::parse('2026-07-01', 'Asia/Kuala_Lumpur')->startOfDay(),
            CarbonImmutable::parse('2026-07-02', 'Asia/Kuala_Lumpur')->endOfDay(),
            'exclude_virtual',
        );
        $longFilters = new DashboardFilterData(
            CarbonImmutable::parse('2026-01-01', 'Asia/Kuala_Lumpur')->startOfDay(),
            CarbonImmutable::parse('2026-12-31', 'Asia/Kuala_Lumpur')->endOfDay(),
            'exclude_virtual',
        );

        DB::enableQueryLog();
        $shortChart = $service->financeChart($shortFilters);
        $shortQueries = DB::getQueryLog();

        DB::flushQueryLog();
        $longChart = $service->financeChart($longFilters);
        $longQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(2, $shortQueries);
        $this->assertCount(2, $longQueries);
        $this->assertCount(2, $shortChart['labels']);
        $this->assertCount(365, $longChart['labels']);

        foreach ($longQueries as $query) {
            $this->assertStringContainsString('group by DATE(reviewed_at)', $query['query']);
        }
    }
}
