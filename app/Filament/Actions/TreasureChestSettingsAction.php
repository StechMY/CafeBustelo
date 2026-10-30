<?php

namespace App\Filament\Actions;

use App\Filament\Pages\TreasureChestConfiguration;
use App\Filament\Resources\TreasureChestClaims\TreasureChestClaimResource;
use App\Models\Setting;
use App\Services\TreasureChestService;
use App\Services\Settings\TreasureChestSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class TreasureChestSettingsAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('treasure_chest.admin_title'))
            ->icon('heroicon-o-gift')
            ->color('warning')
            ->visible(fn (): bool => TreasureChestClaimResource::canViewAny())
            ->authorize(fn (): bool => TreasureChestClaimResource::canViewAny())
            ->modalHeading(__('treasure_chest.admin_title'))
            ->modalDescription(__('treasure_chest.admin_help', ['timezone' => config('app.timezone')]))
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('filament-actions::edit.single.modal.actions.save.label'))
            ->fillForm(fn (): array => [
                'enabled' => Setting::bool(TreasureChestService::SETTING_ENABLED),
                'show_rewards' => Setting::bool(TreasureChestSettings::SHOW_REWARDS),
                'starts_at' => Setting::getValue(TreasureChestService::SETTING_START) ?: now()->format('Y-m-d H:i:s'),
                'ends_at' => Setting::getValue(TreasureChestService::SETTING_END) ?: null,
            ])
            ->schema([
                Toggle::make('enabled')->label(__('treasure_chest.enable_activity')),
                Toggle::make('show_rewards')->label(__('treasure_chest.show_rewards'))
                    ->helperText(__('treasure_chest.show_rewards_help')),
                DateTimePicker::make('starts_at')->label(__('treasure_chest.starts_at'))
                    ->format('Y-m-d H:i:s')->timezone(config('app.timezone'))->required()->seconds(false),
                DateTimePicker::make('ends_at')->label(__('treasure_chest.ends_at'))
                    ->format('Y-m-d H:i:s')->timezone(config('app.timezone'))->nullable()->after('starts_at')->seconds(false),
            ])
            ->extraModalFooterActions([
                Action::make('configureTreasureChests')->label(__('treasure_chest.configuration'))
                    ->url(fn (): string => TreasureChestConfiguration::getUrl())->color('gray'),
            ])
            ->action(function (array $data): void {
                DB::transaction(function () use ($data): void {
                    Setting::setValue(TreasureChestService::SETTING_START, $data['starts_at']);
                    Setting::setValue(TreasureChestService::SETTING_END, $data['ends_at'] ?? '');
                    Setting::setValue(TreasureChestService::SETTING_ENABLED, (bool) $data['enabled']);
                    Setting::setValue(TreasureChestSettings::SHOW_REWARDS, (bool) ($data['show_rewards'] ?? false));
                });
                Notification::make()->title(__('golden_egg.settings_saved'))->success()->send();
            });
    }
}
