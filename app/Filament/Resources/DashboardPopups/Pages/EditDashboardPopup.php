<?php

namespace App\Filament\Resources\DashboardPopups\Pages;

use App\Filament\Resources\DashboardPopups\DashboardPopupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDashboardPopup extends EditRecord
{
    protected static string $resource = DashboardPopupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
