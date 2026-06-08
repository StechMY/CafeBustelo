<?php

namespace App\Filament\Resources\VipLevels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VipLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level')
                    ->label(__('filament.lv'))
                    ->formatStateUsing(fn ($state): string => __('filament.lv') . $state)
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label(__('filament.name'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('position_name')
                    ->label(__('filament.position_name'))
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('required_accumulated_recharge')
                    ->label(__('filament.required_recharge'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('required_active_team_size')
                    ->label(__('filament.team_size'))
                    ->sortable(),

                TextColumn::make('daily_check_in_amount')
                    ->label(__('filament.daily'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('weekly_salary')
                    ->label(__('filament.weekly'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('monthly_salary')
                    ->label(__('filament.monthly'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('team_subsidy')
                    ->label(__('filament.team_subsidy'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('annual_salary')
                    ->label(__('filament.annual'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->defaultSort('level')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}