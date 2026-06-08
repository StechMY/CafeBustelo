<?php

namespace App\Filament\Resources\SalaryClaimWindows\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalaryClaimWindowInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.claim_window'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('type')
                                    ->label(__('filament.type'))
                                    ->badge()
                                    ->color('info'),

                                IconEntry::make('is_enabled')
                                    ->label(__('filament.is_enabled'))
                                    ->boolean(),

                                TextEntry::make('day_of_week')
                                    ->label(__('filament.day_of_week'))
                                    ->placeholder('-'),

                                TextEntry::make('day_of_month')
                                    ->label(__('filament.day_of_month'))
                                    ->placeholder('-'),

                                TextEntry::make('month')
                                    ->label(__('filament.month'))
                                    ->placeholder('-'),

                                TextEntry::make('day')
                                    ->label(__('filament.day'))
                                    ->placeholder('-'),

                                TextEntry::make('start_time')
                                    ->label(__('filament.start_time')),

                                TextEntry::make('end_time')
                                    ->label(__('filament.end_time')),
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