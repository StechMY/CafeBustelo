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

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.recharge_requests');
    }

    public static function getModelLabel(): string
    {
        return __('filament.recharge_request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.recharge_requests');
    }

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