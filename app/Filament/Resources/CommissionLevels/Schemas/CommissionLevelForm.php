<?php

namespace App\Filament\Resources\CommissionLevels\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommissionLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.commission_level'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('level')
                                    ->label(__('filament.level'))
                                    ->helperText(__('filament.1_b_2_c_3_d'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('name')
                                    ->label(__('filament.name'))
                                    ->required()
                                    ->maxLength(64)
                                    ->placeholder(__('filament.b_level')),

                                TextInput::make('rate_percent')
                                    ->label(__('filament.rate'))
                                    ->suffix('%')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step('0.01'),

                                TextInput::make('sort_order')
                                    ->label(__('filament.sort_order'))
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_active')
                                    ->label(__('filament.active'))
                                    ->default(true),

                                Textarea::make('description')
                                    ->label(__('filament.description'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}