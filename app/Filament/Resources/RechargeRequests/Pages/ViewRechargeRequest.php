<?php

namespace App\Filament\Resources\RechargeRequests\Pages;

use App\Filament\Resources\RechargeRequests\RechargeRequestResource;
use App\Models\RechargeRequest;
use App\Services\RechargeApprovalService;
use App\Support\FilamentSupportScope;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
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

            Action::make('approve')
                ->label(__('filament.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn(RechargeRequest $record): bool => $record->status === 'pending')
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.admin_remark'))
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
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

            Action::make('reject')
                ->label(__('filament.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn(RechargeRequest $record): bool => $record->status === 'pending')
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.reject_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
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