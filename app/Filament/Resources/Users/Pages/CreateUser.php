<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Wallet;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        Wallet::query()->firstOrCreate([
            'user_id' => $this->record->id,
            'type' => Wallet::TYPE_RECHARGE,
        ], [
            'balance' => 0,
            'locked_balance' => 0,
        ]);

        Wallet::query()->firstOrCreate([
            'user_id' => $this->record->id,
            'type' => Wallet::TYPE_WITHDRAWABLE,
        ], [
            'balance' => 0,
            'locked_balance' => 0,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}