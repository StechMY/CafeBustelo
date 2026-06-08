<?php

namespace App\Filament\Resources\AdminUsers;

use App\Filament\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Filament\Resources\AdminUsers\Pages\ViewAdminUser;
use App\Filament\Resources\AdminUsers\Schemas\AdminUserForm;
use App\Filament\Resources\AdminUsers\Schemas\AdminUserInfolist;
use App\Filament\Resources\AdminUsers\Tables\AdminUsersTable;
use App\Models\AdminUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AdminUserResource extends Resource
{
    protected static ?string $model = AdminUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 90;
    
    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasRole('super_admin') ?? false;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.system_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.admin_users');
    }

    public static function getModelLabel(): string
    {
        return __('filament.admin_user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.admin_users');
    }

    public static function form(Schema $schema): Schema
    {
        return AdminUserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdminUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminUsersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /**
         * Optional:
         * Non-super-admin cannot even see super_admin accounts.
         */
        if (!auth('admin')->user()?->hasRole('super_admin')) {
            $query->whereDoesntHave('roles', function (Builder $query): void {
                $query->where('name', 'super_admin');
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'view' => ViewAdminUser::route('/{record}'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }
}