<?php

namespace App\Filament\Resources\DashboardSlides\Pages;

use App\Filament\Resources\DashboardSlides\DashboardSlideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDashboardSlides extends ListRecords
{
    protected static string $resource = DashboardSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}