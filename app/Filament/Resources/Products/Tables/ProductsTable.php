<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('filament.image'))
                    ->disk('do_spaces')
                    ->square()
                    ->size(56),

                TextColumn::make('name')
                    ->label(__('filament.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Product $record): string => $record->code),

                TextColumn::make('price')
                    ->label(__('filament.price'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('daily_income')
                    ->label(__('filament.daily'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('duration_days')
                    ->label(__('filament.days'))
                    ->suffix(fn (): string => ' ' . __('filament.days'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_income')
                    ->label(__('filament.total_income'))
                    ->state(fn (Product $record): float => (float) $record->daily_income * (int) $record->duration_days)
                    ->prefix(config('app.currency')." "),

                TextColumn::make('upgrade_bonus')
                    ->label(__('filament.upgrade_bonus'))
                    ->prefix(config('app.currency')." ")
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('invite_bonus')
                    ->label(__('filament.invite_bonus'))
                    ->prefix(config('app.currency')." ")
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        default => 'warning',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label(__('filament.sort'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('filament.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament.updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label(__('filament.deleted'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('price')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => __('filament.active'),
                        'inactive' => __('filament.inactive'),
                    ]),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
