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
                Section::make('Marquee')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('title')
                                    ->placeholder('-'),

                                TextEntry::make('type')
                                    ->badge(),

                                TextEntry::make('message')
                                    ->columnSpanFull(),

                                IconEntry::make('is_enabled')
                                    ->boolean(),

                                TextEntry::make('sort_order'),

                                TextEntry::make('updated_at')
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }
}