<?php

namespace App\Filament\Resources\CommissionLevels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CommissionLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level')
                    ->label(__('filament.level'))
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label(__('filament.name'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('rate_percent')
                    ->label(__('filament.rate'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . '%')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label(__('filament.active'))
                    ->afterStateUpdated(function (): void {
                        Notification::make()
                            ->title(__('filament.commission_level_status_updated'))
                            ->success()
                            ->send();
                    }),

                TextColumn::make('sort_order')
                    ->label(__('filament.sort_order'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label(__('filament.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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