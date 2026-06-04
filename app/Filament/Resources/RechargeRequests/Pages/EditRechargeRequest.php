<?php

namespace App\Filament\Resources\RechargeRequests\Pages;

use App\Filament\Resources\RechargeRequests\RechargeRequestResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRechargeRequest extends EditRecord
{
    protected static string $resource = RechargeRequestResource::class;

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