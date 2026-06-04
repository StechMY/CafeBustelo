<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'product_id',
        'price_snapshot',
        'daily_income_snapshot',
        'duration_days_snapshot',
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
}