<?php

namespace App\Filament\Resources\WithdrawRequests\Tables;

use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use App\Services\Settings\Qc168Settings;
use App\Services\WithdrawService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
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
use Illuminate\Support\Str;
use Throwable;

class WithdrawRequestsTable
{
    public static function configure(Table $table): Table
    {
        $withdrawalAvailable = app(Qc168Gateway::class)->isWithdrawalAvailable();
        $withdrawReleaseHours = app(Qc168Settings::class)->withdrawReleaseHours();

        return $table
            ->columns([
                /*
                |--------------------------------------------------------------------------
                | Customer
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
                | Amount
                |--------------------------------------------------------------------------
                |
                | Requested amount as main value.
                | Net + fee in description.
                |
                */
                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: '.', thousandsSeparator: ',')
                    ->prefix(config('app.currency').' ')
                    ->weight('bold')
                    ->sortable()
                    ->description(
                        fn (WithdrawRequest $record): string =>
                            self::moneySummary($record)
                    ),

                /*
                |--------------------------------------------------------------------------
                | Unified Operational Status
                |--------------------------------------------------------------------------
                |
                | This is now the main operational column.
                |
                | Examples:
                |
                | Pending
                |
                | Waiting for Payout
                | Scheduled Gateway · 30 Aug 2026 15:40
                |
                | Status Needs Confirmation
                | Scheduled Gateway · ⚠ Gateway request timed out.
                |
                | Failed
                | ⚠ Provider rejected payout.
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
                    ->tooltip(
                        fn (WithdrawRequest $record): ?string =>
                            self::gatewayTechnicalTooltip($record)
                    )
                    ->wrap(),

                /*
                |--------------------------------------------------------------------------
                | Bank Summary
                |--------------------------------------------------------------------------
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
                | Hidden by default.
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

                /*
                |--------------------------------------------------------------------------
                | Failure reason
                |--------------------------------------------------------------------------
                |
                | Available for reconciliation, but normal staff sees the friendly
                | version inside the unified Status column.
                |
                */
                TextColumn::make('gateway_fail_reason')
                    ->label(
                        self::ui(
                            'withdraw.latest_gateway_error',
                            'Latest Gateway Error',
                            '最近一次网关错误'
                        )
                    )
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            filled($state)
                                ? Str::limit($state, 140)
                                : '-'
                    )
                    ->wrap()
                    ->tooltip(
                        fn (?string $state): ?string =>
                            filled($state)
                                ? Str::limit($state, 500)
                                : null
                    )
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

                TextColumn::make('gateway_current_merchant_order_id')
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

                /*
                |--------------------------------------------------------------------------
                | Needs Attention Filter
                |--------------------------------------------------------------------------
                |
                | Makes it easy for finance staff to see:
                |
                | - failed
                | - rejected
                | - overdue
                | - create failed
                | - refund review
                | - unknown / uncertain submission state
                |
                */
                Filter::make('gateway_attention')
                    ->label(
                        self::ui(
                            'withdraw.needs_attention',
                            'Needs Attention',
                            '需要处理'
                        )
                    )
                    ->query(function (Builder $query): Builder {
                        return $query
                            ->where('status', 'approved')
                            ->where(function (Builder $query): void {
                                $query
                                    ->whereIn('payout_status', [
                                        WithdrawRequest::PAYOUT_FAILED,
                                        WithdrawRequest::PAYOUT_REJECTED,
                                        WithdrawRequest::PAYOUT_OVERDUE,
                                        WithdrawRequest::PAYOUT_CREATE_FAILED,
                                        WithdrawRequest::PAYOUT_REFUND_REVIEW,
                                    ])
                                    ->orWhere(
                                        'gateway_payment_status',
                                        WithdrawalPayoutAttempt::STATUS_CREATE_PENDING
                                    );
                            })
                            ->whereNotIn('payout_status', [
                                WithdrawRequest::PAYOUT_COMPLETED,
                                WithdrawRequest::PAYOUT_CANCELLED,
                            ]);
                    }),

                Filter::make('gateway_warning')
                    ->label(
                        self::ui(
                            'withdraw.gateway_warning',
                            'Gateway Warning',
                            '网关警告'
                        )
                    )
                    ->query(
                        fn (Builder $query): Builder => $query
                            ->where('status', WithdrawRequest::STATUS_APPROVED)
                            ->whereIn('gateway_payment_status', [
                                WithdrawalPayoutAttempt::STATUS_NONE,
                                WithdrawalPayoutAttempt::STATUS_PENDING,
                                WithdrawalPayoutAttempt::STATUS_PREPARING,
                                WithdrawalPayoutAttempt::STATUS_PROCESSING,
                            ])
                            ->whereNotNull('gateway_fail_reason')
                            ->where('gateway_fail_reason', '!=', '')
                            ->whereNotIn('payout_status', [
                                WithdrawRequest::PAYOUT_COMPLETED,
                                WithdrawRequest::PAYOUT_CANCELLED,
                            ])
                    ),

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

            ->recordActions([
                /*
                |--------------------------------------------------------------------------
                | View
                |--------------------------------------------------------------------------
                */
                ViewAction::make()
                    ->icon('heroicon-o-eye'),

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT: Direct Sync Button
                |--------------------------------------------------------------------------
                |
                | For timeout / communication uncertainty we surface Sync outside
                | the ... menu.
                |
                | Admin should sync first instead of blindly Retry.
                |
                */
                Action::make('sync_gateway_attention')
                    ->label(
                        self::ui(
                            'withdraw.sync_gateway',
                            'Sync Status',
                            '同步状态'
                        )
                    )
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(
                        fn (WithdrawRequest $record): bool =>
                            (
                                self::hasUnknownGatewayStatus($record)
                                || self::hasGatewayWarning($record)
                            )
                            && $record->canSyncPayout()
                    )
                    ->authorize(
                        fn (WithdrawRequest $record): bool =>
                            Gate::allows(
                                'syncGateway',
                                $record
                            )
                    )
                    ->tooltip(
                        self::ui(
                            'withdraw.sync_before_retry',
                            'Gateway response was uncertain. Sync before retrying.',
                            '网关响应状态不确定，请先同步状态再决定是否重试。'
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

                /*
                |--------------------------------------------------------------------------
                | Compact Action Menu
                |--------------------------------------------------------------------------
                */
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
                    | Scheduled Gateway Approval
                    |--------------------------------------------------------------------------
                    */
                    Action::make('approve_gateway_scheduled')
                        ->label(
                            fn (): string =>
                                __(
                                    'withdraw.approve_gateway_scheduled_delay',
                                    [
                                        'hours' => $withdrawReleaseHours,
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
                                ! $withdrawalAvailable
                                || ! self::hasValidBankCode($record)
                        )
                        ->tooltip(
                            fn (
                                WithdrawRequest $record
                            ): ?string =>
                                ! $withdrawalAvailable
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
                                        'hours' => $withdrawReleaseHours,
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
                    | Instant Gateway Approval
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
                                ! $withdrawalAvailable
                                || ! self::hasValidBankCode($record)
                        )
                        ->tooltip(
                            fn (
                                WithdrawRequest $record
                            ): ?string =>
                                ! $withdrawalAvailable
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
                    | Retry
                    |--------------------------------------------------------------------------
                    */
                    Action::make('retry_payout')
                        ->label(__('withdraw.retry_payout'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->canRetryPayout(usePreloadedCancellationState: true)
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
                    | Cancel
                    |--------------------------------------------------------------------------
                    */
                    Action::make('cancel_payout')
                        ->label(__('withdraw.cancel_payout'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(
                            fn (WithdrawRequest $record): bool =>
                                $record->canCancelPayout(usePreloadedCancellationState: true)
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
                    | Normal Sync
                    |--------------------------------------------------------------------------
                    |
                    | If it is a communication-uncertain payout, Sync is already
                    | displayed outside the menu.
                    |
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
                                && ! self::hasUnknownGatewayStatus($record)
                                && ! self::hasGatewayWarning($record)
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

                    RestoreAction::make(),
                ])
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Money Summary
    |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | Status Label
    |--------------------------------------------------------------------------
    */
    private static function statusLabel(
        WithdrawRequest $record
    ): string {
        if (self::hasUnknownGatewayStatus($record)) {
            return self::ui(
                'withdraw.gateway_status_uncertain',
                'Status Needs Confirmation',
                '状态待确认'
            );
        }

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

    /*
    |--------------------------------------------------------------------------
    | Status Description
    |--------------------------------------------------------------------------
    */
    private static function statusDescription(
        WithdrawRequest $record
    ): ?string {
        $parts = [];

        if (
            $record->status === 'approved'
            && filled($record->approval_mode)
        ) {
            $parts[] = $record->approvalModeLabel();
        }

        if (
            $record->status === 'approved'
            && $record->approval_mode
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

        $gatewayAlert = self::gatewayFriendlyAlert($record);

        if ($gatewayAlert !== null) {
            $parts[] = '⚠ '.$gatewayAlert;
        }

        return $parts !== []
            ? implode(' · ', $parts)
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Status Color
    |--------------------------------------------------------------------------
    */
    private static function statusColor(
        WithdrawRequest $record
    ): string {
        if (self::hasUnknownGatewayStatus($record)) {
            return 'warning';
        }

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

    /*
    |--------------------------------------------------------------------------
    | Gateway Attention Detection
    |--------------------------------------------------------------------------
    */
    private static function hasGatewayAttention(
        WithdrawRequest $record
    ): bool {
        if ($record->status !== 'approved') {
            return false;
        }

        if (
            in_array(
                $record->payout_status,
                [
                    WithdrawRequest::PAYOUT_COMPLETED,
                    WithdrawRequest::PAYOUT_CANCELLED,
                ],
                true
            )
        ) {
            return false;
        }

        return self::hasUnknownGatewayStatus($record) || in_array(
            $record->payout_status,
            [
                WithdrawRequest::PAYOUT_FAILED,
                WithdrawRequest::PAYOUT_REJECTED,
                WithdrawRequest::PAYOUT_OVERDUE,
                WithdrawRequest::PAYOUT_CREATE_FAILED,
                WithdrawRequest::PAYOUT_REFUND_REVIEW,
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unknown provider state
    |--------------------------------------------------------------------------
    */
    private static function hasUnknownGatewayStatus(
        WithdrawRequest $record
    ): bool {
        if ($record->status !== 'approved') {
            return false;
        }

        if (
            in_array(
                $record->payout_status,
                [
                    WithdrawRequest::PAYOUT_COMPLETED,
                    WithdrawRequest::PAYOUT_CANCELLED,
                ],
                true
            )
        ) {
            return false;
        }

        return $record->gateway_payment_status
            === WithdrawalPayoutAttempt::STATUS_CREATE_PENDING;
    }

    private static function hasGatewayWarning(
        WithdrawRequest $record
    ): bool {
        return $record->status === WithdrawRequest::STATUS_APPROVED
            && ! in_array($record->payout_status, [
                WithdrawRequest::PAYOUT_COMPLETED,
                WithdrawRequest::PAYOUT_CANCELLED,
            ], true)
            && in_array($record->gateway_payment_status, [
                WithdrawalPayoutAttempt::STATUS_NONE,
                WithdrawalPayoutAttempt::STATUS_PENDING,
                WithdrawalPayoutAttempt::STATUS_PREPARING,
                WithdrawalPayoutAttempt::STATUS_PROCESSING,
            ], true)
            && self::gatewayFailureReason($record) !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Friendly Gateway Alert
    |--------------------------------------------------------------------------
    */
    private static function gatewayFriendlyAlert(
        WithdrawRequest $record
    ): ?string {
        if (! self::hasGatewayAttention($record)
            && ! self::hasGatewayWarning($record)) {
            return null;
        }

        $reason = self::gatewayFailureReason($record);
        $reasonLower = Str::lower((string) $reason);

        if (data_get($record->gateway_meta, 'financial_review_reason') === 'provider_completed_after_cancel') {
            return self::ui(
                'withdraw.gateway_completed_after_cancel_review',
                'QC168 reported this payout completed after the user was refunded. Financial review is required immediately.',
                '用户退款后 QC168 又报告出款完成，必须立即进行财务审核。'
            );
        }

        if (self::hasUnknownGatewayStatus($record)) {
            return self::ui(
                'withdraw.gateway_timeout_sync_first',
                'The QC168 request did not return a reliable status; sync first.',
                'QC168 请求未返回可靠状态，请先同步支付状态'
            );
        }

        if (self::hasGatewayWarning($record)) {
            $currentStatus = self::humanizeStatus(
                $record->gateway_payment_status
            );

            if (Str::contains($reasonLower, [
                'timeout',
                'timed out',
                'awaiting headers',
                'curl error 28',
            ])) {
                return self::ui(
                    'withdraw.gateway_timeout_known_status',
                    "Latest gateway request timed out. Current QC168 status is still {$currentStatus}; sync to refresh.",
                    "最近一次网关请求超时，当前 QC168 状态仍为 {$currentStatus}，可同步最新状态。"
                );
            }

            if (Str::contains($reasonLower, [
                'connection refused',
                'could not connect',
                "couldn't connect",
                'connection reset',
                'network unreachable',
            ])) {
                return self::ui(
                    'withdraw.gateway_unreachable_known_status',
                    "The gateway payment service was temporarily unreachable. Current QC168 status is still {$currentStatus}; sync to refresh.",
                    "网关支付服务暂时无法连接，当前 QC168 状态仍为 {$currentStatus}，可同步最新状态。"
                );
            }

            if (Str::contains($reasonLower, 'no bank cards available')) {
                return self::ui(
                    'withdraw.gateway_no_bank_cards_known_status',
                    "The payment channel reported no available bank cards. Current QC168 status is still {$currentStatus}.",
                    "支付通道报告没有可用银行卡，当前 QC168 状态仍为 {$currentStatus}。"
                );
            }

            return self::ui(
                'withdraw.gateway_diagnostic_known_status',
                "The gateway reported a recent error. Current QC168 status is still {$currentStatus}; sync to refresh.",
                "网关报告了最近一次错误，当前 QC168 状态仍为 {$currentStatus}，可同步最新状态。"
            );
        }

        if (
            $record->payout_status !== WithdrawRequest::PAYOUT_CREATE_FAILED
            && Str::contains(
                $reasonLower,
                [
                    'no bank cards available',
                ]
            )
        ) {
            return self::ui(
                'withdraw.gateway_no_bank_cards',
                'No bank cards are currently available in the payment channel.',
                '支付通道目前没有可用银行卡'
            );
        }

        return match ($record->payout_status) {
            WithdrawRequest::PAYOUT_FAILED =>
                self::ui(
                    'withdraw.gateway_payout_failed',
                    'Gateway payout failed.',
                    '第三方支付出款失败'
                ),

            WithdrawRequest::PAYOUT_REJECTED =>
                self::ui(
                    'withdraw.gateway_payout_rejected',
                    'Gateway payout was rejected.',
                    '第三方支付拒绝了出款'
                ),

            WithdrawRequest::PAYOUT_OVERDUE =>
                self::ui(
                    'withdraw.gateway_payout_overdue',
                    'Gateway payout expired.',
                    '第三方支付出款已过期'
                ),

            WithdrawRequest::PAYOUT_CREATE_FAILED =>
                self::ui(
                    'withdraw.gateway_submission_failed',
                    'QC168 did not create this payout order; it may be retried or cancelled and refunded.',
                    'QC168 未成功建立该出款订单，可重新提交或取消退款。'
                ),

            WithdrawRequest::PAYOUT_REFUND_REVIEW =>
                self::ui(
                    'withdraw.gateway_refund_review',
                    'Gateway refund requires review.',
                    '第三方支付退款需要人工处理'
                ),

            default =>
                filled($reason)
                    ? Str::limit($reason, 100)
                    : self::ui(
                        'withdraw.gateway_requires_attention',
                        'Gateway payout requires attention.',
                        '第三方支付出款需要处理'
                    ),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Raw Gateway Failure Reason
    |--------------------------------------------------------------------------
    */
    private static function gatewayFailureReason(
        WithdrawRequest $record
    ): ?string {
        $reason = trim(
            (string) ($record->gateway_fail_reason ?? '')
        );

        return $reason !== ''
            ? $reason
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Tooltip
    |--------------------------------------------------------------------------
    */
    private static function gatewayTechnicalTooltip(
        WithdrawRequest $record
    ): ?string {
        if (! self::hasGatewayAttention($record)
            && ! self::hasGatewayWarning($record)) {
            return null;
        }

        $reason = self::gatewayFailureReason($record);

        if ($reason === null) {
            return self::gatewayFriendlyAlert($record);
        }

        return Str::limit($reason, 500);
    }

    private static function humanizeStatus(?string $status): string
    {
        return blank($status)
            ? '-'
            : Str::of($status)->replace('_', ' ')->headline()->toString();
    }

    /*
    |--------------------------------------------------------------------------
    | Bank Summary
    |--------------------------------------------------------------------------
    */
    private static function bankSummary(
        WithdrawRequest $record
    ): ?string {
        $parts = [];

        if (filled($record->bank_account_name)) {
            $parts[] = $record->bank_account_name;
        }

        if (filled($record->bank_account_no)) {
            /*
             * Keeping the full account number because your current table already
             * displays it this way.
             *
             * Change to maskAccountNumber() if you want masking later.
             */
            $parts[] = $record->bank_account_no;
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

    /*
    |--------------------------------------------------------------------------
    | Safe UI fallback
    |--------------------------------------------------------------------------
    |
    | Allows this file to work before you add the new translation keys.
    |
    */
    private static function ui(
        string $key,
        string $english,
        string $chinese
    ): string {
        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return str_starts_with(
            strtolower((string) app()->getLocale()),
            'zh'
        )
            ? $chinese
            : $english;
    }
}
