<?php

namespace App\Filament\Resources\WithdrawRequests\Tables;

use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use App\Services\Settings\Qc168Settings;
use App\Services\WithdrawService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Throwable;

class WithdrawRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                /*
                |--------------------------------------------------------------------------
                | Customer Summary
                |--------------------------------------------------------------------------
                */
                TextColumn::make('user.username')
                    ->label(__('filament.username'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(
                        fn (WithdrawRequest $record): ?string =>
                            filled($record->user?->phone)
                                ? $record->user->phone
                                : null
                    ),

                /*
                |--------------------------------------------------------------------------
                | Money Summary
                |--------------------------------------------------------------------------
                |
                | Instead of:
                |
                | Amount | Fee | Net
                |
                | all three values are shown inside one compact column.
                |
                */
                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->prefix(config('app.currency').' ')
                    ->weight('bold')
                    ->sortable()
                    ->description(
                        fn (WithdrawRequest $record): string =>
                            self::moneySummary($record)
                    ),

                /*
                |--------------------------------------------------------------------------
                | Unified Status
                |--------------------------------------------------------------------------
                |
                | Previously:
                |
                | status
                | approval_mode
                | payout_status
                |
                | occupied three separate columns.
                |
                | The main table now combines them into one operational summary.
                |
                */
                TextColumn::make('withdrawal_summary_status')
                    ->label(__('filament.status'))
                    ->state(
                        fn (WithdrawRequest $record): string =>
                            self::statusLabel($record)
                    )
                    ->description(
                        fn (WithdrawRequest $record): ?string =>
                            self::statusDescription($record)
                    )
                    ->badge()
                    ->color(
                        fn (WithdrawRequest $record): string =>
                            self::statusColor($record)
                    )
                    ->wrap(),

                /*
                |--------------------------------------------------------------------------
                | Bank Summary
                |--------------------------------------------------------------------------
                |
                | Instead of:
                |
                | Bank
                | Bank Code
                | Account Name
                | Account Number
                |
                | the normal table displays one compact bank summary.
                |
                */
                TextColumn::make('bank_name')
                    ->label(__('filament.bank_name'))
                    ->searchable()
                    ->weight('medium')
                    ->placeholder('-')
                    ->description(
                        fn (WithdrawRequest $record): ?string =>
                            self::bankSummary($record)
                    )
                    ->wrap(),

                /*
                |--------------------------------------------------------------------------
                | Submitted At
                |--------------------------------------------------------------------------
                */
                TextColumn::make('created_at')
                    ->label(__('filament.submitted_at'))
                    ->dateTime()
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | Advanced Columns
                |--------------------------------------------------------------------------
                |
                | Available when Finance/Reconciliation needs them, but not shown
                | during normal daily review.
                |
                */
                TextColumn::make('fee_amount')
                    ->label(__('filament.fee'))
                    ->prefix(config('app.currency').' ')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('net_amount')
                    ->label(__('filament.net'))
                    ->prefix(config('app.currency').' ')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('vip_level_snapshot')
                    ->label(__('filament.vip'))
                    ->formatStateUsing(
                        fn ($state): string =>
                            __('filament.lv').$state
                    )
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approval_mode')
                    ->label(__('withdraw.approval_method'))
                    ->formatStateUsing(
                        fn (
                            ?string $state,
                            WithdrawRequest $record
                        ): string =>
                            $record->approvalModeLabel()
                    )
                    ->badge()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payout_status')
                    ->label(__('withdraw.payout_status'))
                    ->formatStateUsing(
                        fn (
                            ?string $state,
                            WithdrawRequest $record
                        ): string =>
                            $record->payoutStatusLabel()
                    )
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bank_code')
                    ->label(__('withdraw.bank_code'))
                    ->placeholder(
                        __('withdraw.missing_bank_code')
                    )
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bank_account_name')
                    ->label(__('filament.bank_account_name'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bank_account_no')
                    ->label(__('filament.bank_account_no'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewer.username')
                    ->label(__('filament.reviewer'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewed_at')
                    ->label(__('filament.reviewed_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gateway_release_at')
                    ->label(__('withdraw.gateway_release_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make(
                    'gateway_current_merchant_order_id'
                )
                    ->label(__('withdraw.merchant_order_id'))
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->query(function (
                        Builder $query,
                        array $data
                    ): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (
                                    Builder $query,
                                    $date
                                ) => $query->whereDate(
                                    'created_at',
                                    '>=',
                                    $date
                                )
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (
                                    Builder $query,
                                    $date
                                ) => $query->whereDate(
                                    'created_at',
                                    '<=',
                                    $date
                                )
                            );
                    }),
            ])

            /*
            |--------------------------------------------------------------------------
            | Record Actions
            |--------------------------------------------------------------------------
            |
            | View remains visible.
            |
            | All operational buttons are now inside one compact action menu:
            |
            | - Manual Approve
            | - Scheduled Gateway
            | - Instant Gateway
            | - Reject
            | - Retry
            | - Cancel
            | - Sync
            | - Delete / Restore
            |
            | This is the biggest improvement for horizontal table width.
            |
            */
            ->recordActions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),

                ActionGroup::make([
                    /*
                    |--------------------------------------------------------------------------
                    | Approve Manually
                    |--------------------------------------------------------------------------
                    */
                    Action::make('approve_manual')
                        ->label(__('withdraw.approve_manual'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->status === 'pending'
                        )
                        ->authorize(
                            fn (WithdrawRequest $record): bool =>
                                Gate::allows(
                                    'approveManual',
                                    $record
                                )
                        )
                        ->modalHeading(
                            __('withdraw.approve_manual_heading')
                        )
                        ->modalDescription(
                            __('withdraw.approve_manual_confirmation')
                        )
                        ->modalSubmitActionLabel(
                            __('withdraw.confirm_approve_manual')
                        )
                        ->form([
                            Textarea::make('admin_remark')
                                ->label(
                                    __('filament.admin_remark')
                                )
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->action(function (
                            WithdrawRequest $record,
                            array $data
                        ): void {
                            abort_unless(
                                Gate::allows(
                                    'approveManual',
                                    $record
                                ),
                                403
                            );

                            try {
                                app(
                                    WithdrawService::class
                                )->approveManually(
                                    withdrawRequest: $record,
                                    adminUser:
                                        Filament::auth()->user(),
                                    adminRemark:
                                        $data['admin_remark'] ?? null,
                                    source: 'filament',
                                    ip: request()->ip(),
                                );

                                Notification::make()
                                    ->title(
                                        __('withdraw.approved_manually')
                                    )
                                    ->success()
                                    ->send();
                            } catch (Throwable $exception) {
                                report($exception);

                                Notification::make()
                                    ->title(
                                        __('filament.approve_failed')
                                    )
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /*
                    |--------------------------------------------------------------------------
                    | Approve + Scheduled Gateway
                    |--------------------------------------------------------------------------
                    */
                    Action::make('approve_gateway_scheduled')
                        ->label(
                            fn (): string =>
                                __(
                                    'withdraw.approve_gateway_scheduled_delay',
                                    [
                                        'hours' => app(
                                            Qc168Settings::class
                                        )->withdrawReleaseHours(),
                                    ]
                                )
                        )
                        ->icon('heroicon-o-clock')
                        ->color('info')
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->status === 'pending'
                        )
                        ->disabled(
                            fn (WithdrawRequest $record): bool =>
                                ! app(
                                    Qc168Gateway::class
                                )->isWithdrawalAvailable()
                                || ! self::hasValidBankCode($record)
                        )
                        ->tooltip(
                            fn (
                                WithdrawRequest $record
                            ): ?string =>
                                ! app(
                                    Qc168Gateway::class
                                )->isWithdrawalAvailable()
                                    ? __('withdraw.gateway_unavailable')
                                    : (
                                        ! self::hasValidBankCode(
                                            $record
                                        )
                                            ? __(
                                                'withdraw.bank_not_supported'
                                            )
                                            : null
                                    )
                        )
                        ->authorize(
                            fn (WithdrawRequest $record): bool =>
                                Gate::allows(
                                    'approveScheduledGateway',
                                    $record
                                )
                        )
                        ->modalHeading(
                            __(
                                'withdraw.approve_gateway_scheduled_heading'
                            )
                        )
                        ->modalDescription(
                            fn (): string =>
                                __(
                                    'withdraw.approve_gateway_scheduled_confirmation',
                                    [
                                        'hours' => app(
                                            Qc168Settings::class
                                        )->withdrawReleaseHours(),
                                    ]
                                )
                        )
                        ->modalSubmitActionLabel(
                            __(
                                'withdraw.confirm_approve_gateway_scheduled'
                            )
                        )
                        ->form([
                            Textarea::make('admin_remark')
                                ->label(
                                    __('filament.admin_remark')
                                )
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->action(function (
                            WithdrawRequest $record,
                            array $data
                        ): void {
                            abort_unless(
                                Gate::allows(
                                    'approveScheduledGateway',
                                    $record
                                ),
                                403
                            );

                            try {
                                app(
                                    WithdrawService::class
                                )->approveWithScheduledGateway(
                                    withdrawRequest: $record,
                                    adminUser:
                                        Filament::auth()->user(),
                                    adminRemark:
                                        $data['admin_remark'] ?? null,
                                    source: 'filament',
                                    ip: request()->ip(),
                                );

                                Notification::make()
                                    ->title(
                                        __(
                                            'withdraw.approved_gateway_scheduled'
                                        )
                                    )
                                    ->success()
                                    ->send();
                            } catch (Throwable $exception) {
                                report($exception);

                                Notification::make()
                                    ->title(
                                        __('filament.approve_failed')
                                    )
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /*
                    |--------------------------------------------------------------------------
                    | Approve + Instant Gateway
                    |--------------------------------------------------------------------------
                    */
                    Action::make('approve_gateway_instant')
                        ->label(
                            __('withdraw.approve_gateway_instant')
                        )
                        ->icon('heroicon-o-bolt')
                        ->color('danger')
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->status === 'pending'
                        )
                        ->disabled(
                            fn (WithdrawRequest $record): bool =>
                                ! app(
                                    Qc168Gateway::class
                                )->isWithdrawalAvailable()
                                || ! self::hasValidBankCode($record)
                        )
                        ->tooltip(
                            fn (
                                WithdrawRequest $record
                            ): ?string =>
                                ! app(
                                    Qc168Gateway::class
                                )->isWithdrawalAvailable()
                                    ? __('withdraw.gateway_unavailable')
                                    : (
                                        ! self::hasValidBankCode(
                                            $record
                                        )
                                            ? __(
                                                'withdraw.bank_not_supported'
                                            )
                                            : null
                                    )
                        )
                        ->authorize(
                            fn (WithdrawRequest $record): bool =>
                                Gate::allows(
                                    'approveInstantGateway',
                                    $record
                                )
                        )
                        ->modalHeading(
                            __(
                                'withdraw.approve_gateway_instant_heading'
                            )
                        )
                        ->modalDescription(
                            __(
                                'withdraw.approve_gateway_instant_confirmation'
                            )
                        )
                        ->modalSubmitActionLabel(
                            __(
                                'withdraw.confirm_approve_gateway_instant'
                            )
                        )
                        ->modalIcon(
                            'heroicon-o-exclamation-triangle'
                        )
                        ->modalIconColor('danger')
                        ->form([
                            Textarea::make('admin_remark')
                                ->label(
                                    __('filament.admin_remark')
                                )
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->action(function (
                            WithdrawRequest $record,
                            array $data
                        ): void {
                            abort_unless(
                                Gate::allows(
                                    'approveInstantGateway',
                                    $record
                                ),
                                403
                            );

                            try {
                                app(
                                    WithdrawService::class
                                )->approveWithInstantGateway(
                                    withdrawRequest: $record,
                                    adminUser:
                                        Filament::auth()->user(),
                                    adminRemark:
                                        $data['admin_remark'] ?? null,
                                    source: 'filament',
                                    ip: request()->ip(),
                                );

                                Notification::make()
                                    ->title(
                                        __(
                                            'withdraw.approved_gateway_instant'
                                        )
                                    )
                                    ->success()
                                    ->send();
                            } catch (Throwable $exception) {
                                report($exception);

                                $freshRecord = $record->fresh();

                                if (
                                    $freshRecord
                                    && $freshRecord->isApproved()
                                    && $freshRecord->approval_mode
                                        === WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT
                                ) {
                                    Notification::make()
                                        ->title(
                                            __(
                                                'withdraw.instant_submission_failed_after_approval'
                                            )
                                        )
                                        ->body(
                                            __(
                                                'withdraw.instant_submission_failed_after_approval_help'
                                            )
                                        )
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                Notification::make()
                                    ->title(
                                        __('filament.approve_failed')
                                    )
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /*
                    |--------------------------------------------------------------------------
                    | Reject
                    |--------------------------------------------------------------------------
                    */
                    Action::make('reject')
                        ->label(__('filament.reject'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->status === 'pending'
                        )
                        ->authorize(
                            fn (WithdrawRequest $record): bool =>
                                Gate::allows('audit', $record)
                        )
                        ->form([
                            Textarea::make('admin_remark')
                                ->label(
                                    __('filament.reject_reason')
                                )
                                ->required()
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->action(function (
                            WithdrawRequest $record,
                            array $data
                        ): void {
                            abort_unless(
                                Gate::allows('audit', $record),
                                403
                            );

                            try {
                                app(
                                    WithdrawService::class
                                )->reject(
                                    withdrawRequest: $record,
                                    adminUser:
                                        Filament::auth()->user(),
                                    adminRemark:
                                        $data['admin_remark'] ?? null,
                                    source: 'filament',
                                    ip: request()->ip(),
                                );

                                Notification::make()
                                    ->title(
                                        __('filament.withdraw_rejected')
                                    )
                                    ->success()
                                    ->send();
                            } catch (Throwable $exception) {
                                report($exception);

                                Notification::make()
                                    ->title(
                                        __('filament.reject_failed')
                                    )
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /*
                    |--------------------------------------------------------------------------
                    | Retry Payout
                    |--------------------------------------------------------------------------
                    */
                    Action::make('retry_payout')
                        ->label(__('withdraw.retry_payout'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->canRetryPayout()
                        )
                        ->authorize(
                            fn (WithdrawRequest $record): bool =>
                                Gate::allows(
                                    'retryGateway',
                                    $record
                                )
                        )
                        ->form([
                            Textarea::make('reason')
                                ->label(
                                    __('withdraw.retry_reason')
                                )
                                ->required()
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->action(function (
                            WithdrawRequest $record,
                            array $data
                        ): void {
                            abort_unless(
                                Gate::allows(
                                    'retryGateway',
                                    $record
                                ),
                                403
                            );

                            try {
                                $attempt = app(
                                    Qc168WithdrawalPayoutService::class
                                )->retry(
                                    $record,
                                    Filament::auth()->user(),
                                    $data['reason'],
                                    request()->ip(),
                                );

                                Notification::make()
                                    ->title(
                                        $attempt
                                            ? __('withdraw.retry_submitted')
                                            : __('withdraw.already_completed')
                                    )
                                    ->success()
                                    ->send();
                            } catch (Throwable $exception) {
                                report($exception);

                                Notification::make()
                                    ->title(
                                        __('withdraw.retry_failed')
                                    )
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /*
                    |--------------------------------------------------------------------------
                    | Cancel Payout
                    |--------------------------------------------------------------------------
                    */
                    Action::make('cancel_payout')
                        ->label(__('withdraw.cancel_payout'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->canCancelPayout()
                        )
                        ->authorize(
                            fn (WithdrawRequest $record): bool =>
                                Gate::allows(
                                    'cancelGateway',
                                    $record
                                )
                        )
                        ->form([
                            Textarea::make('reason')
                                ->label(
                                    __('withdraw.cancellation_reason')
                                )
                                ->required()
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->action(function (
                            WithdrawRequest $record,
                            array $data
                        ): void {
                            abort_unless(
                                Gate::allows(
                                    'cancelGateway',
                                    $record
                                ),
                                403
                            );

                            try {
                                app(
                                    Qc168WithdrawalPayoutService::class
                                )->cancel(
                                    $record,
                                    Filament::auth()->user(),
                                    $data['reason'],
                                    request()->ip(),
                                );

                                Notification::make()
                                    ->title(
                                        __(
                                            'withdraw.cancelled_and_refunded'
                                        )
                                    )
                                    ->success()
                                    ->send();
                            } catch (Throwable $exception) {
                                report($exception);

                                Notification::make()
                                    ->title(
                                        __('withdraw.cancel_failed')
                                    )
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /*
                    |--------------------------------------------------------------------------
                    | Sync Gateway
                    |--------------------------------------------------------------------------
                    */
                    Action::make('sync_gateway')
                        ->label(__('withdraw.sync_gateway'))
                        ->icon(
                            'heroicon-o-arrow-path-rounded-square'
                        )
                        ->color('info')
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->canSyncPayout()
                        )
                        ->authorize(
                            fn (WithdrawRequest $record): bool =>
                                Gate::allows(
                                    'syncGateway',
                                    $record
                                )
                        )
                        ->action(function (
                            WithdrawRequest $record
                        ): void {
                            abort_unless(
                                Gate::allows(
                                    'syncGateway',
                                    $record
                                ),
                                403
                            );

                            try {
                                app(
                                    Qc168WithdrawalPayoutService::class
                                )->sync(
                                    $record,
                                    'filament',
                                    Filament::auth()->user(),
                                    ip: request()->ip(),
                                );

                                Notification::make()
                                    ->title(
                                        __('withdraw.sync_complete')
                                    )
                                    ->success()
                                    ->send();
                            } catch (Throwable $exception) {
                                report($exception);

                                Notification::make()
                                    ->title(
                                        __('withdraw.sync_failed')
                                    )
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    DeleteAction::make()
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->status !== 'approved'
                        ),

                    RestoreAction::make(),
                ])
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records): void {
                            $records
                                ->where(
                                    'status',
                                    '!=',
                                    'approved'
                                )
                                ->each
                                ->delete();
                        }),

                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Amount column second line.
     *
     * Example:
     *
     * Net RM 980.00 · Fee RM 20.00
     */
    private static function moneySummary(
        WithdrawRequest $record
    ): string {
        $currency = config('app.currency');

        return __('filament.net')
            .' '
            .$currency
            .' '
            .number_format(
                (float) $record->net_amount,
                2
            )
            .' · '
            .__('filament.fee')
            .' '
            .$currency
            .' '
            .number_format(
                (float) $record->fee_amount,
                2
            );
    }

    /**
     * Main visual status.
     */
    private static function statusLabel(
        WithdrawRequest $record
    ): string {
        if ($record->status === 'pending') {
            return __('filament.pending');
        }

        if ($record->status === 'rejected') {
            return __('filament.rejected');
        }

        if ($record->status !== 'approved') {
            return ucfirst(
                str_replace(
                    '_',
                    ' ',
                    (string) $record->status
                )
            );
        }

        if (filled($record->payout_status)) {
            return $record->payoutStatusLabel();
        }

        return __('filament.approved');
    }

    /**
     * Secondary operational information underneath the status.
     *
     * Examples:
     *
     * Direct Approval
     *
     * Scheduled Gateway · 27 Aug 2026 14:30
     *
     * Instant Gateway
     */
    private static function statusDescription(
        WithdrawRequest $record
    ): ?string {
        if ($record->status !== 'approved') {
            return null;
        }

        $parts = [];

        if (filled($record->approval_mode)) {
            $parts[] = $record->approvalModeLabel();
        }

        if (
            $record->approval_mode
                === WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED
            && filled($record->gateway_release_at)
        ) {
            $releaseAt = self::formatDateTime(
                $record->gateway_release_at
            );

            if ($releaseAt) {
                $parts[] = $releaseAt;
            }
        }

        return $parts !== []
            ? implode(' · ', $parts)
            : null;
    }

    private static function statusColor(
        WithdrawRequest $record
    ): string {
        if ($record->status === 'pending') {
            return 'warning';
        }

        if ($record->status === 'rejected') {
            return 'danger';
        }

        if ($record->status !== 'approved') {
            return 'gray';
        }

        return match ($record->payout_status) {
            WithdrawRequest::PAYOUT_COMPLETED => 'success',

            WithdrawRequest::PAYOUT_FAILED,
            WithdrawRequest::PAYOUT_REJECTED,
            WithdrawRequest::PAYOUT_OVERDUE,
            WithdrawRequest::PAYOUT_CREATE_FAILED,
            WithdrawRequest::PAYOUT_REFUND_REVIEW => 'danger',

            WithdrawRequest::PAYOUT_CANCELLED => 'gray',

            default => 'info',
        };
    }

    /**
     * Compact bank summary.
     *
     * Example:
     *
     * NGUYEN VAN A · ••••••6789
     */
    private static function bankSummary(
        WithdrawRequest $record
    ): ?string {
        $parts = [];

        if (filled($record->bank_account_name)) {
            $parts[] = $record->bank_account_name;
        }

        if (filled($record->bank_account_no)) {
            $parts[] = (
                $record->bank_account_no
            );
        }

        return $parts !== []
            ? implode(' · ', $parts)
            : null;
    }

    private static function hasValidBankCode(
        WithdrawRequest $record
    ): bool {
        return preg_match(
            '/^\d+$/',
            (string) $record->bank_code
        ) === 1;
    }

    private static function maskAccountNumber(
        string|int|null $accountNo
    ): string {
        $accountNo = trim((string) $accountNo);

        if ($accountNo === '') {
            return '-';
        }

        $length = strlen($accountNo);

        if ($length <= 4) {
            return $accountNo;
        }

        return str_repeat('•', $length - 4)
            .substr($accountNo, -4);
    }

    private static function formatDateTime(
        mixed $value
    ): ?string {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)
                ->format('d M Y H:i');
        } catch (Throwable) {
            return (string) $value;
        }
    }
}