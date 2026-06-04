<?php

namespace App\Filament\Resources\RechargeRequests\Pages;

use App\Filament\Resources\RechargeRequests\RechargeRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRechargeRequests extends ListRecords
{
    protected static string $resource = RechargeRequestResource::class;

    protected function getTablePollingInterval(): ?string
    {
        return '10s';
    }

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}