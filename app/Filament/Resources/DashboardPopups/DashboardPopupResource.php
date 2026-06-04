<?php

namespace App\Filament\Resources\DashboardPopups;

use App\Filament\Resources\DashboardPopups\Pages\CreateDashboardPopup;
use App\Filament\Resources\DashboardPopups\Pages\EditDashboardPopup;
use App\Filament\Resources\DashboardPopups\Pages\ListDashboardPopups;
use App\Filament\Resources\DashboardPopups\Pages\ViewDashboardPopup;
use App\Filament\Resources\DashboardPopups\Schemas\DashboardPopupForm;
use App\Filament\Resources\DashboardPopups\Schemas\DashboardPopupInfolist;
use App\Filament\Resources\DashboardPopups\Tables\DashboardPopupsTable;
use App\Models\DashboardPopup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DashboardPopupResource extends Resource
{
    protected static ?string $model = DashboardPopup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Dashboard Popups';

    protected static ?int $navigationSort = 72;

    public static function form(Schema $schema): Schema
    {
        return DashboardPopupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DashboardPopupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DashboardPopupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDashboardPopups::route('/'),
            'create' => CreateDashboardPopup::route('/create'),
            'view' => ViewDashboardPopup::route('/{record}'),
            'edit' => EditDashboardPopup::route('/{record}/edit'),
        ];
    }
}