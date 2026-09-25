<?php

namespace App\Filament\Resources\WithdrawRequests\Actions;

use App\Models\WithdrawRequest;
use App\Services\WithdrawService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class RevertManualApprovalAction
{
    public static function make(): Action
    {
        return Action::make('revert_manual_approval')
            ->label(__('withdraw.revert_manual_approval'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (WithdrawRequest $record): bool => $record->canRevertManualApproval())
            ->authorize(fn (WithdrawRequest $record): bool => Gate::allows('revertManualApproval', $record))
            ->modalDescription(__('withdraw.revert_manual_description'))
            ->form([
                Hidden::make('approval_event_id')
                    ->default(fn (WithdrawRequest $record): ?int => $record->manualApprovalEvents()->latest('id')->value('id'))
                    ->required(),
                Textarea::make('reason')
                    ->label(__('withdraw.manual_resolution_reason'))
                    ->required()
                    ->maxLength(1000),
                Checkbox::make('confirmed_unpaid')
                    ->label(__('withdraw.revert_manual_acknowledgement'))
                    ->accepted()
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(function (WithdrawRequest $record, array $data): void {
                abort_unless(Gate::allows('revertManualApproval', $record), 403);
                try {
                    app(WithdrawService::class)->revertManualApproval(
                        $record,
                        Filament::auth()->user(),
                        (int) $data['approval_event_id'],
                        $data['reason'],
                        (bool) ($data['confirmed_unpaid'] ?? false),
                        request()->ip(),
                    );
                    $record->refresh();
                    Notification::make()->title(__('withdraw.revert_manual_success'))->success()->send();
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('withdraw.revert_manual_failed'))
                        ->body($exception->getMessage())->danger()->send();
                }
            });
    }
}
