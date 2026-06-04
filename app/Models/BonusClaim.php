<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusClaim extends Model
{
    protected $fillable = [
        'user_id',
        'bonus_code_id',
        'type',
        'amount',
        'claimed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'claimed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bonusCode(): BelongsTo
    {
        return $this->belongsTo(BonusCode::class);
    }
}