<?php

namespace App\Filament\Resources\SalaryClaimWindows\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalaryClaimWindowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                IconColumn::make('is_enabled')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('day_of_week')
                    ->label('Day of Week')
                    ->formatStateUsing(fn ($state): string => match ((int) $state) {
                        0 => 'Sunday',
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                        default => '-',
                    })
                    ->placeholder('-'),

                TextColumn::make('day_of_month')
                    ->placeholder('-'),

                TextColumn::make('month')
                    ->placeholder('-'),

                TextColumn::make('day')
                    ->placeholder('-'),

                TextColumn::make('start_time')
                    ->time('H:i'),

                TextColumn::make('end_time')
                    ->time('H:i'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('type')
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