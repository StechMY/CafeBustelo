<?php

namespace App\Filament\Resources\RechargeRequests\Tables;

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
                |
                | Username + phone are grouped into one column so the table does not
                | waste horizontal space on two separate customer columns.
                |
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
                    ->description(function (RechargeRequest $record): ?string {
                        if ($record->isGatewayDeposit()) {
                            return null;
                        }

                        if (filled($record->payment_method_name)) {
                            return $record->payment_method_name;
                        }

                        return null;
                    })
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | Unified Status
                |--------------------------------------------------------------------------
                |
                | Main status is displayed as badge.
                | QC168 status becomes the secondary line instead of another column.
                |
                */
                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => __('filament.pending'),
                        'approved' => __('filament.approved'),
                        'rejected' => __('filament.rejected'),
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->description(function (RechargeRequest $record): ?string {
                        if (! $record->isGatewayDeposit()) {
                            return null;
                        }

                        if (blank($record->gateway_payment_status)) {
                            return null;
                        }

                        return __('filament.gateway_status')
                            .': '
                            .self::humanizeStatus($record->gateway_payment_status);
                    })
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | Payment / Bank Summary
                |--------------------------------------------------------------------------
                |
                | Manual deposit:
                | Bank name
                | Payment method / account owner underneath
                |
                | Gateway:
                | QC168
                |
                */
                TextColumn::make('payment_summary')
                    ->label(__('filament.recharge_method'))
                    ->state(function (RechargeRequest $record): string {
                        if ($record->isGatewayDeposit()) {
                            return 'QC168';
                        }

                        return $record->payment_bank_name
                            ?: $record->payment_method_name
                            ?: '-';
                    })
                    ->description(function (RechargeRequest $record): ?string {
                        if ($record->isGatewayDeposit()) {
                            return null;
                        }

                        $parts = [];

                        if (
                            filled($record->payment_method_name)
                            && $record->payment_method_name !== $record->payment_bank_name
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
                    ->url(
                        fn (RechargeRequest $record): ?string =>
                            $record->receipt_url
                    )
                    ->label(__('filament.receipt_path'))
                    ->openUrlInNewTab(),

                /*
                |--------------------------------------------------------------------------
                | Submitted At
                |--------------------------------------------------------------------------
                */
                TextColumn::make('created_at')
                    ->label(__('filament.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                /*
                |--------------------------------------------------------------------------
                | Advanced / Reconciliation Columns
                |--------------------------------------------------------------------------
                |
                | Still available from column toggle, but hidden by default so the
                | normal finance workflow stays compact.
                |
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

            /*
            |--------------------------------------------------------------------------
            | Actions
            |--------------------------------------------------------------------------
            |
            | Only View stays permanently visible.
            |
            | Approve / Reject / Sync / Delete are moved into one compact action
            | menu so they no longer consume huge horizontal table width.
            |
            */
            ->recordActions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),

                ActionGroup::make([
                    Action::make('syncGateway')
                        ->label(__('filament.sync_gateway_status'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(
                            fn (RechargeRequest $record): bool =>
                                $record->isGatewayDeposit()
                        )
                        ->authorize(
                            fn (RechargeRequest $record): bool =>
                                Gate::allows('syncGateway', $record)
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
                                Gate::allows('audit', $record)
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
                                Gate::allows('audit', $record),
                                403
                            );

                            try {
                                app(
                                    RechargeApprovalService::class
                                )->approve(
                                    rechargeRequest: $record,
                                    adminUser: Filament::auth()->user(),
                                    adminRemark:
                                        $data['admin_remark'] ?? null,
                                );

                                Notification::make()
                                    ->title(
                                        __('filament.recharge_approved')
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
                            RechargeRequest $record,
                            array $data
                        ): void {
                            abort_unless(
                                Gate::allows('audit', $record),
                                403
                            );

                            try {
                                app(
                                    RechargeApprovalService::class
                                )->reject(
                                    rechargeRequest: $record,
                                    adminUser: Filament::auth()->user(),
                                    adminRemark:
                                        $data['admin_remark'] ?? null,
                                );

                                Notification::make()
                                    ->title(
                                        __('filament.recharge_rejected')
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
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    private static function humanizeStatus(?string $status): string
    {
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
}