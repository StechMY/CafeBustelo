<?php

namespace App\Filament\Resources\BonusCodes;

use App\Filament\Resources\BonusCodes\Pages\CreateBonusCode;
use App\Filament\Resources\BonusCodes\Pages\EditBonusCode;
use App\Filament\Resources\BonusCodes\Pages\ListBonusCodes;
use App\Filament\Resources\BonusCodes\Pages\ViewBonusCode;
use App\Filament\Resources\BonusCodes\Schemas\BonusCodeForm;
use App\Filament\Resources\BonusCodes\Schemas\BonusCodeInfolist;
use App\Filament\Resources\BonusCodes\Tables\BonusCodesTable;
use App\Models\BonusCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BonusCodeResource extends Resource
{
    protected static ?string $model = BonusCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Bonus Codes';

    protected static ?string $modelLabel = 'Bonus Code';

    protected static ?string $pluralModelLabel = 'Bonus Codes';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return BonusCodeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BonusCodeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BonusCodesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) BonusCode::query()
            ->where('status', BonusCode::STATUS_UNUSED)
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBonusCodes::route('/'),
            'create' => CreateBonusCode::route('/create'),
            'view' => ViewBonusCode::route('/{record}'),
            'edit' => EditBonusCode::route('/{record}/edit'),
        ];
    }
}