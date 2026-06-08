<?php

namespace App\Filament\Resources\CommissionLevels;

use App\Filament\Resources\CommissionLevels\Pages\CreateCommissionLevel;
use App\Filament\Resources\CommissionLevels\Pages\EditCommissionLevel;
use App\Filament\Resources\CommissionLevels\Pages\ListCommissionLevels;
use App\Filament\Resources\CommissionLevels\Pages\ViewCommissionLevel;
use App\Filament\Resources\CommissionLevels\Schemas\CommissionLevelForm;
use App\Filament\Resources\CommissionLevels\Schemas\CommissionLevelInfolist;
use App\Filament\Resources\CommissionLevels\Tables\CommissionLevelsTable;
use App\Models\CommissionLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CommissionLevelResource extends Resource
{
    protected static ?string $model = CommissionLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 43;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.commission_levels');
    }

    public static function getModelLabel(): string
    {
        return __('filament.commission_level');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.commission_levels');
    }

    public static function form(Schema $schema): Schema
    {
        return CommissionLevelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommissionLevelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommissionLevelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionLevels::route('/'),
            'create' => CreateCommissionLevel::route('/create'),
            'view' => ViewCommissionLevel::route('/{record}'),
            'edit' => EditCommissionLevel::route('/{record}/edit'),
        ];
    }
}