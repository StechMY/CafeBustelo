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
use Illuminate\Support\Facades\Gate;
use Throwable;

class WithdrawRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.username')
                    ->label(__('filament.username'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user.phone')
                    ->label(__('filament.phone'))
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->prefix(config('app.currency') . " ")
                    ->sortable(),

                TextColumn::make('fee_amount')
                    ->label(__('filament.fee'))
                    ->prefix(config('app.currency') . " ")
                    ->toggleable(),

                TextColumn::make('net_amount')
                    ->label(__('filament.net'))
                    ->prefix(config('app.currency') . " ")
                    ->sortable(),

                TextColumn::make('vip_level_snapshot')
                    ->label(__('filament.vip'))
                    ->formatStateUsing(fn($state): string => __('filament.lv') . $state)
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('bank_name')
                    ->label(__('filament.bank_name'))
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('bank_account_name')
                    ->label(__('filament.bank_account_name'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('bank_account_no')
                    ->label(__('filament.bank_account_no'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label(__('filament.submitted_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('reviewer.username')
                    ->label(__('filament.reviewer'))
                    ->placeholder('-'),

                TextColumn::make('reviewed_at')
                    ->label(__('filament.reviewed_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => __('filament.pending'),
                        'approved' => __('filament.approved'),
                        'rejected' => __('filament.rejected'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve')
                    ->label(__('filament.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(WithdrawRequest $record): bool => $record->status === 'pending')
                    ->authorize(fn(WithdrawRequest $record): bool => Gate::allows('audit', $record))
                    ->form([
                        Textarea::make('admin_remark')
                            ->label(__('filament.admin_remark'))
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (WithdrawRequest $record, array $data): void {
                        abort_unless(Gate::allows('audit', $record), 403);
                        try {
                            app(WithdrawService::class)->approve(
                                withdrawRequest: $record,
                                adminUser: Filament::auth()->user(),
                                adminRemark: $data['admin_remark'] ?? null,
                            );

                            Notification::make()
                                ->title(__('filament.withdraw_approved'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title(__('filament.approve_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('reject')
                    ->label(__('filament.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(WithdrawRequest $record): bool => $record->status === 'pending')
                    ->authorize(fn(WithdrawRequest $record): bool => Gate::allows('audit', $record))
                    ->form([
                        Textarea::make('admin_remark')
                            ->label(__('filament.reject_reason'))
                            ->required()
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (WithdrawRequest $record, array $data): void {
                        abort_unless(Gate::allows('audit', $record), 403);
                        try {
                            app(WithdrawService::class)->reject(
                                withdrawRequest: $record,
                                adminUser: Filament::auth()->user(),
                                adminRemark: $data['admin_remark'] ?? null,
                            );

                            Notification::make()
                                ->title(__('filament.withdraw_rejected'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title(__('filament.reject_failed'))
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