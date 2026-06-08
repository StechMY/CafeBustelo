<?php

namespace App\Filament\Resources\Contacts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.contact'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('filament.name'))
                                    ->weight('bold'),

                                TextEntry::make('type')
                                    ->label(__('filament.type'))
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'whatsapp' => 'success',
                                        'telegram' => 'info',
                                        'email' => 'warning',
                                        'phone' => 'gray',
                                        default => 'gray',
                                    }),

                                TextEntry::make('status')
                                    ->label(__('filament.status'))
                                    ->badge()
                                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),

                                TextEntry::make('link')
                                    ->label(__('filament.link'))
                                    ->copyable()
                                    ->url(fn ($record): string => $record->link, true)
                                    ->columnSpanFull(),

                                TextEntry::make('sort_order')
                                    ->label(__('filament.sort_order')),

                                TextEntry::make('created_at')
                                    ->label(__('filament.created_at'))
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label(__('filament.updated_at'))
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