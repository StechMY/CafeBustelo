<?php

namespace App\Filament\Resources\DashboardPopups\Pages;

use App\Filament\Resources\DashboardPopups\DashboardPopupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDashboardPopups extends ListRecords
{
    protected static string $resource = DashboardPopupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
