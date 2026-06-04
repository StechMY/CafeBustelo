<?php

namespace App\Filament\Resources\CommissionLevels\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommissionLevelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Commission Level')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('level')
                                    ->label('Level')
                                    ->weight('bold'),

                                TextEntry::make('name')
                                    ->weight('bold'),

                                TextEntry::make('rate_percent')
                                    ->label('Rate')
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . '%'),

                                IconEntry::make('is_active')
                                    ->boolean(),

                                TextEntry::make('sort_order'),

                                TextEntry::make('created_at')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}