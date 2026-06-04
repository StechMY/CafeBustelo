<?php

namespace App\Filament\Resources\VipLevels\Pages;

use App\Filament\Resources\VipLevels\VipLevelResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVipLevel extends EditRecord
{
    protected static string $resource = VipLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}