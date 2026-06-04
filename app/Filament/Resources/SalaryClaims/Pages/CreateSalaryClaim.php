<?php

namespace App\Filament\Resources\SalaryClaims\Pages;

use App\Filament\Resources\SalaryClaims\SalaryClaimResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalaryClaim extends CreateRecord
{
    protected static string $resource = SalaryClaimResource::class;
}
