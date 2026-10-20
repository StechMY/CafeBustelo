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

class ApproveManualRechargeAction
{
    public static function make(): Action
    {
        return Action::make('approve')
            ->label(__('filament.approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (RechargeRequest $record): bool => $record->isPending() && $record->isManualDeposit())
            ->authorize(fn (RechargeRequest $record): bool => Gate::allows('audit', $record))
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
                Checkbox::make('confirmed')->label(__('recharge.confirm_received'))->accepted()->required(),
                Textarea::make('admin_remark')->label(__('filament.admin_remark'))->maxLength(1000)->rows(3),
            ])
            ->requiresConfirmation()
            ->action(function (RechargeRequest $record, array $data): void {
                try {
                    app(RechargeApprovalService::class)->approveManually(
                        $record, Filament::auth()->user(), (string) $data['confirmed_amount'],
                        (bool) ($data['confirmed'] ?? false), $data['admin_remark'] ?? null,
                    );
                    $record->refresh();
                    Notification::make()->title(__('filament.recharge_approved'))->success()->send();
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('filament.approve_failed'))->body($exception->getMessage())->danger()->send();
                }
            });
    }
}
