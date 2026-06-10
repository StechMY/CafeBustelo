<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\FilamentSupportScope;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless(FilamentSupportScope::canAccessUser($this->record), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', [
            'record' => $this->record,
        ]);
    }
}