<?php

namespace App\Filament\Resources\WithdrawRequests;

use App\Filament\Resources\WithdrawRequests\Pages\ListWithdrawRequests;
use App\Filament\Resources\WithdrawRequests\Pages\ViewWithdrawRequest;
use App\Filament\Resources\WithdrawRequests\Schemas\WithdrawRequestForm;
use App\Filament\Resources\WithdrawRequests\Schemas\WithdrawRequestInfolist;
use App\Filament\Resources\WithdrawRequests\Tables\WithdrawRequestsTable;
use App\Models\WithdrawRequest;
use App\Support\FilamentSupportScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class WithdrawRequestResource extends Resource
{
    protected static ?string $model = WithdrawRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return FilamentSupportScope::applyToUserOwnedQuery(
            parent::getEloquentQuery(),
            'user_id'
        )
            ->with(['user', 'reviewer'])
            ->withExists('gatewayCancellationTransactions');
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return FilamentSupportScope::applyToUserOwnedQuery(
            parent::getGlobalSearchEloquentQuery(),
            'user_id'
        );
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.withdraw_requests');
    }

    public static function getModelLabel(): string
    {
        return __('filament.withdraw_request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.withdraw_requests');
    }

    public static function form(Schema $schema): Schema
    {
        return WithdrawRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WithdrawRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WithdrawRequestsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) FilamentSupportScope::applyToUserOwnedQuery(
            WithdrawRequest::query(),
            'user_id'
        )
            ->where('status', 'pending')
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }


    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawRequests::route('/'),
            'view' => ViewWithdrawRequest::route('/{record}'),
        ];
    }
}
