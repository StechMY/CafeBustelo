<?php

namespace App\Filament\Resources\DashboardMarquees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class DashboardMarqueesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.title'))
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('message')
                    ->label(__('filament.message'))
                    ->limit(60)
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('filament.type'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'warning' => 'warning',
                        'danger' => 'danger',
                        default => 'info',
                    }),

                ToggleColumn::make('is_enabled')
                    ->label(__('filament.enabled'))
                    ->afterStateUpdated(function (): void {
                        Notification::make()
                            ->title(__('filament.marquee_status_updated'))
                            ->success()
                            ->send();
                    }),

                TextColumn::make('sort_order')
                    ->label(__('filament.sort_order'))
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('filament.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
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