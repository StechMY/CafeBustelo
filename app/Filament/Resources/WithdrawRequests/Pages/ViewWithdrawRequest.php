<?php

namespace App\Filament\Resources\WithdrawRequests\Pages;

use App\Filament\Resources\WithdrawRequests\WithdrawRequestResource;
use App\Models\WithdrawRequest;
use App\Services\WithdrawService;
use App\Support\FilamentSupportScope;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
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
            Action::make('approve')
                ->label(__('filament.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn(WithdrawRequest $record): bool => $record->status === 'pending')
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.admin_remark'))
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    try {
                        app(WithdrawService::class)->approve(
                            withdrawRequest: $this->record,
                            adminUser: Filament::auth()->user(),
                            adminRemark: $data['admin_remark'] ?? null,
                        );

                        Notification::make()
                            ->title(__('filament.withdraw_approved'))
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
                ->visible(fn(WithdrawRequest $record): bool => $record->status === 'pending')
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.reject_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    try {
                        app(WithdrawService::class)->reject(
                            withdrawRequest: $this->record,
                            adminUser: Filament::auth()->user(),
                            adminRemark: $data['admin_remark'] ?? null,
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

            DeleteAction::make()
                ->visible(fn(WithdrawRequest $record): bool => $record->status !== 'approved')
                ->requiresConfirmation()
                ->successNotificationTitle(__('filament.withdraw_request_deleted'))
                ->after(function (): void {
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}