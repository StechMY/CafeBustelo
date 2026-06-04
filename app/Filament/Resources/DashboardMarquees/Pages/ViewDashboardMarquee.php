<?php

namespace App\Filament\Resources\DashboardMarquees\Pages;

use App\Filament\Resources\DashboardMarquees\DashboardMarqueeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDashboardMarquee extends ViewRecord
{
    protected static string $resource = DashboardMarqueeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
