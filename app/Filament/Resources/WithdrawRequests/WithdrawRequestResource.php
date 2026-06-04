<?php

namespace App\Filament\Resources\WithdrawRequests;

use App\Filament\Resources\WithdrawRequests\Pages\ListWithdrawRequests;
use App\Filament\Resources\WithdrawRequests\Pages\ViewWithdrawRequest;
use App\Filament\Resources\WithdrawRequests\Schemas\WithdrawRequestForm;
use App\Filament\Resources\WithdrawRequests\Schemas\WithdrawRequestInfolist;
use App\Filament\Resources\WithdrawRequests\Tables\WithdrawRequestsTable;
use App\Models\WithdrawRequest;
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

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Withdraw Requests';

    protected static ?string $modelLabel = 'Withdraw Request';

    protected static ?string $pluralModelLabel = 'Withdraw Requests';

    protected static ?int $navigationSort = 20;

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
        return (string) WithdrawRequest::query()
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