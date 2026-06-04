<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('username')
                                    ->label('Username')
                                    ->required()
                                    ->maxLength(32)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash(),

                                TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(32),

                                TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(fn(?string $state): bool => filled($state))
                                    ->required(fn(string $operation): bool => $operation === 'create')
                                    ->maxLength(255),

                                TextInput::make('referral_code')
                                    ->label('Referral Code')
                                    ->maxLength(32)
                                    ->unique(ignoreRecord: true)
                                    ->default(fn(): string => strtoupper(Str::random(8)))
                                    ->dehydrateStateUsing(fn(?string $state): ?string => $state ? strtoupper($state) : null),
                            ]),
                    ]),

                Section::make('Referral')
                    ->schema([
                        Select::make('referrer_id')
                            ->label('Referred By')
                            ->relationship(
                                name: 'referrer',
                                titleAttribute: 'username',
                                modifyQueryUsing: fn($query) => $query->orderBy('username'),
                            )
                            ->preload()
                            ->nullable()
                            ->helperText('Select the user who referred this customer.'),
                    ]),

                Section::make('Status & Controls')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('User Status')
                                    ->required()
                                    ->default('inactive')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'suspended' => 'Suspended',
                                    ])
                                    ->native(false),

                                Toggle::make('is_virtual')
                                    ->label('Virtual User')
                                    ->default(false)
                                    ->onColor('warning')
                                    ->offColor('gray'),

                                Toggle::make('order_status')
                                    ->label('Can Buy Product')
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),

                                Toggle::make('withdraw_status')
                                    ->label('Can Withdraw')
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),
                                    
                                Toggle::make('check_in_status')
                                    ->label('Can Check in')
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),

                                Toggle::make('weekly_salary_status')
                                    ->label('Weekly Salary Claim')
                                    ->default(true),

                                Toggle::make('monthly_salary_status')
                                    ->label('Monthly Salary Claim')
                                    ->default(true),

                                Toggle::make('annual_salary_status')
                                    ->label('Annual Salary Claim')
                                    ->default(true),

                                TextInput::make('accumulated_recharge')
                                    ->label('Accumulated Recharge')
                                    ->prefix(config('app.currency'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->step('0.01'),
                            ]),
                    ]),

                Section::make('Login Information')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('registered_ip')
                                    ->label('Registered IP')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('last_login_ip')
                                    ->label('Last Login IP')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),
            ]);
    }
}