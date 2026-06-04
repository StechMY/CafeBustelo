<?php

namespace App\Filament\Resources\RechargeRequests;

use App\Filament\Resources\RechargeRequests\Pages\CreateRechargeRequest;
use App\Filament\Resources\RechargeRequests\Pages\EditRechargeRequest;
use App\Filament\Resources\RechargeRequests\Pages\ListRechargeRequests;
use App\Filament\Resources\RechargeRequests\Pages\ViewRechargeRequest;
use App\Filament\Resources\RechargeRequests\Schemas\RechargeRequestForm;
use App\Filament\Resources\RechargeRequests\Schemas\RechargeRequestInfolist;
use App\Filament\Resources\RechargeRequests\Tables\RechargeRequestsTable;
use App\Models\RechargeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RechargeRequestResource extends Resource
{
    protected static ?string $model = RechargeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Recharge Requests';

    protected static ?string $modelLabel = 'Recharge Request';

    protected static ?string $pluralModelLabel = 'Recharge Requests';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return RechargeRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RechargeRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RechargeRequestsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) RechargeRequest::query()
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
            'index' => ListRechargeRequests::route('/'),
            // 'create' => CreateRechargeRequest::route('/create'),
            'view' => ViewRechargeRequest::route('/{record}'),
            // 'edit' => EditRechargeRequest::route('/{record}/edit'),
        ];
    }
}