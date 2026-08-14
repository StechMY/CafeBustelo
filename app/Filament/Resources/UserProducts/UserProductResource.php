<?php

namespace App\Filament\Resources\UserProducts;

use App\Filament\Resources\UserProducts\Pages\ListUserProducts;
use App\Filament\Resources\UserProducts\Pages\ViewUserProduct;
use App\Filament\Resources\UserProducts\RelationManagers\DailyIncomeClaimsRelationManager;
use App\Filament\Resources\UserProducts\Schemas\UserProductInfolist;
use App\Filament\Resources\UserProducts\Tables\UserProductsTable;
use App\Models\UserProduct;
use App\Support\FilamentSupportScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserProductResource extends Resource
{
    protected static ?string $model = UserProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.user_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.user_products');
    }

    public static function getModelLabel(): string
    {
        return __('filament.user_product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.user_products');
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DailyIncomeClaimsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return FilamentSupportScope::applyToUserOwnedQuery(
            parent::getEloquentQuery()->with(['user', 'product']),
            'user_id',
        );
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return FilamentSupportScope::applyToUserOwnedQuery(
            parent::getGlobalSearchEloquentQuery()->with(['user', 'product']),
            'user_id',
        );
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'user.username',
            'user.name',
            'user.phone',
            'product.name',
            'product.code',
        ];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->product?->name
            ?? data_get($record->meta, 'product_name_snapshot')
            ?? __('filament.user_product').' #'.$record->getKey();
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            __('filament.username') => $record->user?->username ?: '-',
            __('filament.product_code') => $record->product?->code
                ?? data_get($record->meta, 'product_code_snapshot', '-'),
            __('filament.status') => __('filament.user_product_status_'.$record->status),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserProducts::route('/'),
            'view' => ViewUserProduct::route('/{record}'),
        ];
    }
}
