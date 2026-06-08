<?php

namespace App\Filament\Resources\DashboardSlides\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DashboardSlideInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.slide'))
                    ->schema([
                        ImageEntry::make('image_path')
                            ->label(__('filament.image_path'))
                            ->disk('do_spaces')
                            ->height(220)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('title')
                                    ->label(__('filament.title'))
                                    ->weight('bold'),

                                IconEntry::make('is_enabled')
                                    ->label(__('filament.is_enabled'))
                                    ->boolean(),

                                TextEntry::make('description')
                                    ->label(__('filament.description'))
                                    ->columnSpanFull(),

                                TextEntry::make('button_text')
                                    ->label(__('filament.button_text'))
                                    ->placeholder('-'),

                                TextEntry::make('button_url')
                                    ->label(__('filament.button_url'))
                                    ->url(fn ($record): ?string => $record->button_url, true)
                                    ->placeholder('-'),

                                TextEntry::make('sort_order')
                                    ->label(__('filament.sort_order')),

                                TextEntry::make('updated_at')
                                    ->label(__('filament.updated_at'))
                                    ->dateTime(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}