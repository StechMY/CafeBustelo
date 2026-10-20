<?php

namespace App\Filament\Resources\RechargeRequests\Actions;

use App\Models\RechargeRequest;
use App\Services\RechargeApprovalService;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ReverseManualRechargeAction
{
    public static function make(): Action
    {
        return Action::make('reverse_approval')
            ->label(__('recharge.reverse_approval'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->visible(fn (RechargeRequest $record): bool => $record->isApproved() && $record->isManualDeposit())
            ->authorize(fn (RechargeRequest $record): bool => Gate::allows('reverseApproval', $record))
            ->modalDescription(__('recharge.reversal_description'))
            ->modalContent(fn (RechargeRequest $record) => view('filament.recharge-requests.review', ['record' => $record]))
            ->form([
                TextInput::make('confirmed_amount')->label(__('recharge.confirm_amount'))
                    ->required()->numeric()->minValue(0.01)
                    ->rules(['regex:/^\d{1,12}(\.\d{1,2})?$/', fn (RechargeRequest $record): Closure => function (string $attribute, $value, Closure $fail) use ($record): void {
                        if (number_format((float) $value, 2, '.', '') !== $record->amount) {
                            $fail(__('recharge.amount_mismatch'));
                        }
                    },
                    ]),
                Textarea::make('reason')->label(__('recharge.reversal_reason'))->required()->maxLength(1000),
                Checkbox::make('confirmed')->label(__('recharge.confirm_reversal'))->accepted()->required(),
            ])
            ->requiresConfirmation()
            ->action(function (RechargeRequest $record, array $data): void {
                try {
                    app(RechargeApprovalService::class)->reverseManualApproval(
                        $record, Filament::auth()->user(), (string) $data['confirmed_amount'],
                        (bool) ($data['confirmed'] ?? false), $data['reason'], request()->ip(),
                    );
                    $record->refresh();
                    Notification::make()->title(__('recharge.reversal_success'))->success()->send();
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('recharge.reversal_failed'))->body($exception->getMessage())->danger()->send();
                }
            });
    }
}
