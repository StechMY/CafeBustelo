<?php

namespace App\Filament\Resources\CommissionLevels\Pages;

use App\Filament\Resources\CommissionLevels\CommissionLevelResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCommissionLevel extends ViewRecord
{
    protected static string $resource = CommissionLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}