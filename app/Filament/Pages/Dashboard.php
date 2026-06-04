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

    protected static ?string $title = 'Admin Dashboard';

    protected bool $persistsFiltersInSession = true;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dashboard Filters')
                    ->description('Filter dashboard statistics by date range and virtual users.')
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->default(now()->startOfMonth())
                            ->native(false),

                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->default(now())
                            ->native(false),

                        Select::make('virtualMode')
                            ->label('Virtual Users')
                            ->options([
                                'exclude_virtual' => 'Exclude virtual users',
                                'include_all' => 'Include all users',
                                'only_virtual' => 'Only virtual users',
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