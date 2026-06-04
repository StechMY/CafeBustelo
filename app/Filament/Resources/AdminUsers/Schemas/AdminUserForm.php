<?php

namespace App\Filament\Resources\AdminUsers\Schemas;

use App\Models\AdminUser;
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
                Section::make('Admin Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('username')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(AdminUser::class, 'username', ignoreRecord: true),

                                Select::make('status')
                                    ->required()
                                    ->default('active')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->native(false),

                                Select::make('roles')
                                    ->label('Role')
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
                            ]),
                    ]),

                Section::make('Password')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(fn($state): bool => filled($state))
                                    ->required(fn(string $operation): bool => $operation === 'create')
                                    ->maxLength(255),

                                TextInput::make('password_confirmation')
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