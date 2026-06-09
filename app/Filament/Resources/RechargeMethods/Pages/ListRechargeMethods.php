<?php

namespace App\Filament\Resources\RechargeMethods\Pages;

use App\Filament\Resources\RechargeMethods\RechargeMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRechargeMethods extends ListRecords
{
    protected static string $resource = RechargeMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}