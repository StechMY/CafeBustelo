<?php

namespace App\Filament\Resources\WithdrawRequests\Pages;

use App\Filament\Resources\WithdrawRequests\Actions\ConfirmGatewayUnpaidAction;
use App\Filament\Resources\WithdrawRequests\Actions\MarkFailedPayoutPaidAction;
use App\Filament\Resources\WithdrawRequests\Actions\RevertManualApprovalAction;
use App\Filament\Resources\WithdrawRequests\WithdrawRequestResource;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use App\Services\Settings\Qc168Settings;
use App\Services\WithdrawService;
use App\Support\FilamentSupportScope;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ViewWithdrawRequest extends ViewRecord
{
    protected static string $resource = WithdrawRequestResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless(
            FilamentSupportScope::canAccessUserId($this->record->user_id),
            403
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve_manual')
                ->label(__('withdraw.approve_manual'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('approveManual', $record))
                ->modalHeading(__('withdraw.approve_manual_heading'))
                ->modalDescription(__('withdraw.approve_manual_confirmation'))
                ->modalSubmitActionLabel(__('withdraw.confirm_approve_manual'))
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.admin_remark'))
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('approveManual', $this->record), 403);
                    try {
                        app(WithdrawService::class)->approveManually(
                            withdrawRequest: $this->record,
                            adminUser: Filament::auth()->user(),
                            adminRemark: $data['admin_remark'] ?? null,
                            source: 'filament',
                            ip: request()->ip(),
                        );

                        Notification::make()
                            ->title(__('withdraw.approved_manually'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', [
                            'record' => $this->record,
                        ]));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title(__('filament.approve_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('approve_gateway_scheduled')
                ->label(fn (): string => __('withdraw.approve_gateway_scheduled_delay', [
                    'hours' => app(Qc168Settings::class)->withdrawReleaseHours(),
                ]))
                ->icon('heroicon-o-clock')
                ->color('info')
                ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                ->disabled(fn (WithdrawRequest $record): bool => ! app(Qc168Gateway::class)->isWithdrawalAvailable()
                    || ! preg_match('/^\d+$/', (string) $record->bank_code))
                ->tooltip(fn (WithdrawRequest $record): ?string => ! app(Qc168Gateway::class)->isWithdrawalAvailable()
                    ? __('withdraw.gateway_unavailable')
                    : (! preg_match('/^\d+$/', (string) $record->bank_code)
                        ? __('withdraw.bank_not_supported')
                        : null))
                ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('approveScheduledGateway', $record))
                ->modalHeading(__('withdraw.approve_gateway_scheduled_heading'))
                ->modalDescription(fn (): string => __('withdraw.approve_gateway_scheduled_confirmation', [
                    'hours' => app(Qc168Settings::class)->withdrawReleaseHours(),
                ]))
                ->modalSubmitActionLabel(__('withdraw.confirm_approve_gateway_scheduled'))
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.admin_remark'))
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('approveScheduledGateway', $this->record), 403);

                    try {
                        app(WithdrawService::class)->approveWithScheduledGateway(
                            withdrawRequest: $this->record,
                            adminUser: Filament::auth()->user(),
                            adminRemark: $data['admin_remark'] ?? null,
                            source: 'filament',
                            ip: request()->ip(),
                        );

                        Notification::make()
                            ->title(__('withdraw.approved_gateway_scheduled'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', [
                            'record' => $this->record,
                        ]));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title(__('filament.approve_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('approve_gateway_instant')
                ->label(__('withdraw.approve_gateway_instant'))
                ->icon('heroicon-o-bolt')
                ->color('danger')
                ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                ->disabled(fn (WithdrawRequest $record): bool => ! app(Qc168Gateway::class)->isWithdrawalAvailable()
                    || ! preg_match('/^\d+$/', (string) $record->bank_code))
                ->tooltip(fn (WithdrawRequest $record): ?string => ! app(Qc168Gateway::class)->isWithdrawalAvailable()
                    ? __('withdraw.gateway_unavailable')
                    : (! preg_match('/^\d+$/', (string) $record->bank_code)
                        ? __('withdraw.bank_not_supported')
                        : null))
                ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('approveInstantGateway', $record))
                ->modalHeading(__('withdraw.approve_gateway_instant_heading'))
                ->modalDescription(__('withdraw.approve_gateway_instant_confirmation'))
                ->modalSubmitActionLabel(__('withdraw.confirm_approve_gateway_instant'))
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.admin_remark'))
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('approveInstantGateway', $this->record), 403);

                    try {
                        app(WithdrawService::class)->approveWithInstantGateway(
                            withdrawRequest: $this->record,
                            adminUser: Filament::auth()->user(),
                            adminRemark: $data['admin_remark'] ?? null,
                            source: 'filament',
                            ip: request()->ip(),
                        );

                        Notification::make()
                            ->title(__('withdraw.approved_gateway_instant'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', [
                            'record' => $this->record,
                        ]));
                    } catch (Throwable $exception) {
                        report($exception);
                        $freshRecord = $this->record->fresh();

                        if ($freshRecord->isApproved()
                            && $freshRecord->approval_mode === WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT) {
                            Notification::make()
                                ->title(__('withdraw.instant_submission_failed_after_approval'))
                                ->body(__('withdraw.instant_submission_failed_after_approval_help'))
                                ->warning()
                                ->send();

                            $this->redirect($this->getResource()::getUrl('view', [
                                'record' => $this->record,
                            ]));

                            return;
                        }

                        Notification::make()
                            ->title(__('filament.approve_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('reject')
                ->label(__('filament.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('audit', $record))
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.reject_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('audit', $this->record), 403);
                    try {
                        app(WithdrawService::class)->reject(
                            withdrawRequest: $this->record,
                            adminUser: Filament::auth()->user(),
                            adminRemark: $data['admin_remark'] ?? null,
                            source: 'filament',
                            ip: request()->ip(),
                        );

                        Notification::make()
                            ->title(__('filament.withdraw_rejected'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', [
                            'record' => $this->record,
                        ]));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title(__('filament.reject_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            ConfirmGatewayUnpaidAction::make(),
            MarkFailedPayoutPaidAction::make(),
            RevertManualApprovalAction::make(),
            Action::make('retry_payout')
                ->label(__('withdraw.retry_payout'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (WithdrawRequest $record): bool => $record->canRetryPayout())
                ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('retryGateway', $record))
                ->form([
                    Textarea::make('reason')
                        ->label(__('withdraw.retry_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('retryGateway', $this->record), 403);

                    try {
                        $attempt = app(Qc168WithdrawalPayoutService::class)->retry(
                            $this->record,
                            Filament::auth()->user(),
                            $data['reason'],
                            request()->ip(),
                        );

                        Notification::make()
                            ->title($attempt ? __('withdraw.retry_submitted') : __('withdraw.already_completed'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title(__('withdraw.retry_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel_payout')
                ->label(__('withdraw.cancel_payout'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (WithdrawRequest $record): bool => $record->canCancelPayout())
                ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('cancelGateway', $record))
                ->form([
                    Textarea::make('reason')
                        ->label(__('withdraw.cancellation_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('cancelGateway', $this->record), 403);

                    try {
                        app(Qc168WithdrawalPayoutService::class)->cancel(
                            $this->record,
                            Filament::auth()->user(),
                            $data['reason'],
                            request()->ip(),
                        );

                        Notification::make()
                            ->title(__('withdraw.cancelled_and_refunded'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title(__('withdraw.cancel_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('sync_gateway')
                ->label(__('withdraw.sync_gateway'))
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('info')
                ->visible(fn (WithdrawRequest $record): bool => $record->canSyncPayout())
                ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('syncGateway', $record))
                ->action(function (): void {
                    abort_unless(Gate::allows('syncGateway', $this->record), 403);

                    try {
                        app(Qc168WithdrawalPayoutService::class)->sync(
                            $this->record,
                            'filament',
                            Filament::auth()->user(),
                            ip: request()->ip(),
                        );

                        Notification::make()
                            ->title(__('withdraw.sync_complete'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title(__('withdraw.sync_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            DeleteAction::make()
                ->visible(fn (WithdrawRequest $record): bool => $record->status !== 'approved')
                ->requiresConfirmation()
                ->successNotificationTitle(__('filament.withdraw_request_deleted'))
                ->after(function (): void {
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}
