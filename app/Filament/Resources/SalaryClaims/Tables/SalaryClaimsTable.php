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
                    ->label(__('filament.username'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user.phone')
                    ->label(__('filament.phone'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('type')
                    ->label(__('filament.type'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'weekly' => 'info',
                        'monthly' => 'success',
                        'annual' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('period_key')
                    ->label(__('filament.period_key'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('vip_level_snapshot')
                    ->label(__('filament.vip'))
                    ->formatStateUsing(fn ($state): string => __('filament.lv') . $state)
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('salary_amount')
                    ->label(__('filament.salary_amount'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('team_subsidy_amount')
                    ->label(__('filament.team_subsidy_amount'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label(__('filament.total_amount'))
                    ->prefix(config('app.currency')." ")
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('claimed_at')
                    ->label(__('filament.claimed_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('filament.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('claimed_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'weekly' => __('filament.weekly'),
                        'monthly' => __('filament.monthly'),
                        'annual' => __('filament.annual'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}