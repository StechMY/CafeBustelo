<?php

namespace App\Filament\Resources\SalaryClaimWindows\Pages;

use App\Filament\Resources\SalaryClaimWindows\SalaryClaimWindowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalaryClaimWindows extends ListRecords
{
    protected static string $resource = SalaryClaimWindowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}