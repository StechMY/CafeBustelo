<?php

namespace App\Filament\Resources\AdminUsers\Tables;

use App\Models\AdminUser;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('username')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label(__('filament.roles'))
                    ->badge()
                    ->separator(',')
                    ->searchable(),

                ToggleColumn::make('status')
                    ->label(__('filament.active'))
                    ->getStateUsing(fn (AdminUser $record): bool => $record->status === 'active')
                    ->updateStateUsing(function (AdminUser $record, bool $state): void {
                        if ($record->hasRole('super_admin') && ! auth('admin')->user()?->hasRole('super_admin')) {
                            Notification::make()
                                ->title(__('filament.you_cannot_update_super_admin_status'))
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->id === auth('admin')->id() && ! $state) {
                            Notification::make()
                                ->title(__('filament.you_cannot_disable_your_own_account'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => $state ? 'active' : 'inactive',
                        ]);

                        Notification::make()
                            ->title(__('filament.admin_status_updated'))
                            ->success()
                            ->send();
                    }),

                TextColumn::make('last_login_ip')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('last_login_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(function (AdminUser $record): bool {
                        if (auth('admin')->user()?->hasRole('super_admin')) {
                            return true;
                        }

                        return ! $record->hasRole('super_admin');
                    }),

                DeleteAction::make()
                    ->visible(function (AdminUser $record): bool {
                        if ($record->id === auth('admin')->id()) {
                            return false;
                        }

                        if ($record->hasRole('super_admin')) {
                            return false;
                        }

                        return auth('admin')->user()?->hasRole('super_admin') ?? false;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth('admin')->user()?->hasRole('super_admin') ?? false),
                ]),
            ]);
    }
}