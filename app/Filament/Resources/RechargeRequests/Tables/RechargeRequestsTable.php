<?php

namespace App\Filament\Resources\RechargeRequests\Tables;

use App\Filament\Resources\RechargeRequests\Actions\ReverseManualRechargeAction;
use App\Models\RechargeRequest;
use App\Services\PaymentGateway\Qc168CallbackService;
use App\Services\RechargeApprovalService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class RechargeRequestsTable
{
    public static function configure(Table $table): Table
    {
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
                        fn (RechargeRequest $record): ?string =>
                            filled($record->user?->phone)
                                ? $record->user->phone
                                : null
                    ),

                /*
                |--------------------------------------------------------------------------
                | Amount
                |--------------------------------------------------------------------------
                */
                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: '.', thousandsSeparator: ',')
                    ->prefix(config('app.currency').' ')
                    ->sortable()
                    ->weight('bold'),

                /*
                |--------------------------------------------------------------------------
                | Deposit Method
                |--------------------------------------------------------------------------
                */
                TextColumn::make('deposit_method')
                    ->label(__('filament.deposit_method'))
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            $state === RechargeRequest::METHOD_GATEWAY
                                ? __('filament.payment_gateway')
                                : __('filament.manual_deposit')
                    )
                    ->badge()
                    ->color(
                        fn (?string $state): string =>
                            $state === RechargeRequest::METHOD_GATEWAY
                                ? 'info'
                                : 'gray'
                    )
                    ->description(function (
                        RechargeRequest $record
                    ): ?string {
                        if ($record->isGatewayDeposit()) {
                            return 'QC168';
                        }

                        return filled($record->payment_method_name)
                            ? $record->payment_method_name
                            : null;
                    })
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | Unified Status
                |--------------------------------------------------------------------------
                */
                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->formatStateUsing(
                        fn (
                            ?string $state,
                            RechargeRequest $record
                        ): string =>
                            self::statusLabel($record)
                    )
                    ->badge()
                    ->color(
                        fn (
                            ?string $state,
                            RechargeRequest $record
                        ): string =>
                            self::statusColor($record)
                    )
                    ->description(
                        fn (RechargeRequest $record): ?string =>
                            self::statusDescription($record)
                    )
                    ->tooltip(
                        fn (RechargeRequest $record): ?string =>
                            self::gatewayTechnicalTooltip($record)
                    )
                    ->sortable()
                    ->wrap(),

                /*
                |--------------------------------------------------------------------------
                | Payment Summary
                |--------------------------------------------------------------------------
                */
                TextColumn::make('payment_summary')
                    ->label(__('filament.recharge_method'))
                    ->state(function (
                        RechargeRequest $record
                    ): string {
                        if ($record->isGatewayDeposit()) {
                            return 'QC168';
                        }

                        return $record->payment_bank_name
                            ?: $record->payment_method_name
                            ?: '-';
                    })
                    ->description(function (
                        RechargeRequest $record
                    ): ?string {
                        if ($record->isGatewayDeposit()) {
                            return filled($record->merchant_order_id)
                                ? self::ui(
                                    'filament.gateway_order',
                                    'Gateway Order',
                                    '网关订单'
                                )
                                    .': '
                                    .Str::limit(
                                        (string) $record->merchant_order_id,
                                        28
                                    )
                                : null;
                        }

                        $parts = [];

                        if (
                            filled($record->payment_method_name)
                            && $record->payment_method_name
                                !== $record->payment_bank_name
                        ) {
                            $parts[] = $record->payment_method_name;
                        }

                        if (filled($record->payment_account_name)) {
                            $parts[] = $record->payment_account_name;
                        }

                        if (filled($record->payment_account_no)) {
                            $parts[] = self::maskAccountNumber(
                                $record->payment_account_no
                            );
                        }

                        return $parts !== []
                            ? implode(' · ', $parts)
                            : null;
                    })
                    ->wrap(),

                /*
                |--------------------------------------------------------------------------
                | Receipt
                |--------------------------------------------------------------------------
                */
                ImageColumn::make('receipt_path')
                    ->disk('do_spaces')
                    ->checkFileExistence(false)
                    ->url(
                        fn (RechargeRequest $record): ?string =>
                            $record->receipt_url
                    )
                    ->label(__('filament.receipt_path'))
                    ->openUrlInNewTab(),

                /*
                |--------------------------------------------------------------------------
                | Submitted
                |--------------------------------------------------------------------------
                */
                TextColumn::make('created_at')
                    ->label(__('filament.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                /*
                |--------------------------------------------------------------------------
                | Advanced Columns
                |--------------------------------------------------------------------------
                */
                TextColumn::make('gateway_payment_status')
                    ->label(__('filament.gateway_status'))
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            self::humanizeStatus($state)
                    )
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'completed' => 'success',

                        'failed',
                        'reject',
                        'refund',
                        'overdue',
                        'create_failed' => 'danger',

                        'pending',
                        'preparing',
                        'processing',
                        'none' => 'warning',

                        default => 'gray',
                    })
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                |--------------------------------------------------------------------------
                | Failure Reason
                |--------------------------------------------------------------------------
                */
                TextColumn::make('gateway_fail_reason')
                    ->label(
                        self::ui(
                            'filament.latest_gateway_error',
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

                TextColumn::make('reference_no')
                    ->label(__('filament.reference_no'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('merchant_order_id')
                    ->label(__('filament.merchant_order_id'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_bank_name')
                    ->label(__('filament.bank_name'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_account_name')
                    ->label(__('filament.account_name'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_account_no')
                    ->label(__('filament.account_no'))
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
            ])

            ->defaultSort('created_at', 'desc')

            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => __('filament.pending'),
                        'approved' => __('filament.approved'),
                        'rejected' => __('filament.rejected'),
                    ]),

                SelectFilter::make('deposit_method')
                    ->label(__('filament.deposit_method'))
                    ->options([
                        RechargeRequest::METHOD_MANUAL =>
                            __('filament.manual_deposit'),

                        RechargeRequest::METHOD_GATEWAY =>
                            __('filament.payment_gateway'),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | Gateway Attention
                |--------------------------------------------------------------------------
                */
                Filter::make('gateway_attention')
                    ->label(
                        self::ui(
                            'filament.needs_attention',
                            'Needs Attention',
                            '需要处理'
                        )
                    )
                    ->query(function (Builder $query): Builder {
                        return $query
                            ->where(
                                'deposit_method',
                                RechargeRequest::METHOD_GATEWAY
                            )
                            ->whereIn(
                                'gateway_payment_status',
                                [
                                    'failed',
                                    'reject',
                                    'refund',
                                    'overdue',
                                    'create_failed',
                                    'create_pending',
                                ]
                            );
                    }),

                Filter::make('gateway_warning')
                    ->label(
                        self::ui(
                            'filament.gateway_warning',
                            'Gateway Warning',
                            '网关警告'
                        )
                    )
                    ->query(
                        fn (Builder $query): Builder => $query
                            ->where(
                                'deposit_method',
                                RechargeRequest::METHOD_GATEWAY
                            )
                            ->whereIn('gateway_payment_status', [
                                'none',
                                'pending',
                                'preparing',
                                'processing',
                            ])
                            ->whereNotNull('gateway_fail_reason')
                            ->where('gateway_fail_reason', '!=', '')
                            ->where('status', '!=', 'approved')
                    ),

                Filter::make('created_at')
                    ->label(__('filament.created_at'))
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
                ViewAction::make()
                    ->icon('heroicon-o-eye'),

                /*
                |--------------------------------------------------------------------------
                | Direct Sync For Problematic Gateway Deposits
                |--------------------------------------------------------------------------
                */
                Action::make('syncGatewayAttention')
                    ->label(
                        self::ui(
                            'filament.sync_gateway_status',
                            'Sync Status',
                            '同步状态'
                        )
                    )
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(
                        fn (RechargeRequest $record): bool =>
                            $record->isGatewayDeposit()
                            && (
                                self::hasUnknownGatewayStatus($record)
                                || self::hasGatewayWarning($record)
                            )
                    )
                    ->authorize(
                        fn (RechargeRequest $record): bool =>
                            Gate::allows(
                                'syncGateway',
                                $record
                            )
                    )
                    ->tooltip(
                        self::ui(
                            'filament.gateway_sync_before_retry',
                            'Gateway response was uncertain. Sync the order first.',
                            '网关响应状态不确定，请先同步订单状态。'
                        )
                    )
                    ->requiresConfirmation()
                    ->action(function (
                        RechargeRequest $record
                    ): void {
                        abort_unless(
                            Gate::allows(
                                'syncGateway',
                                $record
                            ),
                            403
                        );

                        try {
                            $result = app(
                                Qc168CallbackService::class
                            )->sync($record);

                            if (! $result['accepted']) {
                                Notification::make()
                                    ->title(
                                        __('filament.gateway_sync_failed')
                                    )
                                    ->body($result['result'])
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(
                                    __('filament.gateway_status_synced')
                                )
                                ->body($result['result'])
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title(
                                    __('filament.gateway_sync_failed')
                                )
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                ActionGroup::make([
                    /*
                    |--------------------------------------------------------------------------
                    | Normal Gateway Sync
                    |--------------------------------------------------------------------------
                    */
                    Action::make('syncGateway')
                        ->label(
                            __('filament.sync_gateway_status')
                        )
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(
                            fn (RechargeRequest $record): bool =>
                                $record->isGatewayDeposit()
                                && ! self::hasUnknownGatewayStatus($record)
                                && ! self::hasGatewayWarning($record)
                        )
                        ->authorize(
                            fn (RechargeRequest $record): bool =>
                                Gate::allows(
                                    'syncGateway',
                                    $record
                                )
                        )
                        ->requiresConfirmation()
                        ->action(function (
                            RechargeRequest $record
                        ): void {
                            abort_unless(
                                Gate::allows(
                                    'syncGateway',
                                    $record
                                ),
                                403
                            );

                            try {
                                $result = app(
                                    Qc168CallbackService::class
                                )->sync($record);

                                if (! $result['accepted']) {
                                    Notification::make()
                                        ->title(
                                            __('filament.gateway_sync_failed')
                                        )
                                        ->body($result['result'])
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                Notification::make()
                                    ->title(
                                        __('filament.gateway_status_synced')
                                    )
                                    ->body($result['result'])
                                    ->success()
                                    ->send();
                            } catch (Throwable $exception) {
                                report($exception);

                                Notification::make()
                                    ->title(
                                        __('filament.gateway_sync_failed')
                                    )
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /*
                    |--------------------------------------------------------------------------
                    | Manual Approve
                    |--------------------------------------------------------------------------
                    */
                    Action::make('approve')
                        ->label(__('filament.approve'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(
                            fn (RechargeRequest $record): bool =>
                                $record->status === 'pending'
                                && $record->isManualDeposit()
                        )
                        ->authorize(
                            fn (RechargeRequest $record): bool =>
                                Gate::allows(
                                    'audit',
                                    $record
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
                            RechargeRequest $record,
                            array $data
                        ): void {
                            abort_unless(
                                Gate::allows(
                                    'audit',
                                    $record
                                ),
                                403
                            );

                            try {
                                app(
                                    RechargeApprovalService::class
                                )->approve(
                                    rechargeRequest: $record,
                                    adminUser:
                                        Filament::auth()->user(),
                                    adminRemark:
                                        $data['admin_remark'] ?? null,
                                );

                                Notification::make()
                                    ->title(
                                        __(
                                            'filament.recharge_approved'
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
                    ReverseManualRechargeAction::make(),

                    /*
                    |--------------------------------------------------------------------------
                    | Manual Reject
                    |--------------------------------------------------------------------------
                    */
                    Action::make('reject')
                        ->label(__('filament.reject'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(
                            fn (RechargeRequest $record): bool =>
                                $record->status === 'pending'
                                && $record->isManualDeposit()
                        )
                        ->authorize(
                            fn (RechargeRequest $record): bool =>
                                Gate::allows(
                                    'audit',
                                    $record
                                )
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
                            RechargeRequest $record,
                            array $data
                        ): void {
                            abort_unless(
                                Gate::allows(
                                    'audit',
                                    $record
                                ),
                                403
                            );

                            try {
                                app(
                                    RechargeApprovalService::class
                                )->reject(
                                    rechargeRequest: $record,
                                    adminUser:
                                        Filament::auth()->user(),
                                    adminRemark:
                                        $data['admin_remark'] ?? null,
                                );

                                Notification::make()
                                    ->title(
                                        __(
                                            'filament.recharge_rejected'
                                        )
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

                    DeleteAction::make(),
                ])
                    ->icon(
                        'heroicon-o-ellipsis-horizontal'
                    ),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Status Label
    |--------------------------------------------------------------------------
    */
    private static function statusLabel(
        RechargeRequest $record
    ): string {
        if (
            $record->isGatewayDeposit()
            && self::hasUnknownGatewayStatus($record)
        ) {
            return self::ui(
                'filament.gateway_status_uncertain',
                'Status Needs Confirmation',
                '状态待确认'
            );
        }

        if ($record->isGatewayDeposit()) {
            if ($record->status === 'approved') {
                return __('filament.approved');
            }

            if (filled($record->gateway_payment_status)) {
                return match ($record->gateway_payment_status) {
                    'completed' => __('filament.approved'),
                    'failed' => self::ui(
                        'filament.gateway_payment_failed',
                        'Payment Failed',
                        '支付失败'
                    ),
                    'reject' => self::ui(
                        'filament.gateway_payment_rejected',
                        'Payment Rejected',
                        '支付被拒绝'
                    ),
                    'refund' => self::ui(
                        'filament.gateway_payment_refund',
                        'Refund Requires Review',
                        '退款需要审核'
                    ),
                    'overdue' => self::ui(
                        'filament.gateway_payment_overdue',
                        'Payment Overdue',
                        '支付已逾期'
                    ),
                    'create_failed' => self::ui(
                        'filament.gateway_submission_failed',
                        'Gateway Order Creation Failed',
                        '创建网关订单失败'
                    ),
                    default => self::humanizeStatus(
                        $record->gateway_payment_status
                    ),
                };
            }
        }

        return match ($record->status) {
            'pending' => __('filament.pending'),
            'approved' => __('filament.approved'),
            'rejected' => __('filament.rejected'),

            default => $record->status ?: '-',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Status Color
    |--------------------------------------------------------------------------
    */
    private static function statusColor(
        RechargeRequest $record
    ): string {
        if (
            $record->isGatewayDeposit()
            && self::hasUnknownGatewayStatus($record)
        ) {
            return 'warning';
        }

        if (
            $record->isGatewayDeposit()
            && self::isGatewayDefiniteFailure($record)
        ) {
            return 'danger';
        }

        if ($record->isGatewayDeposit()) {
            if ($record->gateway_payment_status === 'completed'
                || $record->status === 'approved') {
                return 'success';
            }

            if (in_array($record->gateway_payment_status, [
                'none',
                'pending',
                'preparing',
                'processing',
            ], true)) {
                return 'info';
            }
        }

        return match ($record->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Status Description
    |--------------------------------------------------------------------------
    */
    private static function statusDescription(
        RechargeRequest $record
    ): ?string {
        if (! $record->isGatewayDeposit()) {
            return null;
        }

        $parts = [];

        if (filled($record->gateway_payment_status)) {
            $parts[] = __('filament.gateway_status')
                .': '
                .self::humanizeStatus(
                    $record->gateway_payment_status
                );
        }

        $alert = self::gatewayFriendlyAlert($record);

        if ($alert !== null) {
            $parts[] = '⚠ '.$alert;
        }

        return $parts !== []
            ? implode(' · ', $parts)
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Attention Detection
    |--------------------------------------------------------------------------
    */
    private static function hasGatewayAttention(
        RechargeRequest $record
    ): bool {
        if (! $record->isGatewayDeposit()) {
            return false;
        }

        if (
            $record->gateway_payment_status === 'completed'
            || $record->status === 'approved'
        ) {
            return false;
        }

        return self::hasUnknownGatewayStatus($record)
            || self::isGatewayDefiniteFailure($record);
    }

    /*
    |--------------------------------------------------------------------------
    | Unknown provider state
    |--------------------------------------------------------------------------
    */
    private static function hasUnknownGatewayStatus(
        RechargeRequest $record
    ): bool {
        if (! $record->isGatewayDeposit()) {
            return false;
        }

        if (
            $record->gateway_payment_status === 'completed'
            || $record->status === 'approved'
        ) {
            return false;
        }

        return $record->gateway_payment_status === 'create_pending';
    }

    private static function hasGatewayWarning(
        RechargeRequest $record
    ): bool {
        return $record->isGatewayDeposit()
            && $record->status !== 'approved'
            && in_array($record->gateway_payment_status, [
                'none',
                'pending',
                'preparing',
                'processing',
            ], true)
            && self::gatewayFailureReason($record) !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Definite Gateway Failure
    |--------------------------------------------------------------------------
    */
    private static function isGatewayDefiniteFailure(
        RechargeRequest $record
    ): bool {
        if (! $record->isGatewayDeposit()) {
            return false;
        }

        if (
            $record->gateway_payment_status === 'completed'
            || $record->status === 'approved'
        ) {
            return false;
        }

        return in_array(
            $record->gateway_payment_status,
            [
                'failed',
                'reject',
                'refund',
                'overdue',
                'create_failed',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Friendly Alert
    |--------------------------------------------------------------------------
    */
    private static function gatewayFriendlyAlert(
        RechargeRequest $record
    ): ?string {
        if (! self::hasGatewayAttention($record)
            && ! self::hasGatewayWarning($record)) {
            return null;
        }

        $reason = self::gatewayFailureReason($record);
        $reasonLower = Str::lower((string) $reason);

        if (self::hasUnknownGatewayStatus($record)) {
            return self::ui(
                'filament.gateway_timeout_sync_first',
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
                    'filament.gateway_timeout_known_status',
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
                    'filament.gateway_unreachable_known_status',
                    "The gateway payment service was temporarily unreachable. Current QC168 status is still {$currentStatus}; sync to refresh.",
                    "网关支付服务暂时无法连接，当前 QC168 状态仍为 {$currentStatus}，可同步最新状态。"
                );
            }

            if (Str::contains($reasonLower, 'no bank cards available')) {
                return self::ui(
                    'filament.gateway_no_bank_cards_known_status',
                    "The payment channel reported no available bank cards. Current QC168 status is still {$currentStatus}.",
                    "支付通道报告没有可用银行卡，当前 QC168 状态仍为 {$currentStatus}。"
                );
            }

            return self::ui(
                'filament.gateway_diagnostic_known_status',
                "The gateway reported a recent error. Current QC168 status is still {$currentStatus}; sync to refresh.",
                "网关报告了最近一次错误，当前 QC168 状态仍为 {$currentStatus}，可同步最新状态。"
            );
        }

        if (
            $record->gateway_payment_status !== 'create_failed'
            && Str::contains(
                $reasonLower,
                'no bank cards available'
            )
        ) {
            return self::ui(
                'filament.gateway_no_bank_cards',
                'No bank cards are currently available in the payment channel.',
                '支付通道目前没有可用银行卡'
            );
        }

        return match ($record->gateway_payment_status) {
            'failed' =>
                self::ui(
                    'filament.gateway_payment_failed',
                    'Gateway payment failed.',
                    '第三方支付处理失败'
                ),

            'reject' =>
                self::ui(
                    'filament.gateway_payment_rejected',
                    'Gateway payment was rejected.',
                    '第三方支付拒绝了交易'
                ),

            'overdue' =>
                self::ui(
                    'filament.gateway_payment_overdue',
                    'Gateway payment expired.',
                    '第三方支付订单已过期'
                ),

            'refund' =>
                self::ui(
                    'filament.gateway_payment_refund',
                    'Gateway payment was refunded and requires review.',
                    '第三方支付已退款，需要人工确认'
                ),

            'create_failed' =>
                self::ui(
                    'filament.gateway_submission_failed',
                    'QC168 did not create this order. A new deposit order may be created.',
                    'QC168 未成功建立该订单，可以重新建立充值订单。'
                ),

            default =>
                filled($reason)
                    ? Str::limit($reason, 100)
                    : self::ui(
                        'filament.gateway_requires_attention',
                        'Gateway payment requires attention.',
                        '第三方支付订单需要处理'
                    ),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Failure Reason
    |--------------------------------------------------------------------------
    */
    private static function gatewayFailureReason(
        RechargeRequest $record
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
        RechargeRequest $record
    ): ?string {
        if (! self::hasGatewayAttention($record)
            && ! self::hasGatewayWarning($record)) {
            return null;
        }

        $reason = self::gatewayFailureReason($record);

        if ($reason === null) {
            return self::gatewayFriendlyAlert($record);
        }

        return Str::limit(
            $reason,
            500
        );
    }

    private static function humanizeStatus(
        ?string $status
    ): string {
        if (blank($status)) {
            return '-';
        }

        return Str::of($status)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    private static function maskAccountNumber(
        string|int|null $accountNo
    ): string {
        $accountNo = trim(
            (string) $accountNo
        );

        if ($accountNo === '') {
            return '-';
        }

        $length = strlen($accountNo);

        if ($length <= 4) {
            return $accountNo;
        }

        return str_repeat(
            '•',
            $length - 4
        )
            .substr(
                $accountNo,
                -4
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Translation fallback
    |--------------------------------------------------------------------------
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
            strtolower(
                (string) app()->getLocale()
            ),
            'zh'
        )
            ? $chinese
            : $english;
    }
}
