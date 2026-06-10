<?php

namespace App\Filament\Resources\AdminUsers\Schemas;

use App\Models\AdminUser;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.admin_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament.name'))
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('username')
                                    ->label(__('filament.username'))
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(AdminUser::class, 'username', ignoreRecord: true),

                                Select::make('status')
                                    ->label(__('filament.status'))
                                    ->required()
                                    ->default('active')
                                    ->options([
                                        'active' => __('filament.active'),
                                        'inactive' => __('filament.inactive'),
                                    ])
                                    ->native(false),

                                Select::make('roles')
                                    ->label(__('filament.role'))
                                    ->relationship('roles', 'name', function ($query) {

                                        /**
                                         * Only super_admin can assign super_admin role.
                                         */
                                        if (!auth('admin')->user()?->hasRole('super_admin')) {
                                            $query->where('name', '!=', 'super_admin');
                                        }

                                        return $query;
                                    })
                                    ->multiple()
                                    ->preload()
                                    ->native(false)
                                    ->required(),

                                Select::make('managed_user_id')
                                    ->label(__('filament.managed_referral_user'))
                                    ->options(fn(): array => User::query()
                                        ->orderBy('username')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn(User $user) => [
                                            $user->id => "{$user->username} ({$user->referral_code})",
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->getSearchResultsUsing(fn(string $search): array => User::query()
                                        ->where(function ($query) use ($search): void {
                                            $query
                                                ->where('username', 'like', "%{$search}%")
                                                ->orWhere('name', 'like', "%{$search}%")
                                                ->orWhere('phone', 'like', "%{$search}%")
                                                ->orWhere('referral_code', 'like', "%{$search}%");
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn(User $user) => [
                                            $user->id => "{$user->username} ({$user->referral_code})",
                                        ])
                                        ->all())
                                    ->getOptionLabelUsing(fn($value): ?string => User::query()
                                        ->whereKey($value)
                                        ->first()
                                            ?->username)
                                    ->native(false)
                                    ->helperText(__('filament.managed_referral_user_help')),
                            ]),
                    ]),

                Section::make(__('filament.password'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('password')
                                    ->label(__('filament.password'))
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(fn($state): bool => filled($state))
                                    ->required(fn(string $operation): bool => $operation === 'create')
                                    ->maxLength(255),

                                TextInput::make('password_confirmation')
                                    ->label(__('filament.password_confirmation'))
                                    ->password()
                                    ->revealable()
                                    ->same('password')
                                    ->dehydrated(false)
                                    ->required(fn(string $operation): bool => $operation === 'create'),
                            ]),
                    ]),
            ]);
    }
}