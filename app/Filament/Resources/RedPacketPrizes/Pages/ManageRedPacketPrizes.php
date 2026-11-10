<?php

namespace App\Filament\Resources\RedPacketPrizes\Pages;

use App\Filament\Actions\RedPacketSettingsAction;
use App\Filament\Resources\RedPacketPrizes\RedPacketPrizeResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRedPacketPrizes extends ManageRecords
{
    protected static string $resource = RedPacketPrizeResource::class;

    protected function getHeaderActions(): array
    {
        return [RedPacketSettingsAction::make('activity_settings')];
    }
}
