<?php

namespace App\Filament\Resources\DashboardPopups\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DashboardPopupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.popup_content'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament.title'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label(__('filament.description'))
                            ->rows(4)
                            ->columnSpanFull(),

                        FileUpload::make('image_path')
                            ->label(__('filament.popup_image'))
                            ->disk('do_spaces')
                            ->directory('dashboard/popups')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('button_text')
                                    ->label(__('filament.button_text'))
                                    ->maxLength(100)
                                    ->placeholder(__('filament.view_now')),

                                TextInput::make('button_url')
                                    ->label(__('filament.button_url'))
                                    ->url()
                                    ->maxLength(2048)
                                    ->placeholder('https://example.com'),
                            ]),
                    ]),

                Section::make(__('filament.display_settings'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_enabled')
                                    ->label(__('filament.is_enabled'))
                                    ->default(true),

                                Toggle::make('show_every_visit')
                                    ->label(__('filament.show_every_dashboard_visit'))
                                    ->helperText(__('filament.if_off_popup_shows_once_per_login_session'))
                                    ->default(false),

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