<?php

namespace App\Filament\Resources\VipLevels\Pages;

use App\Filament\Resources\VipLevels\VipLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVipLevels extends ListRecords
{
    protected static string $resource = VipLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}