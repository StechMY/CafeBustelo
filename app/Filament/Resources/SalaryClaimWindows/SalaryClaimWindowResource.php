<?php

namespace App\Filament\Resources\SalaryClaimWindows;

use App\Filament\Resources\SalaryClaimWindows\Pages\CreateSalaryClaimWindow;
use App\Filament\Resources\SalaryClaimWindows\Pages\EditSalaryClaimWindow;
use App\Filament\Resources\SalaryClaimWindows\Pages\ListSalaryClaimWindows;
use App\Filament\Resources\SalaryClaimWindows\Pages\ViewSalaryClaimWindow;
use App\Filament\Resources\SalaryClaimWindows\Schemas\SalaryClaimWindowForm;
use App\Filament\Resources\SalaryClaimWindows\Schemas\SalaryClaimWindowInfolist;
use App\Filament\Resources\SalaryClaimWindows\Tables\SalaryClaimWindowsTable;
use App\Models\SalaryClaimWindow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SalaryClaimWindowResource extends Resource
{
    protected static ?string $model = SalaryClaimWindow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 42;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.salary_claim_windows');
    }

    public static function getModelLabel(): string
    {
        return __('filament.salary_claim_window');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.salary_claim_windows');
    }

    public static function form(Schema $schema): Schema
    {
        return SalaryClaimWindowForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalaryClaimWindowInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalaryClaimWindowsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalaryClaimWindows::route('/'),
            'create' => CreateSalaryClaimWindow::route('/create'),
            'view' => ViewSalaryClaimWindow::route('/{record}'),
            'edit' => EditSalaryClaimWindow::route('/{record}/edit'),
        ];
    }
}