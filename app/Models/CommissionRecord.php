<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRecord extends Model
{
    protected $fillable = [
        'earner_user_id',
        'source_user_id',
        'source_user_product_id',
        'daily_income_claim_id',
        'product_id',
        'generation_level',
        'source_daily_income',
        'percentage',
        'amount',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'generation_level' => 'integer',
            'source_daily_income' => 'decimal:2',
            'percentage' => 'decimal:4',
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function earner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'earner_user_id');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function sourceUserProduct(): BelongsTo
    {
        return $this->belongsTo(UserProduct::class, 'source_user_product_id');
    }

    public function dailyIncomeClaim(): BelongsTo
    {
        return $this->belongsTo(DailyIncomeClaim::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}