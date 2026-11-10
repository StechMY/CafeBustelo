<?php

namespace App\Filament\Resources\WithdrawRequests\Actions;

use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\YdPayGateway;
use App\Services\PaymentGateway\YdPayWithdrawalService;
use App\Services\Settings\YdPaySettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ApproveYdPayWithdrawalAction
{
    public static function make(bool $instant = false): Action
    {
        $permission = $instant ? 'approveInstantGateway' : 'approveScheduledGateway';
        $mode = $instant ? 'instant' : 'scheduled';

        return Action::make($instant ? 'approve_ydpay_instant' : 'approve_ydpay')
            ->label(fn (): string => $instant
                ? self::text('approve_gateway_instant')
                : self::text('approve_gateway_scheduled_delay', ['hours' => app(YdPaySettings::class)->releaseHours()]))
            ->icon($instant ? 'heroicon-o-bolt' : 'heroicon-o-clock')
            ->color($instant ? 'danger' : 'info')
            ->visible(fn (WithdrawRequest $record): bool => $record->isPending() && (bool) config('services.ydpay.enabled'))
            ->disabled(fn (): bool => ! app(YdPayGateway::class)->isAvailable('withdraw'))
            ->tooltip(fn (): ?string => app(YdPayGateway::class)->isAvailable('withdraw') ? null : self::text('gateway_unavailable'))
            ->authorize(fn (WithdrawRequest $record): bool => Gate::allows($permission, $record))
            ->modalHeading(self::text('approve_gateway_'.$mode.'_heading'))
            ->modalDescription(fn (): string => self::text('approve_gateway_'.$mode.'_confirmation', ['hours' => app(YdPaySettings::class)->releaseHours()]))
            ->modalSubmitActionLabel(self::text('confirm_approve_gateway_'.$mode))
            ->form([Textarea::make('admin_remark')->label(__('filament.admin_remark'))->rows(3)])
            ->requiresConfirmation()
            ->action(function (WithdrawRequest $record, array $data) use ($instant, $permission, $mode): void {
                abort_unless(Gate::allows($permission, $record), 403);
                try {
                    app(YdPayWithdrawalService::class)->approve($record, Filament::auth()->user(),
                        $data['admin_remark'] ?? null, $instant, request()->ip());
                    Notification::make()->title(self::text('approved_gateway_'.$mode))->success()->send();
                } catch (Throwable $exception) {
                    report($exception);
                    $fresh = $record->fresh();
                    if ($instant && $fresh?->isApproved() && $fresh->gateway_provider === 'ydpay'
                        && $fresh->approval_mode === WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT) {
                        Notification::make()->title(self::text('instant_submission_failed_after_approval'))
                            ->body(e($fresh->gateway_fail_reason ?: self::text('instant_submission_failed_after_approval_help')))->warning()->send();
                    } else {
                        Notification::make()->title(__('filament.approve_failed'))->body($exception->getMessage())->danger()->send();
                    }
                }
                $record->refresh();
            });
    }

    private static function text(string $key, array $parameters = []): string
    {
        if ($key === 'approve_gateway_instant_confirmation') {
            return __('ydpay.approve_instant_confirmation');
        }

        return str_replace('QC168', 'YDPAY', __('withdraw.'.$key, $parameters));
    }
}
