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
                Section::make(__('filament.claim_detail'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.username')
                                    ->label(__('filament.username'))
                                    ->copyable()
                                    ->weight('bold'),

                                TextEntry::make('user.name')
                                    ->label(__('filament.name'))
                                    ->placeholder('-'),

                                TextEntry::make('type')
                                    ->label(__('filament.type'))
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'weekly' => 'info',
                                        'monthly' => 'success',
                                        'annual' => 'warning',
                                        default => 'gray',
                                    }),

                                TextEntry::make('period_key')
                                    ->label(__('filament.period_key')),

                                TextEntry::make('vip_level_snapshot')
                                    ->label(__('filament.vip_snapshot'))
                                    ->formatStateUsing(fn ($state): string => __('filament.lv') . $state),

                                TextEntry::make('claimed_at')
                                    ->label(__('filament.claimed_at'))
                                    ->dateTime(),

                                TextEntry::make('accumulated_recharge_snapshot')
                                    ->label(__('filament.accumulated_recharge_snapshot'))
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('active_team_size_snapshot')
                                    ->label(__('filament.active_team_size_snapshot')),

                                TextEntry::make('salary_amount')
                                    ->label(__('filament.salary_amount'))
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('team_subsidy_amount')
                                    ->label(__('filament.team_subsidy_amount'))
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('total_amount')
                                    ->label(__('filament.total_amount'))
                                    ->prefix(config('app.currency')." ")
                                    ->weight('bold'),
                            ]),
                    ]),

                Section::make(__('filament.audit'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('ip_address')
                                    ->label(__('filament.ip_address'))
                                    ->placeholder('-'),

                                TextEntry::make('user_agent')
                                    ->label(__('filament.user_agent'))
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make(__('filament.meta'))
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('meta')
                            ->label(__('filament.meta'))
                            ->placeholder('-'),
                    ]),
            ]);
    }
}