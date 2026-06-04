<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckInRecord extends Model
{
    protected $fillable = [
        'user_id',
        'vip_level_id',
        'claim_date',
        'vip_level_snapshot',
        'amount',
        'claimed_at',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
            'vip_level_snapshot' => 'integer',
            'amount' => 'decimal:2',
            'claimed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vipLevel(): BelongsTo
    {
        return $this->belongsTo(VipLevel::class);
    }
}