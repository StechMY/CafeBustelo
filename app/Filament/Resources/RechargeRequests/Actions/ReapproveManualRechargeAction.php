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

class ReapproveManualRechargeAction
{
    public static function make(): Action
    {
        return Action::make('reapprove')
            ->label(__('recharge.reapprove'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (RechargeRequest $record): bool => $record->isRejected() && $record->isManualDeposit())
            ->authorize(fn (RechargeRequest $record): bool => Gate::allows('audit', $record))
            ->modalDescription(__('recharge.reapproval_description'))
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
                Textarea::make('reason')->label(__('recharge.reapproval_reason'))->required()->maxLength(1000),
                Checkbox::make('confirmed')->label(__('recharge.confirm_reapproval'))->accepted()->required(),
            ])
            ->requiresConfirmation()
            ->action(function (RechargeRequest $record, array $data): void {
                try {
                    app(RechargeApprovalService::class)->reapproveManual(
                        $record, Filament::auth()->user(), (string) $data['confirmed_amount'],
                        (bool) ($data['confirmed'] ?? false), $data['reason'], request()->ip(),
                    );
                    $record->refresh();
                    Notification::make()->title(__('recharge.reapproval_success'))->success()->send();
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('recharge.reapproval_failed'))->body($exception->getMessage())->danger()->send();
                }
            });
    }
}
