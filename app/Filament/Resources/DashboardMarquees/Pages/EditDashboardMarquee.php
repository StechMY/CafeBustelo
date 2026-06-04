<?php

namespace App\Filament\Resources\DashboardMarquees\Pages;

use App\Filament\Resources\DashboardMarquees\DashboardMarqueeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDashboardMarquee extends EditRecord
{
    protected static string $resource = DashboardMarqueeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
