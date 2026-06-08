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
                Section::make(__('filament.claim_window'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('type')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->options([
                                        SalaryClaimWindow::TYPE_WEEKLY => __('filament.weekly'),
                                        SalaryClaimWindow::TYPE_MONTHLY => __('filament.monthly'),
                                        SalaryClaimWindow::TYPE_ANNUAL => __('filament.annual'),
                                    ])
                                    ->native(false)
                                    ->live(),

                                Toggle::make('is_enabled')
                                    ->label(__('filament.enabled'))
                                    ->default(true),

                                Select::make('day_of_week')
                                    ->label(__('filament.day_of_week'))
                                    ->options([
                                        0 => __('filament.sunday'),
                                        1 => __('filament.monday'),
                                        2 => __('filament.tuesday'),
                                        3 => __('filament.wednesday'),
                                        4 => __('filament.thursday'),
                                        5 => __('filament.friday'),
                                        6 => __('filament.saturday'),
                                    ])
                                    ->native(false)
                                    ->required(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_WEEKLY)
                                    ->visible(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_WEEKLY),

                                TextInput::make('day_of_month')
                                    ->label(__('filament.day_of_month'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->required(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_MONTHLY)
                                    ->visible(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_MONTHLY),

                                Select::make('month')
                                    ->label(__('filament.month'))
                                    ->options([
                                        1 => __('filament.january'),
                                        2 => __('filament.february'),
                                        3 => __('filament.march'),
                                        4 => __('filament.april'),
                                        5 => __('filament.may'),
                                        6 => __('filament.june'),
                                        7 => __('filament.july'),
                                        8 => __('filament.august'),
                                        9 => __('filament.september'),
                                        10 => __('filament.october'),
                                        11 => __('filament.november'),
                                        12 => __('filament.december'),
                                    ])
                                    ->native(false)
                                    ->required(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_ANNUAL)
                                    ->visible(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_ANNUAL),

                                TextInput::make('day')
                                    ->label(__('filament.day'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->required(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_ANNUAL)
                                    ->visible(fn ($get): bool => $get('type') === SalaryClaimWindow::TYPE_ANNUAL),

                                TimePicker::make('start_time')
                                    ->label(__('filament.start_time'))
                                    ->seconds(false)
                                    ->required()
                                    ->default('00:00'),

                                TimePicker::make('end_time')
                                    ->label(__('filament.end_time'))
                                    ->seconds(false)
                                    ->required()
                                    ->default('23:59'),

                                Textarea::make('description')
                                    ->label(__('filament.description'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}