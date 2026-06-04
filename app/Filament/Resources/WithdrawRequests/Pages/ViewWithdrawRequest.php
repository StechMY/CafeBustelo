<?php

namespace App\Filament\Resources\WithdrawRequests\Pages;

use App\Filament\Resources\WithdrawRequests\WithdrawRequestResource;
use App\Models\WithdrawRequest;
use App\Services\WithdrawService;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                ->form([
                    Textarea::make('admin_remark')
                        ->label('Admin Remark')
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
                            ->title('Withdraw approved')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', [
                            'record' => $this->record,
                        ]));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Approve failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (WithdrawRequest $record): bool => $record->status === 'pending')
                ->form([
                    Textarea::make('admin_remark')
                        ->label('Reject Reason')
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
                            ->title('Withdraw rejected')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', [
                            'record' => $this->record,
                        ]));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Reject failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            DeleteAction::make()
                ->visible(fn (WithdrawRequest $record): bool => $record->status !== 'approved')
                ->requiresConfirmation()
                ->successNotificationTitle('Withdraw request deleted')
                ->after(function (): void {
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}