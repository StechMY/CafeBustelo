<?php

namespace App\Filament\Resources\WithdrawRequests\Schemas;

use App\Models\WithdrawRequest;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WithdrawRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.request'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.username')
                                    ->label(__('filament.username'))
                                    ->weight('bold')
                                    ->copyable(),

                                TextEntry::make('user.name')
                                    ->label(__('filament.name')),

                                TextEntry::make('user.phone')
                                    ->label(__('filament.phone'))
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('amount')
                                    ->label(__('filament.amount'))
                                    ->prefix(config('app.currency') . ' ')
                                    ->numeric(decimalPlaces: 2, decimalSeparator: '.', thousandsSeparator: ','),

                                TextEntry::make('fee_amount')
                                    ->label(__('filament.fee'))
                                    ->prefix(config('app.currency') . ' ')
                                    ->numeric(decimalPlaces: 2, decimalSeparator: '.', thousandsSeparator: ','),

                                TextEntry::make('net_amount')
                                    ->label(__('filament.net_amount'))
                                    ->prefix(config('app.currency') . ' ')
                                    ->numeric(decimalPlaces: 2, decimalSeparator: '.', thousandsSeparator: ','),

                                TextEntry::make('vip_level_snapshot')
                                    ->label(__('filament.vip_snapshot'))
                                    ->formatStateUsing(fn($state): string => __('filament.lv') . $state),

                                TextEntry::make('min_withdraw_amount_snapshot')
                                    ->label(__('filament.min_withdraw_snapshot'))
                                    ->prefix(config('app.currency') . ' ')
                                    ->numeric(decimalPlaces: 2, decimalSeparator: '.', thousandsSeparator: ','),

                                TextEntry::make('status')
                                    ->label(__('filament.status'))
                                    ->badge()
                                    ->color(fn(?string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    }),

                                TextEntry::make('approval_mode')
                                    ->label(__('withdraw.approval_method'))
                                    ->formatStateUsing(fn(WithdrawRequest $record): string => $record->approvalModeLabel())
                                    ->badge()
                                    ->placeholder('-'),

                                TextEntry::make('payout_status')
                                    ->label(__('withdraw.payout_status'))
                                    ->formatStateUsing(fn(WithdrawRequest $record): string => $record->payoutStatusLabel())
                                    ->badge(),

                                TextEntry::make('created_at')
                                    ->label(__('filament.submitted_at'))
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make(__('filament.bank_detail'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('bank_name')
                                    ->label(__('filament.bank_name'))
                                    ->copyable(),

                                TextEntry::make('bank_code')
                                    ->label(__('withdraw.bank_code'))
                                    ->copyable()
                                    ->placeholder(__('withdraw.missing_bank_code')),

                                TextEntry::make('bank_account_name')
                                    ->label(__('filament.account_name'))
                                    ->copyable(),

                                TextEntry::make('bank_account_no')
                                    ->label(__('filament.account_no'))
                                    ->copyable(),
                            ]),
                    ]),

                Section::make(__('filament.review'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('reviewer.username')
                                    ->label(__('filament.reviewed_by'))
                                    ->placeholder('-'),

                                TextEntry::make('reviewed_at')
                                    ->label(__('filament.reviewed_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('admin_remark')
                                    ->label(__('filament.admin_remark'))
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make(__('withdraw.gateway'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('gateway_provider')
                                    ->formatStateUsing(fn (WithdrawRequest $record): string => $record->gatewayProviderLabel())
                                    ->label(__('withdraw.gateway_provider'))
                                    ->placeholder('-'),
                                TextEntry::make('payout_status')
                                    ->label(__('withdraw.payout_status'))
                                    ->formatStateUsing(fn(WithdrawRequest $record): string => $record->payoutStatusLabel())
                                    ->badge(),
                                TextEntry::make('gateway_payment_status')
                                    ->label(__('withdraw.gateway_status'))
                                    ->formatStateUsing(fn (WithdrawRequest $record): string => $record->gatewayStatusLabel())
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('gateway_release_at')
                                    ->label(__('withdraw.gateway_release_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('gateway_submitted_at')
                                    ->label(__('withdraw.gateway_submitted_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('gateway_completed_at')
                                    ->label(__('withdraw.gateway_completed_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('gateway_current_merchant_order_id')
                                    ->label(__('withdraw.merchant_order_id'))
                                    ->copyable()
                                    ->placeholder('-'),
                                TextEntry::make('gateway_last_callback_at')
                                    ->label(__('withdraw.last_callback_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('gateway_last_synced_at')
                                    ->label(__('withdraw.last_synced_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('gateway_fail_reason')
                                    ->label(__('withdraw.latest_gateway_error'))
                                    ->helperText(__('withdraw.latest_gateway_error_help'))
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                TextEntry::make('gateway_meta.ydpay_submission_error.message')
                                    ->label(__('ydpay.submission_error'))->placeholder('-')->columnSpanFull()
                                    ->visible(fn (WithdrawRequest $record): bool => $record->gateway_provider === 'ydpay'),
                                TextEntry::make('gateway_meta.ydpay_last_error.message')
                                    ->label(__('ydpay.latest_request_error'))->placeholder('-')->columnSpanFull()
                                    ->visible(fn (WithdrawRequest $record): bool => $record->gateway_provider === 'ydpay'),
                            ]),
                    ]),

                Section::make(__('withdraw.mark_failed_payout_paid'))
                    ->visible(fn (WithdrawRequest $record): bool => $record->isManuallySettledPayout())
                    ->schema([
                        TextEntry::make('gateway_meta.manual_payout.completed_at')
                            ->label(__('withdraw.manually_confirmed_at'))->dateTime(),
                        TextEntry::make('gateway_meta.manual_payout.admin_user_id')
                            ->label(__('filament.reviewed_by')),
                        TextEntry::make('gateway_meta.manual_payout.payment_reference')
                            ->label(__('withdraw.manual_payment_reference')),
                        TextEntry::make('gateway_meta.manual_payout.reason')
                            ->label(__('withdraw.manual_resolution_reason')),
                        TextEntry::make('gateway_meta.financial_review_reason')
                            ->label(__('withdraw.platform_handling'))
                            ->formatStateUsing(fn (): string => __('withdraw.manual_paid_gateway_review'))
                            ->visible(fn (WithdrawRequest $record): bool => data_get($record->gateway_meta, 'financial_review_reason') === 'provider_completed_after_manual_payout'),
                    ]),

                Section::make(__('withdraw.payout_attempts'))
                    ->schema([
                        RepeatableEntry::make('payoutAttempts')
                            ->label('')
                            ->schema([
                                TextEntry::make('attempt_no')
                                    ->label(__('withdraw.attempt')),
                                TextEntry::make('provider')
                                    ->label(__('withdraw.gateway_provider'))
                                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? '-')),
                                TextEntry::make('merchant_order_id')
                                    ->label(__('withdraw.merchant_order_id'))
                                    ->copyable(),
                                TextEntry::make('amount')
                                    ->label(__('filament.amount'))
                                    ->prefix(config('app.currency') . ' '),
                                TextEntry::make('bank_code_snapshot')
                                    ->label(__('withdraw.bank_code')),
                                TextEntry::make('provider_meta.original_net_amount')
                                    ->label(__('ydpay.original_net_amount'))->placeholder('-'),
                                TextEntry::make('provider_meta.rounding_subsidy')
                                    ->label(__('ydpay.rounding_subsidy'))->placeholder('-'),
                                TextEntry::make('provider_meta.not_accepted_confirmation.provider_confirmation')
                                    ->label(__('ydpay.not_accepted_evidence'))->placeholder('-'),
                                TextEntry::make('provider_meta.not_accepted_confirmation.reason')
                                    ->label(__('withdraw.manual_resolution_reason'))->placeholder('-'),
                                TextEntry::make('provider_meta.not_accepted_confirmation.admin_user_id')
                                    ->label(__('filament.reviewed_by'))->placeholder('-'),
                                TextEntry::make('provider_meta.not_accepted_confirmation.confirmed_at')
                                    ->label(__('withdraw.manually_confirmed_at'))->dateTime()->placeholder('-'),
                                TextEntry::make('gateway_status')
                                    ->label(__('withdraw.gateway_status'))
                                    ->formatStateUsing(fn ($record, ?string $state): string => $record->provider === 'ydpay' && data_get($record->provider_response, 'status') !== null
                                        ? __('ydpay.payout_statuses.'.data_get($record->provider_response, 'status')) : ($state ?? '-'))
                                    ->badge(),
                                TextEntry::make('submitted_at')
                                    ->label(__('withdraw.gateway_submitted_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('completed_at')
                                    ->label(__('withdraw.gateway_completed_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('failure_reason')
                                    ->label(__('withdraw.latest_gateway_error'))
                                    ->helperText(__('withdraw.latest_gateway_error_help'))
                                    ->placeholder('-'),
                                TextEntry::make('provider_meta.manual_unpaid_confirmation.provider_confirmation')
                                    ->label(__('withdraw.provider_confirmation'))
                                    ->placeholder('-'),
                                TextEntry::make('provider_meta.manual_unpaid_confirmation.reason')
                                    ->label(__('withdraw.manual_resolution_reason'))
                                    ->placeholder('-'),
                                TextEntry::make('provider_meta.manual_unpaid_confirmation.admin_user_id')
                                    ->label(__('filament.reviewed_by'))
                                    ->placeholder('-'),
                                TextEntry::make('provider_meta.manual_unpaid_confirmation.confirmed_at')
                                    ->label(__('withdraw.manually_confirmed_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
