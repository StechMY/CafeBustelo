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

    protected $fillable = [
        'name',
        'code',
        'product_type',
        'available_days',
        'available_from',
        'available_until',
        'stock_quantity',
        'sold_quantity',
        'claim_interval_hours',
        'total_claims',
        'extra_points',
        'price',
        'image_path',
        'daily_income',
        'duration_days',
        'upgrade_bonus',
        'invite_bonus',
        'max_purchase_per_user',
        'status',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'daily_income' => 'decimal:2',
            'upgrade_bonus' => 'decimal:2',
            'invite_bonus' => 'decimal:2',
            'duration_days' => 'integer',
            'sort_order' => 'integer',
            'max_purchase_per_user' => 'integer',
            'available_days' => 'array',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'stock_quantity' => 'integer',
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
        $now = now();

        if ($this->available_from && $now->lt($this->available_from)) {
            return false;
        }

        if ($this->available_until && $now->gt($this->available_until)) {
            return false;
        }

        return true;
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

    public function isPurchasableNow(): bool
    {
        return $this->isActive()
            && $this->isWithinAvailablePeriod()
            && $this->isAvailableToday()
            && $this->hasStock();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('do_spaces')->url($this->image_path);
    }
}
