<?php

namespace App\Filament\Resources\WithdrawRequests\Actions;

use App\Models\AdminUser;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\YdPayWithdrawalService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class RefundYdPayReturnedPayoutAction
{
    public static function make(): Action
    {
        return Action::make('refund_ydpay_returned_payout')
            ->label(__('ydpay.returned_payout_refund'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (WithdrawRequest $record): bool => $record->canRefundReturnedYdPayPayout())
            ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('cancelGateway', $record) && Gate::allows('syncGateway', $record))
            ->requiresConfirmation()
            ->modalDescription(fn (WithdrawRequest $record): string => __('ydpay.returned_payout_refund_help', [
                'amount' => number_format((float) $record->amount, 2),
            ]))
            ->modalSubmitActionLabel(__('ydpay.returned_payout_refund_confirm'))
            ->form([Textarea::make('reason')->label(__('withdraw.manual_resolution_reason'))->required()->maxLength(1000)])
            ->action(function (WithdrawRequest $record, array $data): void {
                $admin = Filament::auth()->user();
                abort_unless($admin instanceof AdminUser, 403);

                try {
                    app(YdPayWithdrawalService::class)->refundReturnedPayout($record, $admin, $data['reason'], request()->ip());
                    Notification::make()->title(__('ydpay.returned_payout_refund_done'))->success()->send();
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('ydpay.returned_payout_refund_unavailable'))->danger()->send();
                }

                $record->refresh();
            });
    }
}
