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
                Section::make('Bonus Code')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Code')
                                    ->weight('bold')
                                    ->copyable(),

                                TextEntry::make('owner.username')
                                    ->label('Owner')
                                    ->copyable(),

                                TextEntry::make('amount')
                                    ->label('Amount')
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
                                    ->label('Claimed By')
                                    ->placeholder('-')
                                    ->copyable(),

                                TextEntry::make('issuedByAdminUser.username')
                                    ->label('Issued By Admin')
                                    ->placeholder('-'),

                                TextEntry::make('used_at')
                                    ->label('Used At')
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('expires_at')
                                    ->label('Expires At')
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Updated At')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Remark')
                    ->schema([
                        TextEntry::make('remark')
                            ->placeholder('-'),
                    ]),

                Section::make('Meta')
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('meta')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}