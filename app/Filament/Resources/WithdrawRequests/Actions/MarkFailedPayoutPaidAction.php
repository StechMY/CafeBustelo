<?php

namespace App\Filament\Resources\WithdrawRequests\Actions;

use App\Models\WithdrawRequest;
use App\Services\WithdrawService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class MarkFailedPayoutPaidAction
{
    public static function make(bool $usePreloadedCancellationState = false): Action
    {
        return Action::make('mark_failed_payout_paid')
            ->hidden() // QC168 retired from the UI; retain legacy processing.
            ->label('QC168 · '.__('withdraw.mark_failed_payout_paid'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (WithdrawRequest $record): bool => $record->canMarkPayoutPaid($usePreloadedCancellationState))
            ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('approveManual', $record))
            ->modalDescription(__('withdraw.mark_paid_description'))
            ->form([
                TextInput::make('merchant_order_id')
                    ->label(__('withdraw.merchant_order_id'))
                    ->default(fn (WithdrawRequest $record): ?string => $record->gateway_current_merchant_order_id)
                    ->readOnly()->required(),
                TextInput::make('payment_reference')
                    ->label(__('withdraw.manual_payment_reference'))
                    ->required()->maxLength(255),
                Textarea::make('reason')
                    ->label(__('withdraw.manual_resolution_reason'))
                    ->required()->maxLength(1000),
                Checkbox::make('confirmed_paid')
                    ->label(__('withdraw.mark_paid_acknowledgement'))
                    ->accepted()->required(),
            ])
            ->requiresConfirmation()
            ->action(function (WithdrawRequest $record, array $data): void {
                abort_unless(Gate::allows('approveManual', $record), 403);

                try {
                    app(WithdrawService::class)->markFailedPayoutPaid(
                        $record, Filament::auth()->user(), $data['merchant_order_id'],
                        $data['payment_reference'], $data['reason'], (bool) ($data['confirmed_paid'] ?? false), request()->ip(),
                    );
                    $record->refresh();
                    Notification::make()->title(__('withdraw.mark_paid_success'))->success()->send();
                } catch (Throwable $exception) {
                    report($exception);
                    $record->refresh();
                    Notification::make()->title(__('withdraw.mark_paid_failed'))->body($exception->getMessage())->danger()->send();
                }
            });
    }
}
