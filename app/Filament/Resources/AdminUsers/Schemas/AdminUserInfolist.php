<?php

namespace App\Filament\Resources\AdminUsers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdminUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.admin_user'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('name')
                                    ->weight('bold'),

                                TextEntry::make('username'),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),

                                TextEntry::make('roles.name')
                                    ->label(__('filament.roles'))
                                    ->badge()
                                    ->separator(','),

                                TextEntry::make('last_login_ip')
                                    ->placeholder('-'),

                                TextEntry::make('last_login_at')
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('created_at')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }
}