<?php

namespace App\Filament\Resources\WithdrawRequests\Actions;

use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\YdPayGateway;
use App\Services\PaymentGateway\YdPayWithdrawalService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class RetryYdPayNotAcceptedAction
{
    public static function make(): Action
    {
        return Action::make('retry_ydpay_not_accepted')
            ->label(__('ydpay.retry_not_accepted'))
            ->icon('heroicon-o-shield-check')->color('danger')
            ->visible(fn (WithdrawRequest $record): bool => app(YdPayWithdrawalService::class)->canConfirmNotAccepted($record))
            ->disabled(fn (): bool => ! app(YdPayGateway::class)->isAvailable('withdraw'))
            ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('retryGateway', $record) && Gate::allows('confirmGatewayUnpaid', $record))
            ->modalDescription(__('ydpay.not_accepted_description'))
            ->form([
                TextInput::make('merchant_order_id')->label(__('withdraw.merchant_order_id'))
                    ->default(fn (WithdrawRequest $record): string => $record->gateway_current_merchant_order_id)
                    ->readOnly()->required(),
                Textarea::make('provider_confirmation')->label(__('withdraw.provider_confirmation'))
                    ->helperText(__('ydpay.not_accepted_evidence'))->required()->maxLength(2000),
                Textarea::make('reason')->label(__('withdraw.manual_resolution_reason'))->required()->maxLength(1000),
                Checkbox::make('confirmed')->label(__('ydpay.not_accepted_acknowledgement'))->accepted()->required(),
            ])
            ->requiresConfirmation()
            ->action(function (WithdrawRequest $record, array $data): void {
                try {
                    app(YdPayWithdrawalService::class)->retryNotAccepted($record, Filament::auth()->user(),
                        $data['merchant_order_id'], $data['provider_confirmation'], $data['reason'],
                        (bool) ($data['confirmed'] ?? false), request()->ip());
                    Notification::make()->title(__('withdraw.retry_submitted'))->success()->send();
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('ydpay.not_accepted_retry_attention'))
                        ->body(e($exception->getMessage()))->warning()->send();
                }
                $record->refresh();
            });
    }
}
