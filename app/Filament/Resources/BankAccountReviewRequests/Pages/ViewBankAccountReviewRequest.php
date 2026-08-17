<?php

namespace App\Filament\Resources\BankAccountReviewRequests\Pages;

use App\Filament\Resources\BankAccountReviewRequests\BankAccountReviewRequestResource;
use App\Models\BankAccountReviewRequest;
use App\Services\BankAccountReviewService;
use App\Support\FilamentSupportScope;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ViewBankAccountReviewRequest extends ViewRecord
{
    protected static string $resource = BankAccountReviewRequestResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless(FilamentSupportScope::canAccessUserId($this->record->user_id), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('filament.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (BankAccountReviewRequest $record): bool => $record->isPending())
                ->authorize(fn (BankAccountReviewRequest $record): bool => Gate::allows('update', $record))
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.admin_remark'))
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('update', $this->record), 403);

                    try {
                        app(BankAccountReviewService::class)->approve(
                            $this->record,
                            Filament::auth()->user(),
                            $data['admin_remark'] ?? null,
                        );

                        Notification::make()
                            ->title(__('filament.bank_account_review_approved'))
                            ->success()
                            ->send();

                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
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
                ->visible(fn (BankAccountReviewRequest $record): bool => $record->isPending())
                ->authorize(fn (BankAccountReviewRequest $record): bool => Gate::allows('update', $record))
                ->form([
                    Textarea::make('admin_remark')
                        ->label(__('filament.reject_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('update', $this->record), 403);

                    try {
                        app(BankAccountReviewService::class)->reject(
                            $this->record,
                            Filament::auth()->user(),
                            $data['admin_remark'],
                        );

                        Notification::make()
                            ->title(__('filament.bank_account_review_rejected'))
                            ->success()
                            ->send();

                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
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
