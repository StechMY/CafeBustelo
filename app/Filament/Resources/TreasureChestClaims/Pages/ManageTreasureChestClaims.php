<?php

namespace App\Filament\Resources\TreasureChestClaims\Pages;

use App\Filament\Resources\TreasureChestClaims\TreasureChestClaimResource;
use App\Models\Setting;
use App\Services\TreasureChestService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;

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
            Action::make('activity_settings')->label(__('golden_egg.activity_settings'))->icon('heroicon-o-cog-6-tooth')
                ->authorize(fn (): bool => TreasureChestClaimResource::canViewAny())
                ->fillForm(fn (): array => [
                    'enabled' => Setting::bool(TreasureChestService::SETTING_ENABLED),
                    'starts_at' => Setting::getValue(TreasureChestService::SETTING_START) ?: now()->format('Y-m-d H:i:s'),
                    'ends_at' => Setting::getValue(TreasureChestService::SETTING_END) ?: null,
                ])
                ->schema([
                    Toggle::make('enabled')->label(__('golden_egg.enable_activity')),
                    DateTimePicker::make('starts_at')->label(__('treasure_chest.starts_at'))
                        ->format('Y-m-d H:i:s')->timezone(config('app.timezone'))->required()->seconds(false),
                    DateTimePicker::make('ends_at')->label(__('treasure_chest.ends_at'))
                        ->format('Y-m-d H:i:s')->timezone(config('app.timezone'))->nullable()->after('starts_at')->seconds(false),
                ])->action(function (array $data): void {
                    DB::transaction(function () use ($data): void {
                        Setting::setValue(TreasureChestService::SETTING_START, $data['starts_at']);
                        Setting::setValue(TreasureChestService::SETTING_END, $data['ends_at'] ?? '');
                        Setting::setValue(TreasureChestService::SETTING_ENABLED, (bool) $data['enabled']);
                    });
                    Notification::make()->title(__('golden_egg.settings_saved'))->success()->send();
                }),
        ];
    }
}
