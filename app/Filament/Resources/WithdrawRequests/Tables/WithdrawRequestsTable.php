<?php

namespace App\Filament\Resources\WithdrawRequests\Tables;

use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use App\Services\Settings\Qc168Settings;
use App\Services\WithdrawService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                    ->prefix(config('app.currency').' ')
                    ->sortable(),

                TextColumn::make('fee_amount')
                    ->label(__('filament.fee'))
                    ->prefix(config('app.currency').' ')
                    ->toggleable(),

                TextColumn::make('net_amount')
                    ->label(__('filament.net'))
                    ->prefix(config('app.currency').' ')
                    ->sortable(),

                TextColumn::make('vip_level_snapshot')
                    ->label(__('filament.vip'))
                    ->formatStateUsing(fn ($state): string => __('filament.lv').$state)
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('approval_mode')
                    ->label(__('withdraw.approval_method'))
                    ->formatStateUsing(fn (?string $state, WithdrawRequest $record): string => $record->approvalModeLabel())
                    ->badge()
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('payout_status')
                    ->label(__('withdraw.payout_status'))
                    ->formatStateUsing(fn (?string $state, WithdrawRequest $record): string => $record->payoutStatusLabel())
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        WithdrawRequest::PAYOUT_COMPLETED => 'success',
                        WithdrawRequest::PAYOUT_FAILED,
                        WithdrawRequest::PAYOUT_REJECTED,
                        WithdrawRequest::PAYOUT_OVERDUE,
                        WithdrawRequest::PAYOUT_CREATE_FAILED,
                        WithdrawRequest::PAYOUT_REFUND_REVIEW => 'danger',
                        WithdrawRequest::PAYOUT_CANCELLED => 'gray',
                        default => 'warning',
                    })
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('bank_name')
                    ->label(__('filament.bank_name'))
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('bank_code')
                    ->label(__('withdraw.bank_code'))
                    ->placeholder(__('withdraw.missing_bank_code'))
                    ->toggleable(),

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

                TextColumn::make('gateway_release_at')
                    ->label(__('withdraw.gateway_release_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('gateway_current_merchant_order_id')
                    ->label(__('withdraw.merchant_order_id'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
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

                Filter::make('created_at')
                    ->label(__('filament.submitted_at'))
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Created from'),

                        DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve_manual')
                    ->label(__('withdraw.approve_manual'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                    ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('approveManual', $record))
                    ->modalHeading(__('withdraw.approve_manual_heading'))
                    ->modalDescription(__('withdraw.approve_manual_confirmation'))
                    ->modalSubmitActionLabel(__('withdraw.confirm_approve_manual'))
                    ->form([
                        Textarea::make('admin_remark')
                            ->label(__('filament.admin_remark'))
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (WithdrawRequest $record, array $data): void {
                        abort_unless(Gate::allows('approveManual', $record), 403);
                        try {
                            app(WithdrawService::class)->approveManually(
                                withdrawRequest: $record,
                                adminUser: Filament::auth()->user(),
                                adminRemark: $data['admin_remark'] ?? null,
                                source: 'filament',
                                ip: request()->ip(),
                            );

                            Notification::make()
                                ->title(__('withdraw.approved_manually'))
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

                Action::make('approve_gateway_scheduled')
                    ->label(fn (): string => __('withdraw.approve_gateway_scheduled_delay', [
                        'hours' => app(Qc168Settings::class)->withdrawReleaseHours(),
                    ]))
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                    ->disabled(fn (WithdrawRequest $record): bool => ! app(Qc168Gateway::class)->isWithdrawalAvailable()
                        || ! preg_match('/^\d+$/', (string) $record->bank_code))
                    ->tooltip(fn (WithdrawRequest $record): ?string => ! app(Qc168Gateway::class)->isWithdrawalAvailable()
                        ? __('withdraw.gateway_unavailable')
                        : (! preg_match('/^\d+$/', (string) $record->bank_code)
                            ? __('withdraw.bank_not_supported')
                            : null))
                    ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('approveScheduledGateway', $record))
                    ->modalHeading(__('withdraw.approve_gateway_scheduled_heading'))
                    ->modalDescription(fn (): string => __('withdraw.approve_gateway_scheduled_confirmation', [
                        'hours' => app(Qc168Settings::class)->withdrawReleaseHours(),
                    ]))
                    ->modalSubmitActionLabel(__('withdraw.confirm_approve_gateway_scheduled'))
                    ->form([
                        Textarea::make('admin_remark')
                            ->label(__('filament.admin_remark'))
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (WithdrawRequest $record, array $data): void {
                        abort_unless(Gate::allows('approveScheduledGateway', $record), 403);

                        try {
                            app(WithdrawService::class)->approveWithScheduledGateway(
                                withdrawRequest: $record,
                                adminUser: Filament::auth()->user(),
                                adminRemark: $data['admin_remark'] ?? null,
                                source: 'filament',
                                ip: request()->ip(),
                            );

                            Notification::make()
                                ->title(__('withdraw.approved_gateway_scheduled'))
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

                Action::make('approve_gateway_instant')
                    ->label(__('withdraw.approve_gateway_instant'))
                    ->icon('heroicon-o-bolt')
                    ->color('danger')
                    ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                    ->disabled(fn (WithdrawRequest $record): bool => ! app(Qc168Gateway::class)->isWithdrawalAvailable()
                        || ! preg_match('/^\d+$/', (string) $record->bank_code))
                    ->tooltip(fn (WithdrawRequest $record): ?string => ! app(Qc168Gateway::class)->isWithdrawalAvailable()
                        ? __('withdraw.gateway_unavailable')
                        : (! preg_match('/^\d+$/', (string) $record->bank_code)
                            ? __('withdraw.bank_not_supported')
                            : null))
                    ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('approveInstantGateway', $record))
                    ->modalHeading(__('withdraw.approve_gateway_instant_heading'))
                    ->modalDescription(__('withdraw.approve_gateway_instant_confirmation'))
                    ->modalSubmitActionLabel(__('withdraw.confirm_approve_gateway_instant'))
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalIconColor('danger')
                    ->form([
                        Textarea::make('admin_remark')
                            ->label(__('filament.admin_remark'))
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (WithdrawRequest $record, array $data): void {
                        abort_unless(Gate::allows('approveInstantGateway', $record), 403);

                        try {
                            app(WithdrawService::class)->approveWithInstantGateway(
                                withdrawRequest: $record,
                                adminUser: Filament::auth()->user(),
                                adminRemark: $data['admin_remark'] ?? null,
                                source: 'filament',
                                ip: request()->ip(),
                            );

                            Notification::make()
                                ->title(__('withdraw.approved_gateway_instant'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            $freshRecord = $record->fresh();

                            if ($freshRecord->isApproved()
                                && $freshRecord->approval_mode === WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT) {
                                Notification::make()
                                    ->title(__('withdraw.instant_submission_failed_after_approval'))
                                    ->body(__('withdraw.instant_submission_failed_after_approval_help'))
                                    ->warning()
                                    ->send();

                                return;
                            }

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
                    ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                    ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('audit', $record))
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
                                source: 'filament',
                                ip: request()->ip(),
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

                Action::make('retry_payout')
                    ->label(__('withdraw.retry_payout'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (WithdrawRequest $record): bool => $record->canRetryPayout())
                    ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('retryGateway', $record))
                    ->form([
                        Textarea::make('reason')
                            ->label(__('withdraw.retry_reason'))
                            ->required()
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (WithdrawRequest $record, array $data): void {
                        abort_unless(Gate::allows('retryGateway', $record), 403);

                        try {
                            $attempt = app(Qc168WithdrawalPayoutService::class)->retry(
                                $record,
                                Filament::auth()->user(),
                                $data['reason'],
                                request()->ip(),
                            );
                            Notification::make()
                                ->title($attempt ? __('withdraw.retry_submitted') : __('withdraw.already_completed'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()
                                ->title(__('withdraw.retry_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel_payout')
                    ->label(__('withdraw.cancel_payout'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (WithdrawRequest $record): bool => $record->canCancelPayout())
                    ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('cancelGateway', $record))
                    ->form([
                        Textarea::make('reason')
                            ->label(__('withdraw.cancellation_reason'))
                            ->required()
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (WithdrawRequest $record, array $data): void {
                        abort_unless(Gate::allows('cancelGateway', $record), 403);

                        try {
                            app(Qc168WithdrawalPayoutService::class)->cancel(
                                $record,
                                Filament::auth()->user(),
                                $data['reason'],
                                request()->ip(),
                            );
                            Notification::make()
                                ->title(__('withdraw.cancelled_and_refunded'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()
                                ->title(__('withdraw.cancel_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('sync_gateway')
                    ->label(__('withdraw.sync_gateway'))
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('info')
                    ->visible(fn (WithdrawRequest $record): bool => $record->canSyncPayout())
                    ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('syncGateway', $record))
                    ->action(function (WithdrawRequest $record): void {
                        abort_unless(Gate::allows('syncGateway', $record), 403);

                        try {
                            app(Qc168WithdrawalPayoutService::class)->sync(
                                $record,
                                'filament',
                                Filament::auth()->user(),
                                ip: request()->ip(),
                            );
                            Notification::make()
                                ->title(__('withdraw.sync_complete'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()
                                ->title(__('withdraw.sync_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(fn (WithdrawRequest $record): bool => $record->status !== 'approved'),

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
