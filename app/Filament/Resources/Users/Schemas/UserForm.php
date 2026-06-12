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

                                self::displayIntegerInput(
                                    'display_active_team_count',
                                    __('filament.display_active_team_count')
                                ),

                                self::displayMoneyInput(
                                    'display_accumulated_recharge',
                                    __('filament.display_accumulated_recharge')
                                ),

                                self::displayMoneyInput(
                                    'display_total_earning',
                                    __('filament.display_total_earning')
                                ),

                                self::displayMoneyInput(
                                    'display_total_withdrawals',
                                    __('filament.display_total_withdrawals')
                                ),

                                self::displayMoneyInput(
                                    'display_total_team_commission',
                                    __('filament.display_total_team_commission')
                                ),

                                self::displayMoneyInput(
                                    'display_total_team_recharge',
                                    __('filament.display_total_team_recharge')
                                ),

                                self::displayIntegerInput(
                                    'display_total_users_count',
                                    __('filament.display_total_users_count')
                                ),

                                self::displayIntegerInput(
                                    'display_valid_users_count',
                                    __('filament.display_valid_users_count')
                                ),

                                self::displayMoneyInput(
                                    'display_income_today',
                                    __('filament.display_income_today')
                                ),

                                self::displayMoneyInput(
                                    'display_yesterday_earnings',
                                    __('filament.display_yesterday_earnings')
                                ),

                                self::displayMoneyInput(
                                    'display_team_income',
                                    __('filament.display_team_income')
                                ),

                                self::displayMoneyInput(
                                    'display_daily_income_total',
                                    __('filament.display_daily_income_total')
                                ),

                                self::displayMoneyInput(
                                    'display_salary_income_total',
                                    __('filament.display_salary_income_total')
                                ),

                                self::displayMoneyInput(
                                    'display_wallet_total_credit',
                                    __('filament.display_wallet_total_credit')
                                ),

                                self::displayMoneyInput(
                                    'display_wallet_total_debit',
                                    __('filament.display_wallet_total_debit')
                                ),
                            ]),

                        Grid::make(3)
                            ->schema([
                                self::displayIntegerInput(
                                    'display_b_count',
                                    __('filament.display_b_count')
                                ),

                                self::displayIntegerInput(
                                    'display_c_count',
                                    __('filament.display_c_count')
                                ),

                                self::displayIntegerInput(
                                    'display_d_count',
                                    __('filament.display_d_count')
                                ),

                                self::displayIntegerInput(
                                    'display_b_active_count',
                                    __('filament.display_b_active_count')
                                ),

                                self::displayIntegerInput(
                                    'display_c_active_count',
                                    __('filament.display_c_active_count')
                                ),

                                self::displayIntegerInput(
                                    'display_d_active_count',
                                    __('filament.display_d_active_count')
                                ),
                            ]),

                        Section::make(__('filament.display_team_statistics'))
                            ->description(__('filament.display_team_statistics_desc'))
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        self::displayIntegerInput(
                                            'display_today_joined_count',
                                            __('filament.display_today_joined_count')
                                        ),

                                        self::displayIntegerInput(
                                            'display_yesterday_joined_count',
                                            __('filament.display_yesterday_joined_count')
                                        ),

                                        self::displayMoneyInput(
                                            'display_today_recharge',
                                            __('filament.display_today_recharge')
                                        ),

                                        self::displayMoneyInput(
                                            'display_yesterday_recharge',
                                            __('filament.display_yesterday_recharge')
                                        ),

                                        self::displayMoneyInput(
                                            'display_this_month_recharge',
                                            __('filament.display_this_month_recharge')
                                        ),

                                        self::displayMoneyInput(
                                            'display_last_month_recharge',
                                            __('filament.display_last_month_recharge')
                                        ),
                                    ]),
                            ])
                            ->collapsed(),
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

    private static function displayMoneyInput(string $field, string $label): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->minValue(0)
            ->step('0.01')
            ->placeholder('1,000.00')
            ->helperText(__('filament.leave_empty_use_real_value'))
            ->formatStateUsing(function ($state): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return number_format((float) $state, 2, '.', ',');
            })
            ->dehydrateStateUsing(function ($state): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return str_replace(',', '', (string) $state);
            })
            ->rule('nullable')
            ->rule('regex:/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/')
            ->extraInputAttributes([
                'inputmode' => 'decimal',
            ]);
    }

    private static function displayIntegerInput(string $field, string $label): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->minValue(0)
            ->placeholder('10,000')
            ->helperText(__('filament.leave_empty_use_real_value'))
            ->formatStateUsing(function ($state): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return number_format((int) $state);
            })
            ->dehydrateStateUsing(function ($state): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return str_replace(',', '', (string) $state);
            })
            ->rule('nullable')
            ->rule('regex:/^\d{1,3}(,\d{3})*$|^\d+$/')
            ->extraInputAttributes([
                'inputmode' => 'numeric',
            ]);
    }
}