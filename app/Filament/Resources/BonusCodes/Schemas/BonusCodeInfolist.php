<?php

namespace App\Filament\Resources\BonusCodes\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BonusCodeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.bonus_code'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('code')
                                    ->label(__('filament.code'))
                                    ->weight('bold')
                                    ->copyable(),

                                TextEntry::make('owner.username')
                                    ->label(__('filament.owner'))
                                    ->copyable(),

                                TextEntry::make('amount')
                                    ->label(__('filament.amount'))
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('type')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'admin' => 'warning',
                                        'invite' => 'info',
                                        'upgrade' => 'success',
                                        'compensation' => 'danger',
                                        default => 'gray',
                                    }),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'unused' => 'warning',
                                        'used' => 'success',
                                        'cancelled' => 'danger',
                                        'expired' => 'gray',
                                        default => 'gray',
                                    }),

                                TextEntry::make('claim.user.username')
                                    ->label(__('filament.claimed_by'))
                                    ->placeholder('-')
                                    ->copyable(),

                                TextEntry::make('issuedByAdminUser.username')
                                    ->label(__('filament.issued_by_admin'))
                                    ->placeholder('-'),

                                TextEntry::make('used_at')
                                    ->label(__('filament.used_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('expires_at')
                                    ->label(__('filament.expires_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('created_at')
                                    ->label(__('filament.created_at'))
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label(__('filament.updated_at'))
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make(__('filament.remark'))
                    ->schema([
                        TextEntry::make('remark')
                            ->placeholder('-'),
                    ]),

                Section::make(__('filament.meta'))
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('meta')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}