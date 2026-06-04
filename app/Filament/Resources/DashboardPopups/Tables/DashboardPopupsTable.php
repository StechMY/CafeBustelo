<?php

namespace App\Filament\Resources\DashboardPopups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class DashboardPopupsTable
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
                            ->title('Popup status updated')
                            ->success()
                            ->send();
                    }),

                IconColumn::make('show_every_visit')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->sortable(),

                TextColumn::make('updated_at')
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