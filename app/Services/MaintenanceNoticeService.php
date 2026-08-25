<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Throwable;

class MaintenanceNoticeService
{
    public const SETTING_ENABLED = 'maintenance_notice_enabled';

    public const SETTING_STARTS_AT = 'maintenance_notice_starts_at';

    public const SETTING_ENDS_AT = 'maintenance_notice_ends_at';

    public const SETTING_REVISION = 'maintenance_notice_revision';

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null
     */
    public function schedule(): ?array
    {
        if (! Setting::bool(self::SETTING_ENABLED, false)) {
            return null;
        }

        $startsAtValue = Setting::getValue(self::SETTING_STARTS_AT);
        $endsAtValue = Setting::getValue(self::SETTING_ENDS_AT);

        if (blank($startsAtValue) || blank($endsAtValue)) {
            return null;
        }

        try {
            $startsAt = CarbonImmutable::parse($startsAtValue);
            $endsAt = CarbonImmutable::parse($endsAtValue);
        } catch (Throwable) {
            return null;
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return null;
        }

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    public function sessionKey(): string
    {
        $revision = (string) Setting::getValue(self::SETTING_REVISION, 'initial');

        return 'maintenance_notice_seen_'.hash('sha256', $revision);
    }
}
