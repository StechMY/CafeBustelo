<?php

namespace App\Filament\Resources\DashboardMarquees\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DashboardMarqueeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.marquee'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('title')
                                    ->label(__('filament.title'))
                                    ->placeholder('-'),

                                TextEntry::make('type')
                                    ->label(__('filament.type'))
                                    ->badge(),

                                TextEntry::make('message')
                                    ->label(__('filament.message'))
                                    ->columnSpanFull(),

                                IconEntry::make('is_enabled')
                                    ->label(__('filament.is_enabled'))
                                    ->boolean(),

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