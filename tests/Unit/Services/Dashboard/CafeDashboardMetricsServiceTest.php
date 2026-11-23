<?php

namespace Tests\Unit\Services\Dashboard;

use App\Data\Dashboard\DashboardFilterData;
use App\Filament\Widgets\PlatformOperationStats;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProduct;
use App\Services\Dashboard\CafeDashboardMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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

    public function test_selected_range_counts_valid_members_by_their_first_successful_purchase(): void
    {
        $product = Product::query()->create([
            'name' => 'Dashboard Product', 'code' => 'TEST', 'price' => 100,
            'daily_income' => 10, 'duration_days' => 30, 'status' => 'active',
        ]);
        $makeUser = function (string $username, array $attributes = []): User {
            $user = User::query()->create([
                'name' => $username, 'username' => $username, 'password' => 'password',
                'status' => 'active', 'is_virtual' => false, ...$attributes,
            ]);
            $user->forceFill(['created_at' => '2026-09-01 12:00:00'])->saveQuietly();

            return $user;
        };
        $buy = fn (User $user, string $purchasedAt, string $status = 'active'): UserProduct => $user->products()->create([
            'product_id' => $product->id, 'price_snapshot' => 100, 'daily_income_snapshot' => 10,
            'duration_days_snapshot' => 30, 'purchased_at' => $purchasedAt, 'next_claim_at' => $purchasedAt,
            'expires_at' => '2026-11-01 00:00:00', 'status' => $status,
        ]);

        $atStart = $makeUser('range_start');
        $buy($atStart, '2026-10-01 00:00:00');
        $buy($atStart, '2026-10-01 12:00:00');
        $existing = $makeUser('range_existing');
        $buy($existing, '2026-09-30 23:59:59', 'expired')->delete();
        $buy($existing, '2026-10-01 12:00:00');
        $buy($makeUser('range_suspended', ['status' => 'suspended']), '2026-10-01 12:00:00');
        $buy($makeUser('range_expired'), '2026-10-01 12:00:00', 'expired');
        $buy($makeUser('range_next_day'), '2026-10-02 00:00:00');
        $virtual = $makeUser('range_virtual', ['is_virtual' => true]);
        $buy($virtual, '2026-10-01 23:59:59');

        $service = app(CafeDashboardMetricsService::class);
        $date = ['startDate' => '2026-10-01', 'endDate' => '2026-10-01'];
        $realStats = $service->operationMetrics(DashboardFilterData::fromFilamentFilters($date));
        $this->assertSame(1, $realStats['selected']['valid_members_in_range']);
        $this->assertSame(3, $realStats['overview']['valid_members']);
        $all = DashboardFilterData::fromFilamentFilters([...$date, 'virtualMode' => 'include_all']);
        $this->assertSame(2, $service->operationMetrics($all)['selected']['valid_members_in_range']);
        $this->assertSame(1, $service->operationMetrics($all, [$existing->id, $virtual->id])['selected']['valid_members_in_range']);
        $this->assertSame(0, $service->operationMetrics($all, [])['selected']['valid_members_in_range']);

        app()->setLocale('zh_CN');
        Livewire::withoutLazyLoading()->test(PlatformOperationStats::class, ['pageFilters' => $date])
            ->assertSee('筛选期有效会员')
            ->assertSee('首次成功购入产品的日期落在所选范围内')
            ->assertSee(__('filament.operation_valid_members_desc'));
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

        $queries = implode("\n", array_column($longQueries, 'query'));

        $this->assertStringContainsString('group by DATE(reviewed_at)', $queries);
        $this->assertStringContainsString(
            'group by DATE(COALESCE(gateway_completed_at, reviewed_at))',
            $queries,
        );
    }
}
