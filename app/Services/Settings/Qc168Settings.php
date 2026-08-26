<?php

namespace App\Services\Settings;

use App\Models\AdminUser;
use App\Models\Setting;
use App\Services\PaymentGateway\PaymentGatewayEventRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Qc168Settings
{
    public const MANAGE_PERMISSION = 'ManagePaymentGateway:Settings';

    public const KEY_DEPOSIT_ENABLED = 'qc168_deposit_enabled';

    public const KEY_WITHDRAW_ENABLED = 'qc168_withdraw_enabled';

    public const KEY_WITHDRAW_RELEASE_HOURS = 'qc168_withdraw_release_hours';

    public const DEFAULT_DEPOSIT_ENABLED = true;

    public const DEFAULT_WITHDRAW_ENABLED = false;

    public const DEFAULT_WITHDRAW_RELEASE_HOURS = 24;

    public function __construct(
        private readonly PaymentGatewayEventRecorder $eventRecorder,
    ) {}

    public function depositEnabled(): bool
    {
        return Setting::bool(self::KEY_DEPOSIT_ENABLED, self::DEFAULT_DEPOSIT_ENABLED);
    }

    public function withdrawEnabled(): bool
    {
        return Setting::bool(self::KEY_WITHDRAW_ENABLED, self::DEFAULT_WITHDRAW_ENABLED);
    }

    public function withdrawReleaseHours(): int
    {
        $value = filter_var(
            Setting::getValue(
                self::KEY_WITHDRAW_RELEASE_HOURS,
                self::DEFAULT_WITHDRAW_RELEASE_HOURS,
            ),
            FILTER_VALIDATE_INT,
        );

        return $value !== false && $value >= 1 && $value <= 720
            ? $value
            : self::DEFAULT_WITHDRAW_RELEASE_HOURS;
    }

    /** @return array{deposit_enabled: bool, withdraw_enabled: bool, withdraw_release_hours: int} */
    public function formData(): array
    {
        return [
            'deposit_enabled' => $this->depositEnabled(),
            'withdraw_enabled' => $this->withdrawEnabled(),
            'withdraw_release_hours' => $this->withdrawReleaseHours(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data, AdminUser $actor, ?string $ip = null): void
    {
        if (! $actor->can(self::MANAGE_PERMISSION)) {
            throw new AuthorizationException;
        }

        $values = $this->validated($data);
        $oldValues = $this->formData();

        DB::transaction(function () use ($values, $oldValues, $actor, $ip): void {
            Setting::setValue(self::KEY_DEPOSIT_ENABLED, $values['deposit_enabled']);
            Setting::setValue(self::KEY_WITHDRAW_ENABLED, $values['withdraw_enabled']);
            Setting::setValue(self::KEY_WITHDRAW_RELEASE_HOURS, $values['withdraw_release_hours']);

            if ($oldValues !== $values) {
                $this->eventRecorder->recordSettingsChange(
                    actor: $actor,
                    ip: $ip,
                    oldValues: $oldValues,
                    newValues: $values,
                );
            }
        });
    }

    /** @param array<string, mixed> $data
     * @return array{deposit_enabled: bool, withdraw_enabled: bool, withdraw_release_hours: int}
     */
    private function validated(array $data): array
    {
        $depositEnabled = $this->submittedBoolean($data, 'deposit_enabled');
        $withdrawEnabled = $this->submittedBoolean($data, 'withdraw_enabled');
        $releaseHours = filter_var(
            $data['withdraw_release_hours'] ?? null,
            FILTER_VALIDATE_INT,
        );
        $errors = [];

        if ($depositEnabled === null) {
            $errors['deposit_enabled'][] = __('filament.qc168_validation_boolean');
        }

        if ($withdrawEnabled === null) {
            $errors['withdraw_enabled'][] = __('filament.qc168_validation_boolean');
        }

        if ($releaseHours === false || $releaseHours < 1 || $releaseHours > 720) {
            $errors['withdraw_release_hours'][] = __('filament.qc168_validation_integer_range', [
                'min' => 1,
                'max' => 720,
            ]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'deposit_enabled' => $depositEnabled,
            'withdraw_enabled' => $withdrawEnabled,
            'withdraw_release_hours' => $releaseHours,
        ];
    }

    /** @param array<string, mixed> $data */
    private function submittedBoolean(array $data, string $key): ?bool
    {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        return filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
