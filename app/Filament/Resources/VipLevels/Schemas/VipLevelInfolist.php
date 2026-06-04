<?php

namespace App\Filament\Resources\VipLevels\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VipLevelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('VIP Level')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('level')
                                    ->label('Level')
                                    ->formatStateUsing(fn ($state): string => 'LV' . $state)
                                    ->weight('bold'),

                                TextEntry::make('name'),

                                TextEntry::make('position_name')
                                    ->placeholder('-'),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),

                                TextEntry::make('required_accumulated_recharge')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('required_active_team_size'),
                            ]),
                    ]),

                Section::make('Benefits')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('daily_check_in_amount')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('weekly_salary')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('monthly_salary')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('team_subsidy')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('annual_salary')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('min_withdraw_amount')
                                    ->prefix(config('app.currency')." "),
                            ]),
                    ]),
            ]);
    }
}