<?php

namespace App\Filament\Actions;

use App\Models\AdminUser;
use App\Services\PaymentGateway\YdPayGateway;
use App\Services\Settings\Qc168Settings;
use App\Services\Settings\YdPaySettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

class YdPaySettingsAction
{
    public static function make(): Action
    {
        return Action::make('ydpaySettings')
            ->label('YDPAY')
            ->icon('heroicon-o-banknotes')
            ->color(fn (): string => app(YdPayGateway::class)->isAvailable('deposit') || app(YdPayGateway::class)->isAvailable('withdraw') ? 'success' : 'gray')
            ->visible(fn (): bool => Filament::auth()->user()?->can(Qc168Settings::MANAGE_PERMISSION) ?? false)
            ->authorize(fn (): bool => Filament::auth()->user()?->can(Qc168Settings::MANAGE_PERMISSION) ?? false)
            ->modalHeading('YDPAY · '.__('filament.payment_gateway_settings'))
            ->fillForm(fn (): array => app(YdPaySettings::class)->formData())
            ->form([
                Toggle::make('deposit_enabled')->label(__('filament.qc168_automatic_deposit'))->required(),
                Toggle::make('withdraw_enabled')->label(__('filament.qc168_automatic_withdrawal'))->required(),
                TextInput::make('withdraw_release_hours')->label(__('filament.qc168_withdraw_release_hours'))
                    ->helperText(str_replace('QC168', 'YDPAY', __('filament.qc168_withdraw_release_hours_help')))
                    ->suffix(__('filament.hours'))->numeric()->integer()->minValue(1)->maxValue(720)->required(),
            ])
            ->action(function (array $data): void {
                $admin = Filament::auth()->user();
                abort_unless($admin instanceof AdminUser, 403);
                app(YdPaySettings::class)->save($data, $admin, request()->ip());
                Notification::make()->title(__('filament.payment_gateway_settings_saved'))->success()->send();
            });
    }
}
