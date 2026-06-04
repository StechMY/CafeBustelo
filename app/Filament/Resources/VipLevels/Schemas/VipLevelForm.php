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
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('level')
                                    ->label('Level')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(100),

                                TextInput::make('position_name')
                                    ->label('Position Name')
                                    ->maxLength(100)
                                    ->placeholder('Junior Manager / City Leader'),

                                Select::make('status')
                                    ->label('Status')
                                    ->required()
                                    ->default('active')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->native(false),
                            ]),
                    ]),

                Section::make('Level Requirements')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('required_accumulated_recharge')
                                    ->label('Required Accumulated Recharge')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('required_active_team_size')
                                    ->label('Required Active Team Size')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                    ]),

                Section::make('Income & Salary Benefits')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('daily_check_in_amount')
                                    ->label('Daily Check-in Amount')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('weekly_salary')
                                    ->label('Weekly Salary')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('monthly_salary')
                                    ->label('Monthly Salary')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('team_subsidy')
                                    ->label('Monthly Team Subsidy')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('annual_salary')
                                    ->label('Annual Salary')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),

                                TextInput::make('min_withdraw_amount')
                                    ->label('Minimum Withdraw Amount')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.01'),
                            ]),
                    ]),
            ]);
    }
}