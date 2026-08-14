<?php

namespace App\Filament\Resources\UserProducts\Pages;

use App\Filament\Resources\UserProducts\UserProductResource;
use App\Support\FilamentSupportScope;
use Filament\Resources\Pages\ViewRecord;

class ViewUserProduct extends ViewRecord
{
    protected static string $resource = UserProductResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless(
            FilamentSupportScope::canAccessUserId($this->record->user_id),
            403,
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
