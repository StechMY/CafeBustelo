<?php

namespace App\Services\Settings;

use App\Models\AdminUser;
use App\Models\PaymentGatewayEvent;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class YdPaySettings
{
    public function enabled(string $business): bool
    {
        return Setting::bool('ydpay_'.$business.'_enabled', (bool) config("services.ydpay.{$business}_enabled", false));
    }

    public function releaseHours(): int
    {
        $hours = (int) Setting::getValue('ydpay_withdraw_release_hours', 24);

        return $hours >= 1 && $hours <= 720 ? $hours : 24;
    }

    public function depositExpiryMinutes(): int
    {
        $minutes = (int) Setting::getValue('ydpay_deposit_expiry_minutes', 60);

        return $minutes >= 1 && $minutes <= 1440 ? $minutes : 60;
    }

    public function formData(): array
    {
        return ['deposit_enabled' => $this->enabled('deposit'), 'withdraw_enabled' => $this->enabled('withdraw'),
            'withdraw_release_hours' => $this->releaseHours(), 'deposit_expiry_minutes' => $this->depositExpiryMinutes()];
    }

    public function save(array $data, AdminUser $actor, ?string $ip = null): void
    {
        abort_unless($actor->can(Qc168Settings::MANAGE_PERMISSION), 403);
        $values = Validator::make($data, ['deposit_enabled' => ['required', 'boolean'],
            'withdraw_enabled' => ['required', 'boolean'], 'withdraw_release_hours' => ['required', 'integer', 'between:1,720'],
            'deposit_expiry_minutes' => ['required', 'integer', 'between:1,1440']])->validate();
        DB::transaction(function () use ($values, $actor, $ip): void {
            $before = $this->formData();
            foreach ($values as $key => $value) {
                Setting::setValue('ydpay_'.$key, $value);
            }
            PaymentGatewayEvent::query()->create([
                'provider' => 'ydpay', 'event_type' => 'ydpay.settings.updated', 'source' => 'filament',
                'actor_admin_user_id' => $actor->id, 'payload' => ['ip' => $ip, 'old' => $before, 'new' => $values],
                'received_at' => now(), 'processed_at' => now(), 'processing_result' => 'saved',
            ]);
        });
    }
}
