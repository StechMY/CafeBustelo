<?php

namespace App\Filament\Resources\DashboardSlides\Pages;

use App\Filament\Resources\DashboardSlides\DashboardSlideResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDashboardSlide extends EditRecord
{
    protected static string $resource = DashboardSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}