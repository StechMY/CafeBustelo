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
                Section::make('Commission Level')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('level')
                                    ->label('Level')
                                    ->helperText('1 = B, 2 = C, 3 = D')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(64)
                                    ->placeholder('B Level'),

                                TextInput::make('rate_percent')
                                    ->label('Rate')
                                    ->suffix('%')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step('0.01'),

                                TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}