<?php

namespace App\Filament\Resources\SalaryClaimWindows\Schemas;

use App\Models\SalaryClaimWindow;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalaryClaimWindowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Claim Window')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('type')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->options([
                                        SalaryClaimWindow::TYPE_WEEKLY => 'Weekly',
                                        SalaryClaimWindow::TYPE_MONTHLY => 'Monthly',
                                        SalaryClaimWindow::TYPE_ANNUAL => 'Annual',
                                    ])
                                    ->native(false)
                                    ->live(),

                                Toggle::make('is_enabled')
                                    ->label('Enabled')
                                    ->default(true),

                                Select::make('day_of_week')
                                    ->label('Day of Week')
                                    ->options([
                                        0 => 'Sunday',
                                        1 => 'Monday',
                                        2 => 'Tuesday',
                                        3 => 'Wednesday',
                                        4 => 'Thursday',
                                        5 => 'Friday',
                                        6 => 'Saturday',
                                    ])
                                    ->native(false)
                                    ->required(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_WEEKLY)
                                    ->visible(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_WEEKLY),

                                TextInput::make('day_of_month')
                                    ->label('Day of Month')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->required(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_MONTHLY)
                                    ->visible(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_MONTHLY),

                                Select::make('month')
                                    ->label('Month')
                                    ->options([
                                        1 => 'January',
                                        2 => 'February',
                                        3 => 'March',
                                        4 => 'April',
                                        5 => 'May',
                                        6 => 'June',
                                        7 => 'July',
                                        8 => 'August',
                                        9 => 'September',
                                        10 => 'October',
                                        11 => 'November',
                                        12 => 'December',
                                    ])
                                    ->native(false)
                                    ->required(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_ANNUAL)
                                    ->visible(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_ANNUAL),

                                TextInput::make('day')
                                    ->label('Day')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->required(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_ANNUAL)
                                    ->visible(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_ANNUAL),

                                TimePicker::make('start_time')
                                    ->label('Start Time')
                                    ->seconds(false)
                                    ->required()
                                    ->default('00:00'),

                                TimePicker::make('end_time')
                                    ->label('End Time')
                                    ->seconds(false)
                                    ->required()
                                    ->default('23:59'),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}