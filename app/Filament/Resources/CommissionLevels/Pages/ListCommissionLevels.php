<?php

namespace App\Filament\Resources\CommissionLevels\Pages;

use App\Filament\Resources\CommissionLevels\CommissionLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommissionLevels extends ListRecords
{
    protected static string $resource = CommissionLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}