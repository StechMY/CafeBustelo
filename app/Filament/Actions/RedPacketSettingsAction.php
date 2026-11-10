<?php

namespace App\Filament\Actions;

use App\Filament\Resources\RedPacketPrizes\RedPacketPrizeResource;
use App\Models\Setting;
use App\Services\RedPacketService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class RedPacketSettingsAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->label(__('red_packet.title'))->icon('heroicon-o-envelope')->color('danger')
            ->visible(fn (): bool => $this->canManageActivity())
            ->authorize(fn (): bool => $this->canManageActivity())
            ->modalDescription(__('red_packet.admin_help', ['timezone' => config('app.timezone')]))
            ->fillForm(fn (): array => [
                'enabled' => Setting::bool(RedPacketService::SETTING_ENABLED),
                'starts_at' => Setting::getValue(RedPacketService::SETTING_START) ?: '2026-11-15 00:00:00',
                'ends_at' => Setting::getValue(RedPacketService::SETTING_END),
            ])->schema([
                Toggle::make('enabled')->label(__('red_packet.enable_activity')),
                DateTimePicker::make('starts_at')->label(__('red_packet.starts_at'))->required()
                    ->format('Y-m-d H:i:s')->timezone(config('app.timezone'))->seconds(false),
                DateTimePicker::make('ends_at')->label(__('red_packet.ends_at'))->nullable()->after('starts_at')
                    ->format('Y-m-d H:i:s')->timezone(config('app.timezone'))->seconds(false),
            ])->extraModalFooterActions([
                Action::make('prizes')->label(__('red_packet.manage_prizes'))->color('gray')
                    ->visible(fn (): bool => RedPacketPrizeResource::canViewAny())->url(fn (): string => RedPacketPrizeResource::getUrl()),
            ])->action(function (array $data): void {
                DB::transaction(function () use ($data): void {
                    Setting::setValue(RedPacketService::SETTING_START, $data['starts_at']);
                    Setting::setValue(RedPacketService::SETTING_END, $data['ends_at'] ?? '');
                    Setting::setValue(RedPacketService::SETTING_ENABLED, (bool) $data['enabled']);
                });
                Notification::make()->title(__('red_packet.settings_saved'))->success()->send();
            });
    }

    private function canManageActivity(): bool
    {
        $admin = Filament::auth()->user();

        return $admin && ($admin->isSuperAdmin() || $admin->can('Manage:RedPacket'));
    }
}
