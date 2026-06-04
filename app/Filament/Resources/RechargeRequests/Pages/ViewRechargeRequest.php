<?php

namespace App\Filament\Resources\RechargeRequests\Pages;

use App\Filament\Resources\RechargeRequests\RechargeRequestResource;
use App\Models\RechargeRequest;
use App\Services\RechargeApprovalService;
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

    protected function getHeaderActions(): array
    {
        return [
            // EditAction::make(),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (RechargeRequest $record): bool => $record->status === 'pending')
                ->form([
                    Textarea::make('admin_remark')
                        ->label('Admin Remark')
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
                            ->title('Recharge approved')
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
                ->visible(fn (RechargeRequest $record): bool => $record->status === 'pending')
                ->form([
                    Textarea::make('admin_remark')
                        ->label('Reject Reason')
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
                            ->title('Recharge rejected')
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
        ];
    }
}