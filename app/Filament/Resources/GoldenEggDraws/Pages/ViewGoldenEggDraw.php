<?php

namespace App\Filament\Resources\GoldenEggDraws\Pages;

use App\Filament\Resources\GoldenEggDraws\GoldenEggDrawResource;
use Filament\Resources\Pages\ViewRecord;

class ViewGoldenEggDraw extends ViewRecord
{
    protected static string $resource = GoldenEggDrawResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
