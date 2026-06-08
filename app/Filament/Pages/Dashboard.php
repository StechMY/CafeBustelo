<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PlatformFinanceChart;
use App\Filament\Widgets\PlatformOverviewStats;
use App\Filament\Widgets\PlatformRequestStats;
use App\Filament\Widgets\PlatformUserStats;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getTitle(): string
    {
        return __('filament.admin_dashboard');
    }

    protected bool $persistsFiltersInSession = true;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.dashboard_filters'))
                    ->description(__('filament.filter_dashboard_statistics_by_date_range_and_virtual_users'))
                    ->schema([
                        DatePicker::make('startDate')
                            ->label(__('filament.start_date'))
                            ->default(now()->startOfMonth())
                            ->native(false),

                        DatePicker::make('endDate')
                            ->label(__('filament.end_date'))
                            ->default(now())
                            ->native(false),

                        Select::make('virtualMode')
                            ->label(__('filament.virtual_users'))
                            ->options([
                                'exclude_virtual' => __('filament.exclude_virtual_users'),
                                'include_all' => __('filament.include_all_users'),
                                'only_virtual' => __('filament.only_virtual_users'),
                            ])
                            ->default('exclude_virtual')
                            ->selectablePlaceholder(false),
                    ])
                    ->columns([
                        'default' => 3,
                        'md' => 3,
                    ])->columnSpan('full'),
            ]);
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    public function getWidgets(): array
    {
        return [
            PlatformOverviewStats::class,
            PlatformRequestStats::class,
            PlatformUserStats::class,
            PlatformFinanceChart::class,
        ];
    }
}