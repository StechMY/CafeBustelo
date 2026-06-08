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
                Section::make(__('filament.commission_level'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('level')
                                    ->label(__('filament.level'))
                                    ->weight('bold'),

                                TextEntry::make('name')
                                    ->label(__('filament.name'))
                                    ->weight('bold'),

                                TextEntry::make('rate_percent')
                                    ->label(__('filament.rate'))
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . '%'),

                                IconEntry::make('is_active')
                                    ->label(__('filament.is_active'))
                                    ->boolean(),

                                TextEntry::make('sort_order')
                                    ->label(__('filament.sort_order')),

                                TextEntry::make('created_at')
                                    ->label(__('filament.created_at'))
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make(__('filament.description'))
                    ->schema([
                        TextEntry::make('description')
                            ->label(__('filament.description'))
                            ->placeholder('-'),
                    ]),
            ]);
    }
}