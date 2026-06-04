<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryClaim extends Model
{
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_ANNUAL = 'annual';

    protected $fillable = [
        'user_id',
        'vip_level_id',
        'type',
        'period_key',
        'vip_level_snapshot',
        'accumulated_recharge_snapshot',
        'active_team_size_snapshot',
        'salary_amount',
        'team_subsidy_amount',
        'total_amount',
        'claimed_at',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'vip_level_snapshot' => 'integer',
            'accumulated_recharge_snapshot' => 'decimal:2',
            'active_team_size_snapshot' => 'integer',
            'salary_amount' => 'decimal:2',
            'team_subsidy_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
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