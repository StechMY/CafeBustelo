<?php

namespace App\Filament\Resources\BonusCodes\Pages;

use App\Filament\Resources\BonusCodes\BonusCodeResource;
use App\Models\BonusCode;
use App\Services\BonusCodeService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewBonusCode extends ViewRecord
{
    protected static string $resource = BonusCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (BonusCode $record): bool => ! $record->isUsed()),

            Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (BonusCode $record): bool => $record->status === BonusCode::STATUS_UNUSED)
                ->form([
                    Textarea::make('remark')
                        ->label('Cancel Remark')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    try {
                        app(BonusCodeService::class)->cancel(
                            bonusCode: $this->record,
                            remark: $data['remark'] ?? null,
                        );

                        Notification::make()
                            ->title('Bonus code cancelled')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', [
                            'record' => $this->record,
                        ]));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Cancel failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}