<?php

namespace App\Filament\Resources\WithdrawRequests\Pages;

use App\Filament\Resources\WithdrawRequests\WithdrawRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListWithdrawRequests extends ListRecords
{
    protected static string $resource = WithdrawRequestResource::class;

    protected string $view = 'filament.withdraw-requests.list';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getTablePollingInterval(): ?string
    {
        return '60s';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
