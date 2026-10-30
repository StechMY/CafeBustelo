<?php

namespace App\Filament\Resources\TreasureChestClaims\Pages;

use App\Filament\Actions\TreasureChestSettingsAction;
use App\Filament\Resources\TreasureChestClaims\TreasureChestClaimResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTreasureChestClaims extends ManageRecords
{
    protected static string $resource = TreasureChestClaimResource::class;

    public function getSubheading(): ?string
    {
        return __('treasure_chest.admin_help', ['timezone' => config('app.timezone')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            TreasureChestSettingsAction::make('activity_settings')
                ->label(__('golden_egg.activity_settings'))->icon('heroicon-o-cog-6-tooth'),
        ];
    }
}
