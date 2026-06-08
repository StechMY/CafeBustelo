<?php

namespace App\Filament\Resources\VipLevels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VipLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.basic_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('level')
                                    ->label(__('filament.level'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('name')
                                    ->label(__('filament.name'))
                                    ->required()
                                    ->maxLength(100),

                                TextInput::make('position_name')
                                    ->label(__('filament.position_name'))
                                    ->maxLength(100)
                                    ->placeholder(__('filament.junior_manager_city_leader')),

                                Select::make('status')
                                    ->label(__('filament.status'))
                                    ->required()
                                    ->default('active')
                                    ->options([
                                        'active' => __('filament.active'),
                                        'inactive' => __('filament.inactive'),
                                    ])
                                    ->native(false),
                            ]),
                    ]),

                Section::make(__('filament.level_requirements'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('required_accumulated_recharge')
                                    ->label(__('filament.required_accumulated_recharge'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('required_active_team_size')
                                    ->label(__('filament.required_active_team_size'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                    ]),

                Section::make(__('filament.income_and_salary_benefits'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('daily_check_in_amount')
                                    ->label(__('filament.daily_check_in_amount'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('weekly_salary')
                                    ->label(__('filament.weekly_salary'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('monthly_salary')
                                    ->label(__('filament.monthly_salary'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('team_subsidy')
                                    ->label(__('filament.monthly_team_subsidy'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('annual_salary')
                                    ->label(__('filament.annual_salary'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('min_withdraw_amount')
                                    ->label(__('filament.minimum_withdraw_amount'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('withdraw_fee_percent')
                                    ->label(__('filament.withdraw_fee_percent'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),
                            ]),
                    ]),
            ]);
    }
}