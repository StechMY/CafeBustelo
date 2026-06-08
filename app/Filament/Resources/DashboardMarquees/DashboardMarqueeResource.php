<?php

namespace App\Filament\Resources\DashboardMarquees;

use App\Filament\Resources\DashboardMarquees\Pages\CreateDashboardMarquee;
use App\Filament\Resources\DashboardMarquees\Pages\EditDashboardMarquee;
use App\Filament\Resources\DashboardMarquees\Pages\ListDashboardMarquees;
use App\Filament\Resources\DashboardMarquees\Pages\ViewDashboardMarquee;
use App\Filament\Resources\DashboardMarquees\Schemas\DashboardMarqueeForm;
use App\Filament\Resources\DashboardMarquees\Schemas\DashboardMarqueeInfolist;
use App\Filament\Resources\DashboardMarquees\Tables\DashboardMarqueesTable;
use App\Models\DashboardMarquee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DashboardMarqueeResource extends Resource
{
    protected static ?string $model = DashboardMarquee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSpeakerWave;

    protected static ?int $navigationSort = 71;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.platform_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.dashboard_marquees');
    }

    public static function getModelLabel(): string
    {
        return __('filament.dashboard_marquee');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.dashboard_marquees');
    }

    public static function form(Schema $schema): Schema
    {
        return DashboardMarqueeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DashboardMarqueeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DashboardMarqueesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDashboardMarquees::route('/'),
            'create' => CreateDashboardMarquee::route('/create'),
            'view' => ViewDashboardMarquee::route('/{record}'),
            'edit' => EditDashboardMarquee::route('/{record}/edit'),
        ];
    }
}
