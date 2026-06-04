<?php

namespace App\Filament\Resources\SalaryClaims\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalaryClaimsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'weekly' => 'info',
                        'monthly' => 'success',
                        'annual' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('period_key')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('vip_level_snapshot')
                    ->label('VIP')
                    ->formatStateUsing(fn ($state): string => 'LV' . $state)
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('salary_amount')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('team_subsidy_amount')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->prefix(config('app.currency')." ")
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('claimed_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('claimed_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'annual' => 'Annual',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}