<?php

namespace App\Filament\Resources\SalaryClaims\Pages;

use App\Filament\Resources\SalaryClaims\SalaryClaimResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalaryClaim extends EditRecord
{
    protected static string $resource = SalaryClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
