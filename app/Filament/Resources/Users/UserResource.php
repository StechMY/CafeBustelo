<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\AdminTopUpsRelationManager;
use App\Filament\Resources\Users\RelationManagers\EarnedCommissionsRelationManager;
use App\Filament\Resources\Users\RelationManagers\LoginLogsRelationManager;
use App\Filament\Resources\Users\RelationManagers\ReferralsRelationManager;
use App\Filament\Resources\Users\RelationManagers\WalletTransactionsRelationManager;
use App\Filament\Resources\Users\RelationManagers\CheckInRecordsRelationManager;
use App\Filament\Resources\Users\RelationManagers\SvipPurchasesRelationManager;
use App\Filament\Resources\Users\RelationManagers\BankAccountRelationManager;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.user_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.users');
    }

    public static function getModelLabel(): string
    {
        return __('filament.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.users');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ReferralsRelationManager::class,
            LoginLogsRelationManager::class,
            AdminTopUpsRelationManager::class,
            WalletTransactionsRelationManager::class,
            EarnedCommissionsRelationManager::class,
            CheckInRecordsRelationManager::class,
            SvipPurchasesRelationManager::class,
            BankAccountRelationManager::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) User::query()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'username',
            'name',
            'phone',
            'referral_code',
            'last_login_ip',
        ];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            __('filament.name') => $record->name,
            __('filament.phone') => $record->phone ?: '-',
            __('filament.status') => $record->status,
            __('filament.referral_code') => $record->referral_code ?: '-',
            __('filament.last_ip') => $record->last_login_ip ?: '-',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}