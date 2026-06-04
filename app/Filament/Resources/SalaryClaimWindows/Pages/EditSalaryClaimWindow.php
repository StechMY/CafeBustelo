<?php

namespace App\Filament\Resources\SalaryClaimWindows\Pages;

use App\Filament\Resources\SalaryClaimWindows\SalaryClaimWindowResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalaryClaimWindow extends EditRecord
{
    protected static string $resource = SalaryClaimWindowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}