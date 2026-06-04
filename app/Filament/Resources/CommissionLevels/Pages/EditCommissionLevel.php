<?php

namespace App\Filament\Resources\CommissionLevels\Pages;

use App\Filament\Resources\CommissionLevels\CommissionLevelResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCommissionLevel extends EditRecord
{
    protected static string $resource = CommissionLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}