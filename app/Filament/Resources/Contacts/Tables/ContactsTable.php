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
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
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
                    ->limit(40)
                    ->copyable()
                    ->searchable()
                    ->url(fn ($record): string => $record->link, true),

                ToggleColumn::make('status')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('gray')
                    ->getStateUsing(fn ($record): bool => $record->status === 'active')
                    ->updateStateUsing(function ($record, bool $state): void {
                        $record->update([
                            'status' => $state ? 'active' : 'inactive',
                        ]);

                        Notification::make()
                            ->title('Contact status updated')
                            ->success()
                            ->send();
                    }),

                TextColumn::make('sort_order')
                    ->sortable(),

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