<?php

namespace App\Filament\Resources\WithdrawRequests\Actions;

use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ConfirmGatewayUnpaidAction
{
    public static function make(bool $usePreloadedCancellationState = false): Action
    {
        return Action::make('confirm_gateway_unpaid')
            ->hidden() // QC168 retired from the UI; retain legacy processing.
            ->label('QC168 · '.__('withdraw.confirm_gateway_unpaid'))
            ->icon('heroicon-o-shield-check')
            ->color('warning')
            ->visible(fn (WithdrawRequest $record): bool => $record->canConfirmGatewayUnpaid($usePreloadedCancellationState))
            ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('confirmGatewayUnpaid', $record))
            ->modalDescription(__('withdraw.confirm_unpaid_description'))
            ->form([
                TextInput::make('merchant_order_id')
                    ->label(__('withdraw.merchant_order_id'))
                    ->default(fn (WithdrawRequest $record): string => $record->gateway_current_merchant_order_id)
                    ->readOnly()
                    ->required(),
                Textarea::make('provider_confirmation')
                    ->label(__('withdraw.provider_confirmation'))
                    ->helperText(__('withdraw.provider_confirmation_help'))
                    ->required()
                    ->maxLength(2000),
                Textarea::make('reason')
                    ->label(__('withdraw.manual_resolution_reason'))
                    ->required()
                    ->maxLength(1000),
                Checkbox::make('confirmed_unpaid')
                    ->label(__('withdraw.confirm_unpaid_acknowledgement'))
                    ->accepted()
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(function (WithdrawRequest $record, array $data): void {
                abort_unless(Gate::allows('confirmGatewayUnpaid', $record), 403);

                try {
                    app(Qc168WithdrawalPayoutService::class)->confirmUnpaid(
                        $record,
                        Filament::auth()->user(),
                        $data['merchant_order_id'],
                        $data['provider_confirmation'],
                        $data['reason'],
                        (bool) ($data['confirmed_unpaid'] ?? false),
                        request()->ip(),
                    );
                    $record->refresh();

                    Notification::make()
                        ->title(__('withdraw.confirm_unpaid_success'))
                        ->body(__('withdraw.confirm_unpaid_next_steps'))
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title(__('withdraw.confirm_unpaid_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
