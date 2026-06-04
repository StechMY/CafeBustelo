<?php

namespace App\Filament\Resources\WithdrawRequests\Tables;

use App\Models\WithdrawRequest;
use App\Services\WithdrawService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class WithdrawRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('fee_amount')
                    ->label('Fee')
                    ->prefix(config('app.currency')." ")
                    ->toggleable(),

                TextColumn::make('net_amount')
                    ->label('Net')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('vip_level_snapshot')
                    ->label('VIP')
                    ->formatStateUsing(fn($state): string => 'LV' . $state)
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('bank_name')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('bank_account_name')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('bank_account_no')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('reviewer.username')
                    ->label('Reviewer')
                    ->placeholder('-'),

                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(WithdrawRequest $record): bool => $record->status === 'pending')
                    ->form([
                        Textarea::make('admin_remark')
                            ->label('Admin Remark')
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (WithdrawRequest $record, array $data): void {
                        try {
                            app(WithdrawService::class)->approve(
                                withdrawRequest: $record,
                                adminUser: Filament::auth()->user(),
                                adminRemark: $data['admin_remark'] ?? null,
                            );

                            Notification::make()
                                ->title('Withdraw approved')
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('Approve failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(WithdrawRequest $record): bool => $record->status === 'pending')
                    ->form([
                        Textarea::make('admin_remark')
                            ->label('Reject Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (WithdrawRequest $record, array $data): void {
                        try {
                            app(WithdrawService::class)->reject(
                                withdrawRequest: $record,
                                adminUser: Filament::auth()->user(),
                                adminRemark: $data['admin_remark'] ?? null,
                            );

                            Notification::make()
                                ->title('Withdraw rejected')
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('Reject failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(fn(WithdrawRequest $record): bool => $record->status !== 'approved'),

                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records): void {
                            $records
                                ->where('status', '!=', 'approved')
                                ->each
                                ->delete();
                        }),

                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}