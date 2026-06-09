<?php

namespace App\Filament\Resources\RechargeMethods\Pages;

use App\Filament\Resources\RechargeMethods\RechargeMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRechargeMethod extends EditRecord
{
    protected static string $resource = RechargeMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}