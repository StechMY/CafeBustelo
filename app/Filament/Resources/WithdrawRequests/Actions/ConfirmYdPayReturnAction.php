<?php

namespace App\Filament\Resources\WithdrawRequests\Actions;

use App\Models\AdminUser;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\YdPayWithdrawalService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConfirmYdPayReturnAction
{
    public static function make(): Action
    {
        return Action::make('confirm_ydpay_return')
            ->label(__('ydpay.manual_return_action'))
            ->icon('heroicon-o-clipboard-document-check')
            ->color('warning')
            ->visible(fn (WithdrawRequest $record): bool => $record->canConfirmYdPayReturn())
            ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('confirmYdPayReturn', $record))
            ->requiresConfirmation()
            ->modalDescription(fn (WithdrawRequest $record): string => __('ydpay.manual_return_help', [
                'order' => $record->gateway_current_merchant_order_id,
                'amount' => number_format((float) $record->amount, 2),
            ]))
            ->modalSubmitActionLabel(__('ydpay.returned_payout_refund_confirm'))
            ->form([
                TextInput::make('provider_order_id')->label(__('filament.gateway_order_uid'))->maxLength(128),
                TextInput::make('return_reference')->label(__('ydpay.manual_return_reference'))->maxLength(128),
                TextInput::make('returned_amount')->label(__('ydpay.manual_return_amount'))->suffix('VND')->numeric()->minValue(0.01),
                FileUpload::make('proof_file')->label(__('ydpay.manual_return_file'))->disk('local')->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(4096)->storeFiles(false)
                    ->previewable(false),
                Textarea::make('reason')->label(__('withdraw.manual_resolution_reason'))->maxLength(1000),
            ])
            ->action(function (Action $action, WithdrawRequest $record, array $data): void {
                $admin = Filament::auth()->user();
                abort_unless($admin instanceof AdminUser, 403);

                try {
                    app(YdPayWithdrawalService::class)->confirmReturnAndRefund($record, $admin, $data, request()->ip());
                    Notification::make()->title(__('ydpay.returned_payout_refund_done'))->success()->send();
                } catch (ValidationException $exception) {
                    Notification::make()->title(__('ydpay.returned_payout_refund_unavailable'))
                        ->body($exception->validator->errors()->first())->danger()->send();
                    $action->halt();
                } catch (Throwable $exception) {
                    report($exception);
                    $record->refresh();
                    Notification::make()->title(__('ydpay.returned_payout_refund_unavailable'))->danger()->send();
                }

                $record->refresh();
            });
    }
}
