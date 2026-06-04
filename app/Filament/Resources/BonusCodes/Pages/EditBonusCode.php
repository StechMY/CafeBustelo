<?php

namespace App\Filament\Resources\BonusCodes\Pages;

use App\Filament\Resources\BonusCodes\BonusCodeResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBonusCode extends EditRecord
{
    protected static string $resource = BonusCodeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->isUsed()) {
            unset(
                $data['code'],
                $data['owner_user_id'],
                $data['type'],
                $data['amount'],
                $data['status'],
                $data['expires_at']
            );
        }

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', [
            'record' => $this->record,
        ]);
    }
}