<?php

namespace App\Filament\Resources\SalaryClaims\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalaryClaimInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Claim Detail')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.username')
                                    ->label('Username')
                                    ->copyable()
                                    ->weight('bold'),

                                TextEntry::make('user.name')
                                    ->label('Name')
                                    ->placeholder('-'),

                                TextEntry::make('type')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'weekly' => 'info',
                                        'monthly' => 'success',
                                        'annual' => 'warning',
                                        default => 'gray',
                                    }),

                                TextEntry::make('period_key'),

                                TextEntry::make('vip_level_snapshot')
                                    ->label('VIP Snapshot')
                                    ->formatStateUsing(fn ($state): string => 'LV' . $state),

                                TextEntry::make('claimed_at')
                                    ->dateTime(),

                                TextEntry::make('accumulated_recharge_snapshot')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('active_team_size_snapshot'),

                                TextEntry::make('salary_amount')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('team_subsidy_amount')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('total_amount')
                                    ->prefix(config('app.currency')." ")
                                    ->weight('bold'),
                            ]),
                    ]),

                Section::make('Audit')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('ip_address')
                                    ->placeholder('-'),

                                TextEntry::make('user_agent')
                                    ->placeholder('-'),
                            ]),
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