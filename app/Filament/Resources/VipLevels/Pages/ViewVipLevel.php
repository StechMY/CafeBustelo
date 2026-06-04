<?php

namespace App\Filament\Resources\VipLevels\Pages;

use App\Filament\Resources\VipLevels\VipLevelResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVipLevel extends ViewRecord
{
    protected static string $resource = VipLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}