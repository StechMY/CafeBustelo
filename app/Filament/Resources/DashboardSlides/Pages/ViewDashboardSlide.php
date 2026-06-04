<?php

namespace App\Filament\Resources\DashboardSlides\Pages;

use App\Filament\Resources\DashboardSlides\DashboardSlideResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDashboardSlide extends ViewRecord
{
    protected static string $resource = DashboardSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}