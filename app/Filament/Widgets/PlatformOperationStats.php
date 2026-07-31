<?php

namespace App\Filament\Widgets;

use App\Data\Dashboard\DashboardFilterData;
use App\Services\Dashboard\CafeDashboardMetricsService;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class PlatformOperationStats extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected string $view = 'filament.widgets.platform-operation-stats';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getStats(): array
    {
        return app(CafeDashboardMetricsService::class)->operationMetrics(
            DashboardFilterData::fromFilamentFilters($this->pageFilters),
            PlatformDashboardQuery::supportDownlineUserIds(),
        );
    }
}
