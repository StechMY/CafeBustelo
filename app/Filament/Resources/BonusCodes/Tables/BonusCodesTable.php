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

class BonusCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('owner.username')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('owner.phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'admin' => 'warning',
                        'invite' => 'info',
                        'upgrade' => 'success',
                        'compensation' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('amount')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'unused' => 'warning',
                        'used' => 'success',
                        'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('claim.user.username')
                    ->label('Claimed By')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('issuedByAdminUser.username')
                    ->label('Issued By')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('used_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('expires_at')
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
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        BonusCode::STATUS_UNUSED => 'Unused',
                        BonusCode::STATUS_USED => 'Used',
                        BonusCode::STATUS_CANCELLED => 'Cancelled',
                        BonusCode::STATUS_EXPIRED => 'Expired',
                    ]),

                SelectFilter::make('type')
                    ->options([
                        BonusCode::TYPE_ADMIN => 'Admin',
                        BonusCode::TYPE_INVITE => 'Invite',
                        BonusCode::TYPE_UPGRADE => 'Upgrade',
                        BonusCode::TYPE_COMPENSATION => 'Compensation',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn (BonusCode $record): bool => ! $record->isUsed()),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BonusCode $record): bool => $record->status === BonusCode::STATUS_UNUSED)
                    ->form([
                        Textarea::make('remark')
                            ->label('Cancel Remark')
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
                                ->title('Bonus code cancelled')
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('Cancel failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(fn (BonusCode $record): bool => ! $record->isUsed()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records): void {
                            $records
                                ->filter(fn (BonusCode $record): bool => ! $record->isUsed())
                                ->each
                                ->delete();
                        }),
                ]),
            ]);
    }
}