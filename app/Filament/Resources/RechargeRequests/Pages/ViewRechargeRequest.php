<?php

namespace App\Filament\Resources\RechargeRequests\Pages;

use App\Filament\Resources\RechargeRequests\RechargeRequestResource;
use App\Filament\Resources\RechargeRequests\Actions\ReverseManualRechargeAction;
use App\Models\RechargeRequest;
use App\Services\PaymentGateway\Qc168CallbackService;
use App\Services\RechargeApprovalService;
use App\Support\FilamentSupportScope;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ViewRechargeRequest extends ViewRecord
{
    protected static string $resource = RechargeRequestResource::class;

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
            // EditAction::make(),

            Action::make('syncGateway')
                ->label(__('filament.sync_gateway_status'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn (RechargeRequest $record): bool => $record->isGatewayDeposit())
                ->authorize(fn (RechargeRequest $record): bool => Gate::allows('syncGateway', $record))
                ->requiresConfirmation()
                ->action(function (): void {
                    abort_unless(Gate::allows('syncGateway', $this->record), 403);

                    try {
                        $result = app(Qc168CallbackService::class)->sync($this->record);

                        if (! $result['accepted']) {
                            Notification::make()
                                ->title(__('filament.gateway_sync_failed'))
                                ->body($result['result'])
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('filament.gateway_status_synced'))
                            ->body($result['result'])
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title(__('filament.gateway_sync_failed'))
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('approve')
                ->label(__('filament.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (RechargeRequest $record): bool => $record->status === 'pending' && $record->isManualDeposit())
                ->authorize(fn (RechargeRequest $record): bool => Gate::allows('audit', $record))
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.admin_remark'))
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('audit', $this->record), 403);
                    try {
                        app(RechargeApprovalService::class)->approve(
                            rechargeRequest: $this->record,
                            adminUser: Filament::auth()->user(),
                            adminRemark: $data['admin_remark'] ?? null,
                        );

                        Notification::make()
                            ->title(__('filament.recharge_approved'))
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
            ReverseManualRechargeAction::make(),

            Action::make('reject')
                ->label(__('filament.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (RechargeRequest $record): bool => $record->status === 'pending' && $record->isManualDeposit())
                ->authorize(fn (RechargeRequest $record): bool => Gate::allows('audit', $record))
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
                        app(RechargeApprovalService::class)->reject(
                            rechargeRequest: $this->record,
                            adminUser: Filament::auth()->user(),
                            adminRemark: $data['admin_remark'] ?? null,
                        );

                        Notification::make()
                            ->title(__('filament.recharge_rejected'))
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
        ];
    }
}
