<?php

namespace App\Filament\Resources\DashboardMarquees\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DashboardMarqueeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Marquee Message')
                    ->schema([
                        TextInput::make('title')
                            ->maxLength(255)
                            ->placeholder('Important'),

                        Textarea::make('message')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('type')
                            ->required()
                            ->default('info')
                            ->options([
                                'info' => 'Info',
                                'warning' => 'Warning',
                                'success' => 'Success',
                                'danger' => 'Danger',
                            ])
                            ->native(false),
                    ]),

                Section::make('Display Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_enabled')
                                    ->default(true),

                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),

                                DateTimePicker::make('starts_at')
                                    ->seconds(false),

                                DateTimePicker::make('ends_at')
                                    ->seconds(false),
                            ]),
                    ]),
            ]);
    }
}