<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GoldenEggDraw extends Model
{
    protected $fillable = [
        'user_id', 'prize_id', 'entry_id', 'request_id', 'egg_number', 'prize_name',
        'prize_type', 'amount', 'discount_percent', 'is_virtual',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'discount_percent' => 'decimal:2', 'is_virtual' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): HasOne
    {
        return $this->hasOne(PurchaseCoupon::class);
    }
}
