<?php

namespace App\Filament\Resources\DashboardPopups\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DashboardPopupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Popup')
                    ->schema([
                        ImageEntry::make('image_path')
                            ->disk('do_spaces')
                            ->height(220)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('title')
                                    ->weight('bold'),

                                IconEntry::make('is_enabled')
                                    ->boolean(),

                                IconEntry::make('show_every_visit')
                                    ->boolean(),

                                TextEntry::make('description')
                                    ->columnSpanFull(),

                                TextEntry::make('button_text')
                                    ->placeholder('-'),

                                TextEntry::make('button_url')
                                    ->url(fn ($record): ?string => $record->button_url, true)
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}