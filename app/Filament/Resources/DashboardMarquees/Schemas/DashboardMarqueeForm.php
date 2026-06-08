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
                Section::make(__('filament.marquee_message'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament.title'))
                            ->maxLength(255)
                            ->placeholder(__('filament.important')),

                        Textarea::make('message')
                            ->label(__('filament.message'))
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('type')
                            ->label(__('filament.type'))
                            ->required()
                            ->default('info')
                            ->options([
                                'info' => __('filament.info'),
                                'warning' => __('filament.warning'),
                                'success' => __('filament.success'),
                                'danger' => __('filament.danger'),
                            ])
                            ->native(false),
                    ]),

                Section::make(__('filament.display_settings'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_enabled')
                                    ->label(__('filament.is_enabled'))
                                    ->default(true),

                                TextInput::make('sort_order')
                                    ->label(__('filament.sort_order'))
                                    ->numeric()
                                    ->default(0),

                                DateTimePicker::make('starts_at')
                                    ->label(__('filament.starts_at'))
                                    ->seconds(false),

                                DateTimePicker::make('ends_at')
                                    ->label(__('filament.ends_at'))
                                    ->seconds(false),
                            ]),
                    ]),
            ]);
    }
}