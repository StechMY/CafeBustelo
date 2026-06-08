<?php

namespace App\Filament\Resources\Contacts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label(__('filament.type'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'telegram' => 'info',
                        'email' => 'warning',
                        'phone' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('link')
                    ->label(__('filament.link'))
                    ->limit(40)
                    ->copyable()
                    ->searchable()
                    ->url(fn ($record): string => $record->link, true),

                ToggleColumn::make('status')
                    ->label(__('filament.active'))
                    ->onColor('success')
                    ->offColor('gray')
                    ->getStateUsing(fn ($record): bool => $record->status === 'active')
                    ->updateStateUsing(function ($record, bool $state): void {
                        $record->update([
                            'status' => $state ? 'active' : 'inactive',
                        ]);

                        Notification::make()
                            ->title(__('filament.contact_status_updated'))
                            ->success()
                            ->send();
                    }),

                TextColumn::make('sort_order')
                    ->label(__('filament.sort_order'))
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('filament.updated_at'))
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