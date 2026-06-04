<?php

namespace App\Filament\Resources\VipLevels;

use App\Filament\Resources\VipLevels\Pages\CreateVipLevel;
use App\Filament\Resources\VipLevels\Pages\EditVipLevel;
use App\Filament\Resources\VipLevels\Pages\ListVipLevels;
use App\Filament\Resources\VipLevels\Pages\ViewVipLevel;
use App\Filament\Resources\VipLevels\Schemas\VipLevelForm;
use App\Filament\Resources\VipLevels\Schemas\VipLevelInfolist;
use App\Filament\Resources\VipLevels\Tables\VipLevelsTable;
use App\Models\VipLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VipLevelResource extends Resource
{
    protected static ?string $model = VipLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'VIP Levels';

    protected static ?string $modelLabel = 'VIP Level';

    protected static ?string $pluralModelLabel = 'VIP Levels';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return VipLevelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VipLevelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VipLevelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVipLevels::route('/'),
            'create' => CreateVipLevel::route('/create'),
            'view' => ViewVipLevel::route('/{record}'),
            'edit' => EditVipLevel::route('/{record}/edit'),
        ];
    }
}