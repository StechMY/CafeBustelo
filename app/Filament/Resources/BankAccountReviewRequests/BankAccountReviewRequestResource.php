<?php

namespace App\Filament\Resources\BankAccountReviewRequests;

use App\Filament\Resources\BankAccountReviewRequests\Pages\ListBankAccountReviewRequests;
use App\Filament\Resources\BankAccountReviewRequests\Pages\ViewBankAccountReviewRequest;
use App\Filament\Resources\BankAccountReviewRequests\Schemas\BankAccountReviewRequestInfolist;
use App\Filament\Resources\BankAccountReviewRequests\Tables\BankAccountReviewRequestsTable;
use App\Models\BankAccountReviewRequest;
use App\Support\FilamentSupportScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BankAccountReviewRequestResource extends Resource
{
    protected static ?string $model = BankAccountReviewRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 12;

    public static function getEloquentQuery(): Builder
    {
        return FilamentSupportScope::applyToUserOwnedQuery(parent::getEloquentQuery());
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return FilamentSupportScope::applyToUserOwnedQuery(parent::getGlobalSearchEloquentQuery());
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.bank_account_review_requests');
    }

    public static function getModelLabel(): string
    {
        return __('filament.bank_account_review_request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.bank_account_review_requests');
    }

    public static function infolist(Schema $schema): Schema
    {
        return BankAccountReviewRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankAccountReviewRequestsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = FilamentSupportScope::applyToUserOwnedQuery(
            BankAccountReviewRequest::query(),
        )->where('status', BankAccountReviewRequest::STATUS_PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankAccountReviewRequests::route('/'),
            'view' => ViewBankAccountReviewRequest::route('/{record}'),
        ];
    }
}
