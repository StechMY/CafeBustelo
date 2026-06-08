<?php

namespace App\Filament\Resources\AdminUsers\Pages;

use App\Filament\Resources\AdminUsers\AdminUserResource;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /**
         * Prevent non-super-admin from changing important fields if they somehow access edit URL.
         */
        if (! auth('admin')->user()?->hasRole('super_admin')) {
            unset($data['roles']);
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $record = $this->record;

        if ($record->hasRole('super_admin') && ! auth('admin')->user()?->hasRole('super_admin')) {
            Notification::make()
                ->title(__('filament.you_cannot_edit_super_admin'))
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}