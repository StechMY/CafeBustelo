<?php

namespace App\Filament\Resources\RechargeMethods\Tables;

use App\Models\RechargeMethod;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RechargeMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('qr_image_path')
                    ->label(__('filament.qr_image'))
                    ->disk(fn (RechargeMethod $record): string => $record->qr_image_disk ?: 'do_spaces')
                    ->height(56)
                    ->width(56)
                    ->square(),

                TextColumn::make('name')
                    ->label(__('filament.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('bank_name')
                    ->label(__('filament.bank_name'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('account_name')
                    ->label(__('filament.account_name'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('account_no')
                    ->label(__('filament.account_no'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RechargeMethod::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        RechargeMethod::STATUS_ACTIVE => 'success',
                        RechargeMethod::STATUS_INACTIVE => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label(__('filament.sort_order'))
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('filament.updated_at'))
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament.status'))
                    ->options(RechargeMethod::statusOptions())
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}