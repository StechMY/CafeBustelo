<?php

namespace App\Filament\Resources\RechargeMethods;

use App\Filament\Resources\RechargeMethods\Pages\CreateRechargeMethod;
use App\Filament\Resources\RechargeMethods\Pages\EditRechargeMethod;
use App\Filament\Resources\RechargeMethods\Pages\ListRechargeMethods;
use App\Filament\Resources\RechargeMethods\Schemas\RechargeMethodForm;
use App\Filament\Resources\RechargeMethods\Tables\RechargeMethodsTable;
use App\Models\RechargeMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RechargeMethodResource extends Resource
{
    protected static ?string $model = RechargeMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.recharge_methods');
    }

    public static function getModelLabel(): string
    {
        return __('filament.recharge_method');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.recharge_methods');
    }

    public static function form(Schema $schema): Schema
    {
        return RechargeMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RechargeMethodsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) RechargeMethod::query()
            ->where('status', RechargeMethod::STATUS_ACTIVE)
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRechargeMethods::route('/'),
            'create' => CreateRechargeMethod::route('/create'),
            'edit' => EditRechargeMethod::route('/{record}/edit'),
        ];
    }
}