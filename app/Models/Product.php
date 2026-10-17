<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    public const TYPE_NORMAL = 'normal';

    public const TYPE_UPGRADE_DAY = 'upgrade_day';

    public const TYPE_LIMITED_TIME = 'limited_time';

    public const TYPE_INVITE_DAY = 'invite_day';

    public const TYPE_WEEKEND_SPECIAL = 'weekend_special';

    public const TYPE_HOURLY = 'hourly';

    protected $attributes = [
        'allow_coupons' => true,
    ];

    protected $fillable = [
        'name',
        'code',
        'product_type',
        'available_days',
        'available_from',
        'available_until',
        'stock_quantity',
        'daily_limit',
        'sold_quantity',
        'claim_interval_hours',
        'total_claims',
        'extra_points',
        'price',
        'allow_coupons',
        'image_path',
        'daily_income',
        'duration_days',
        'upgrade_bonus',
        'invite_bonus',
        'max_purchase_per_user',
        'min_vip_level',
        'status',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'allow_coupons' => 'boolean',
            'daily_income' => 'decimal:2',
            'upgrade_bonus' => 'decimal:2',
            'invite_bonus' => 'decimal:2',
            'duration_days' => 'integer',
            'sort_order' => 'integer',
            'max_purchase_per_user' => 'integer',
            'min_vip_level' => 'integer',
            'available_days' => 'array',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'stock_quantity' => 'integer',
            'daily_limit' => 'integer',
            'sold_quantity' => 'integer',
            'claim_interval_hours' => 'integer',
            'total_claims' => 'integer',
            'extra_points' => 'integer',
            'meta' => 'array',
        ];
    }

    public function userProducts(): HasMany
    {
        return $this->hasMany(UserProduct::class);
    }

    public function dailyIncomeClaims(): HasMany
    {
        return $this->hasMany(DailyIncomeClaim::class);
    }

    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_NORMAL => __('filament.product_type_normal'),
            self::TYPE_UPGRADE_DAY => __('filament.product_type_upgrade_day'),
            self::TYPE_LIMITED_TIME => __('filament.product_type_limited_time'),
            self::TYPE_INVITE_DAY => __('filament.product_type_invite_day'),
            self::TYPE_WEEKEND_SPECIAL => __('filament.product_type_weekend_special'),
            self::TYPE_HOURLY => __('filament.product_type_hourly'),
        ];
    }

    public static function typeLabel(?string $type): string
    {
        if (! $type) {
            return '';
        }

        $key = 'products.types.'.$type;
        $label = __($key);

        return $label !== $key ? $label : Str::of($type)->headline()->toString();
    }

    public static function weekdayOptions(): array
    {
        return [
            1 => __('filament.monday'),
            2 => __('filament.tuesday'),
            3 => __('filament.wednesday'),
            4 => __('filament.thursday'),
            5 => __('filament.friday'),
            6 => __('filament.saturday'),
            7 => __('filament.sunday'),
        ];
    }

    public function maxPurchasePerUser(): int
    {
        return max(1, (int) ($this->max_purchase_per_user ?: 2));
    }

    public function effectiveClaimIntervalHours(): int
    {
        return max(1, (int) ($this->claim_interval_hours ?: 24));
    }

    public function meetsVipLevelRequirement(?User $user): bool
    {
        return $this->min_vip_level === null
            || ($user !== null && $user->current_vip_level_number >= $this->min_vip_level);
    }

    public function effectiveTotalClaims(): int
    {
        if ($this->total_claims !== null && (int) $this->total_claims > 0) {
            return (int) $this->total_claims;
        }

        $durationDays = max(1, (int) $this->duration_days);
        $claimIntervalHours = $this->effectiveClaimIntervalHours();

        return max(1, (int) floor(($durationDays * 24) / $claimIntervalHours));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isWithinAvailablePeriod(): bool
    {
        // Existing datetime columns are retained, but dates no longer restrict sales.
        $time = now()->format('H:i:s');
        $from = $this->available_from?->format('H:i:s');
        $until = $this->available_until?->format('H:i:s');

        if ($from !== null && $until !== null && $from > $until) {
            return $time >= $from || $time <= $until;
        }

        return ($from === null || $time >= $from)
            && ($until === null || $time <= $until);
    }

    public function isAvailableToday(): bool
    {
        $days = $this->available_days ?: [];

        if ($days === []) {
            return true;
        }

        return in_array((int) now()->isoWeekday(), array_map('intval', $days), true);
    }

    public function hasStock(): bool
    {
        if ($this->stock_quantity === null) {
            return true;
        }

        return (int) $this->sold_quantity < (int) $this->stock_quantity;
    }

    public function hasDailyStock(): bool
    {
        if ($this->daily_limit === null) {
            return true;
        }

        $startOfDay = now()->startOfDay();

        // Completed or soft-deleted purchases still consumed a daily slot.
        $soldToday = $this->userProducts()->withTrashed()
            ->where('purchased_at', '>=', $startOfDay)
            ->where('purchased_at', '<', $startOfDay->copy()->addDay())
            ->count();

        return $soldToday < $this->daily_limit;
    }

    public function isPurchasableNow(): bool
    {
        return $this->isActive()
            && $this->isWithinAvailablePeriod()
            && $this->isAvailableToday()
            && $this->hasStock()
            && $this->hasDailyStock();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('do_spaces')->url($this->image_path);
    }
}
