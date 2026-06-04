<?php

namespace App\Filament\Resources\SalaryClaimWindows\Pages;

use App\Filament\Resources\SalaryClaimWindows\SalaryClaimWindowResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalaryClaimWindow extends ViewRecord
{
    protected static string $resource = SalaryClaimWindowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}