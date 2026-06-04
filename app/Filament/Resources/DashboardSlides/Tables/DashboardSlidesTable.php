<?php

namespace App\Filament\Resources\DashboardSlides\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class DashboardSlidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->disk('do_spaces')
                    ->height(60)
                    ->width(100),

                TextColumn::make('title')
                    ->searchable()
                    ->weight('bold'),

                ToggleColumn::make('is_enabled')
                    ->label('Enabled')
                    ->afterStateUpdated(function (): void {
                        Notification::make()
                            ->title('Slide status updated')
                            ->success()
                            ->send();
                    }),

                TextColumn::make('sort_order')
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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