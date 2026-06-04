<?php

namespace App\Filament\Resources\DashboardPopups\Pages;

use App\Filament\Resources\DashboardPopups\DashboardPopupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDashboardPopup extends ViewRecord
{
    protected static string $resource = DashboardPopupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
