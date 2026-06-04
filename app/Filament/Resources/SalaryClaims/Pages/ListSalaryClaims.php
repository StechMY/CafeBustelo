<?php

namespace App\Filament\Resources\SalaryClaims\Pages;

use App\Filament\Resources\SalaryClaims\SalaryClaimResource;
use Filament\Resources\Pages\ListRecords;

class ListSalaryClaims extends ListRecords
{
    protected static string $resource = SalaryClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}