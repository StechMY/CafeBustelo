<?php

namespace App\Filament\Resources\SalaryClaims\Pages;

use App\Filament\Resources\SalaryClaims\SalaryClaimResource;
use App\Support\FilamentSupportScope;
use Filament\Resources\Pages\ViewRecord;

class ViewSalaryClaim extends ViewRecord
{
    protected static string $resource = SalaryClaimResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless(
            FilamentSupportScope::canAccessUserId($this->record->user_id),
            403
        );
    }
}