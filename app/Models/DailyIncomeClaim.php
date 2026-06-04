<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyIncomeClaim extends Model
{
    protected $fillable = [
        'user_id',
        'user_product_id',
        'product_id',
        'claim_day_no',
        'amount',
        'claim_date',
        'claimable_at',
        'claimed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'claim_day_no' => 'integer',
            'amount' => 'decimal:2',
            'claim_date' => 'date',
            'claimable_at' => 'datetime',
            'claimed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userProduct(): BelongsTo
    {
        return $this->belongsTo(UserProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(CommissionRecord::class);
    }
}