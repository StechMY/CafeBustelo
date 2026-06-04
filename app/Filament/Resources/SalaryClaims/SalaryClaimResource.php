<?php

namespace App\Filament\Resources\SalaryClaims;

use App\Filament\Resources\SalaryClaims\Pages\ListSalaryClaims;
use App\Filament\Resources\SalaryClaims\Pages\ViewSalaryClaim;
use App\Filament\Resources\SalaryClaims\Schemas\SalaryClaimInfolist;
use App\Filament\Resources\SalaryClaims\Tables\SalaryClaimsTable;
use App\Models\SalaryClaim;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SalaryClaimResource extends Resource
{
    protected static ?string $model = SalaryClaim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Salary Claims';

    protected static ?string $modelLabel = 'Salary Claim';

    protected static ?string $pluralModelLabel = 'Salary Claims';

    protected static ?int $navigationSort = 41;

    public static function infolist(Schema $schema): Schema
    {
        return SalaryClaimInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalaryClaimsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalaryClaims::route('/'),
            'view' => ViewSalaryClaim::route('/{record}'),
        ];
    }
}