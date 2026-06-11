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
                Section::make(__('filament.account_information'))
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
                                    ->maxLength(32)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash(),

                                TextInput::make('phone')
                                    ->label(__('filament.phone'))
                                    ->tel()
                                    ->maxLength(32),

                                TextInput::make('password')
                                    ->label(__('filament.password'))
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(fn(?string $state): bool => filled($state))
                                    ->required(fn(string $operation): bool => $operation === 'create')
                                    ->maxLength(255),

                                TextInput::make('referral_code')
                                    ->label(__('filament.referral_code'))
                                    ->maxLength(32)
                                    ->unique(ignoreRecord: true)
                                    ->default(fn(): string => strtoupper(Str::random(8)))
                                    ->dehydrateStateUsing(fn(?string $state): ?string => $state ? strtoupper($state) : null),
                            ]),
                    ]),

                Section::make(__('filament.referral'))
                    ->schema([
                        Select::make('referrer_id')
                            ->label(__('filament.referred_by'))
                            ->relationship(
                                name: 'referrer',
                                titleAttribute: 'username',
                                modifyQueryUsing: fn($query) => $query->orderBy('username'),
                            )
                            ->preload()
                            ->nullable()
                            ->helperText(__('filament.select_the_user_who_referred_this_customer')),
                    ]),

                Section::make(__('filament.status_and_controls'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label(__('filament.user_status'))
                                    ->required()
                                    ->default('inactive')
                                    ->options([
                                        'active' => __('filament.active'),
                                        'inactive' => __('filament.inactive'),
                                        'suspended' => __('filament.suspended'),
                                    ])
                                    ->native(false),

                                Toggle::make('is_virtual')
                                    ->label(__('filament.virtual_user'))
                                    ->default(false)
                                    ->onColor('warning')
                                    ->offColor('gray'),

                                Toggle::make('order_status')
                                    ->label(__('filament.can_buy_product'))
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),

                                Toggle::make('withdraw_status')
                                    ->label(__('filament.can_withdraw'))
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),

                                Toggle::make('check_in_status')
                                    ->label(__('filament.can_check_in'))
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),

                                Toggle::make('weekly_salary_status')
                                    ->label(__('filament.weekly_salary_claim'))
                                    ->default(true),

                                Toggle::make('monthly_salary_status')
                                    ->label(__('filament.monthly_salary_claim'))
                                    ->default(true),

                                Toggle::make('annual_salary_status')
                                    ->label(__('filament.annual_salary_claim'))
                                    ->default(true),

                                TextInput::make('accumulated_recharge')
                                    ->label(__('filament.accumulated_recharge'))
                                    ->prefix(config('app.currency'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->step('0.01'),
                            ]),
                    ]),

                Section::make(__('filament.display_overrides'))
                    ->description(__('filament.display_overrides_desc'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('display_vip_level')
                                    ->label(__('filament.display_vip_level'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(99)
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_active_team_count')
                                    ->label(__('filament.display_active_team_count'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_accumulated_recharge')
                                    ->label(__('filament.display_accumulated_recharge'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_total_earning')
                                    ->label(__('filament.display_total_earning'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_total_withdrawals')
                                    ->label(__('filament.display_total_withdrawals'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_total_team_commission')
                                    ->label(__('filament.display_total_team_commission'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_total_team_recharge')
                                    ->label(__('filament.display_total_team_recharge'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_total_users_count')
                                    ->label(__('filament.display_total_users_count'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_valid_users_count')
                                    ->label(__('filament.display_valid_users_count'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.leave_empty_use_real_value')),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('display_b_count')
                                    ->label(__('filament.display_b_count'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_c_count')
                                    ->label(__('filament.display_c_count'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_d_count')
                                    ->label(__('filament.display_d_count'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_b_active_count')
                                    ->label(__('filament.display_b_active_count'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_c_active_count')
                                    ->label(__('filament.display_c_active_count'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.leave_empty_use_real_value')),

                                TextInput::make('display_d_active_count')
                                    ->label(__('filament.display_d_active_count'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.leave_empty_use_real_value')),
                            ]),
                    ])
                    ->collapsed(),

                Section::make(__('filament.login_information'))
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('registered_ip')
                                    ->label(__('filament.registered_ip'))
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('last_login_ip')
                                    ->label(__('filament.last_login_ip'))
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),
            ]);
    }
}