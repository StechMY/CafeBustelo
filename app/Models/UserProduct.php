<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class UserProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'product_id',
        'price_snapshot',
        'daily_income_snapshot',
        'duration_days_snapshot',
        'claim_interval_hours_snapshot',
        'total_claims_snapshot',
        'product_type_snapshot',
        'extra_points_snapshot',
        'claimed_days',
        'missed_days',
        'purchased_at',
        'next_claim_at',
        'expires_at',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'daily_income_snapshot' => 'decimal:2',
            'duration_days_snapshot' => 'integer',
            'claim_interval_hours_snapshot' => 'integer',
            'total_claims_snapshot' => 'integer',
            'extra_points_snapshot' => 'integer',
            'claimed_days' => 'integer',
            'missed_days' => 'integer',
            'purchased_at' => 'datetime',
            'next_claim_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dailyIncomeClaims(): HasMany
    {
        return $this->hasMany(DailyIncomeClaim::class);
    }

    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class, 'source_user_product_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function effectiveClaimIntervalHours(): int
    {
        return max(1, (int) ($this->claim_interval_hours_snapshot ?: 24));
    }

    public function effectiveTotalClaims(): int
    {
        if ($this->total_claims_snapshot !== null && (int) $this->total_claims_snapshot > 0) {
            return (int) $this->total_claims_snapshot;
        }

        $durationDays = max(1, (int) $this->duration_days_snapshot);
        $claimIntervalHours = $this->effectiveClaimIntervalHours();

        return max(1, (int) floor(($durationDays * 24) / $claimIntervalHours));
    }

    public static function calculateFinalClaimUnlockAt(
        Carbon $purchasedAt,
        int $claimIntervalHours,
        int $totalClaims,
    ): Carbon {
        return $purchasedAt->copy()->addHours($claimIntervalHours * $totalClaims);
    }

    public static function calculateExpiresAt(
        Carbon $purchasedAt,
        int $claimIntervalHours,
        int $totalClaims,
    ): Carbon {
        return self::calculateFinalClaimUnlockAt(
            $purchasedAt,
            $claimIntervalHours,
            $totalClaims,
        )->endOfDay();
    }

    public function finalClaimUnlockAt(): Carbon
    {
        return self::calculateFinalClaimUnlockAt(
            $this->purchased_at,
            $this->effectiveClaimIntervalHours(),
            $this->effectiveTotalClaims(),
        );
    }

    public function effectiveExpiresAt(): Carbon
    {
        $finalClaimDayExpiry = $this->finalClaimUnlockAt()->endOfDay();

        if (! $this->expires_at || $this->expires_at->lt($finalClaimDayExpiry)) {
            return $finalClaimDayExpiry;
        }

        return $this->expires_at->copy();
    }

    public function isHourlyClaimProduct(): bool
    {
        return $this->effectiveClaimIntervalHours() < 24;
    }
}
