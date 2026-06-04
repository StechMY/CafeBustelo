<?php

namespace App\Filament\Resources\DashboardMarquees\Pages;

use App\Filament\Resources\DashboardMarquees\DashboardMarqueeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDashboardMarquees extends ListRecords
{
    protected static string $resource = DashboardMarqueeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
