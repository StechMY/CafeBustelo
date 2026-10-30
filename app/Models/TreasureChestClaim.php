<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasureChestClaim extends Model
{
    protected $fillable = ['user_id', 'chest_number', 'threshold', 'amount', 'keys', 'discount_percent'];

    protected function casts(): array
    {
        return [
            'chest_number' => 'integer', 'threshold' => 'integer', 'keys' => 'integer',
            'amount' => 'decimal:2', 'discount_percent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
