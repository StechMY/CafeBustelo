<?php

namespace App\Filament\Resources\GoldenEggPrizes\Pages;

use App\Filament\Resources\GoldenEggPrizes\GoldenEggPrizeResource;
use App\Models\Setting;
use App\Services\GoldenEggService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageGoldenEggPrizes extends ManageRecords
{
    protected static string $resource = GoldenEggPrizeResource::class;

    public function getSubheading(): ?string
    {
        return __('golden_egg.admin_help');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activity_settings')->label(__('golden_egg.activity_settings'))->icon('heroicon-o-cog-6-tooth')
                ->visible(fn (): bool => Filament::auth()->user()?->can('Manage:GoldenEgg') ?? false)
                ->authorize(fn (): bool => Filament::auth()->user()?->can('Manage:GoldenEgg') ?? false)
                ->fillForm(fn (): array => ['enabled' => Setting::bool(GoldenEggService::SETTING_ENABLED)])
                ->schema([
                    Toggle::make('enabled')->label(__('golden_egg.enable_activity'))
                        ->helperText(__('golden_egg.enable_help')),
                ])->action(function (array $data): void {
                    Setting::setValue(GoldenEggService::SETTING_ENABLED, (bool) $data['enabled']);
                    Notification::make()->title(__('golden_egg.settings_saved'))->success()->send();
                }),
        ];
    }
}
