<?php

namespace App\Filament\Resources\WithdrawRequests\Pages;

use App\Filament\Resources\WithdrawRequests\WithdrawRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListWithdrawRequests extends ListRecords
{
    protected static string $resource = WithdrawRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}