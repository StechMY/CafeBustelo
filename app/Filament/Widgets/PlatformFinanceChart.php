<?php

namespace App\Filament\Widgets;

use App\Data\Dashboard\DashboardFilterData;
use App\Services\Dashboard\CafeDashboardMetricsService;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PlatformFinanceChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public function getHeading(): string
    {
        return __('filament.approved_recharge_vs_withdraw');
    }

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $filters = DashboardFilterData::fromFilamentFilters($this->pageFilters);

        if ($filters->dateFrom->greaterThan($filters->dateTo)) {
            $filters = new DashboardFilterData(
                $filters->dateTo->startOfDay(),
                $filters->dateFrom->endOfDay(),
                $filters->virtualMode,
            );
        }

        $metrics = app(CafeDashboardMetricsService::class)->financeChart(
            $filters,
            PlatformDashboardQuery::supportDownlineUserIds(),
        );

        return [
            'datasets' => [
                [
                    'label' => __('filament.approved_recharge'),
                    'data' => $metrics['approved_recharge'],
                ],
                [
                    'label' => __('filament.approved_withdraw'),
                    'data' => $metrics['approved_withdraw'],
                ],
                [
                    'label' => __('filament.net'),
                    'data' => $metrics['net'],
                ],
            ],
            'labels' => array_map(
                fn (string $date): string => CarbonImmutable::parse($date)->format('d M'),
                $metrics['labels'],
            ),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
