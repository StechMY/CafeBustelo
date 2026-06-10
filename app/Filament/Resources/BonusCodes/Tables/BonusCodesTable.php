<?php

namespace App\Filament\Resources\BonusCodes\Tables;

use App\Models\BonusCode;
use App\Services\BonusCodeService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;
use App\Support\FilamentSupportScope;

class BonusCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('filament.code'))
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('owner.username')
                    ->label(__('filament.owner'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('owner.phone')
                    ->label(__('filament.phone'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('type')
                    ->label(__('filament.type'))
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'admin' => 'warning',
                        'invite' => 'info',
                        'upgrade' => 'success',
                        'compensation' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->prefix(config('app.currency') . " ")
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'unused' => 'warning',
                        'used' => 'success',
                        'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('claim.user.username')
                    ->label(__('filament.claimed_by'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('issuedByAdminUser.username')
                    ->label(__('filament.issued_by'))
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('used_at')
                    ->label(__('filament.used_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('expires_at')
                    ->label(__('filament.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('filament.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        BonusCode::STATUS_UNUSED => __('filament.unused'),
                        BonusCode::STATUS_USED => __('filament.used'),
                        BonusCode::STATUS_CANCELLED => __('filament.cancelled'),
                        BonusCode::STATUS_EXPIRED => __('filament.expired'),
                    ]),

                SelectFilter::make('type')
                    ->options([
                        BonusCode::TYPE_ADMIN => __('filament.admin'),
                        BonusCode::TYPE_INVITE => __('filament.invite'),
                        BonusCode::TYPE_UPGRADE => __('filament.upgrade'),
                        BonusCode::TYPE_COMPENSATION => __('filament.compensation'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn(BonusCode $record): bool => !$record->isUsed()
                        && FilamentSupportScope::canAccessBonusCode($record)),

                Action::make('cancel')
                    ->label(__('filament.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(BonusCode $record): bool => $record->status === BonusCode::STATUS_UNUSED
                        && FilamentSupportScope::canAccessBonusCode($record))
                    ->form([
                        Textarea::make('remark')
                            ->label(__('filament.cancel_remark'))
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (BonusCode $record, array $data): void {
                        try {
                            app(BonusCodeService::class)->cancel(
                                bonusCode: $record,
                                remark: $data['remark'] ?? null,
                            );

                            Notification::make()
                                ->title(__('filament.bonus_code_cancelled'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title(__('filament.cancel_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(fn(BonusCode $record): bool => !$record->isUsed()
                        && FilamentSupportScope::canAccessBonusCode($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records): void {
                            $records
                                ->filter(fn(BonusCode $record): bool => !$record->isUsed()
                                    && FilamentSupportScope::canAccessBonusCode($record))
                                ->each
                                ->delete();
                        }),
                ]),
            ]);
    }
}