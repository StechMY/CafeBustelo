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
                    ->label('LV')
                    ->formatStateUsing(fn ($state): string => 'LV' . $state)
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('position_name')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('required_accumulated_recharge')
                    ->label('Required Recharge')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('required_active_team_size')
                    ->label('Team Size')
                    ->sortable(),

                TextColumn::make('daily_check_in_amount')
                    ->label('Daily')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('weekly_salary')
                    ->label('Weekly')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('monthly_salary')
                    ->label('Monthly')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('team_subsidy')
                    ->label('Team Subsidy')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('annual_salary')
                    ->label('Annual')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('status')
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