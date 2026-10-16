<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseCoupon extends Model
{
    protected $fillable = ['user_id', 'golden_egg_draw_id', 'discount_percent', 'used_at', 'user_product_id'];

    protected function casts(): array
    {
        return ['discount_percent' => 'decimal:2', 'used_at' => 'datetime'];
    }

    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)->whereNull('used_at');
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(GoldenEggDraw::class, 'golden_egg_draw_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(UserProduct::class, 'user_product_id')->withTrashed();
    }

    public function discountedPrice(float|string $price): float
    {
        $cents = (int) round((float) $price * 100);
        $basisPoints = (int) round((float) $this->discount_percent * 100);

        return round($cents * (10000 - $basisPoints) / 10000) / 100;
    }
}
