<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointTransaction extends Model
{
    public const DIRECTION_CREDIT = 'credit';
    public const DIRECTION_DEBIT = 'debit';

    public const TYPE_UPGRADE_DAY_PURCHASE = 'upgrade_day_purchase';
    public const TYPE_INVITE_DAY_REGISTER = 'invite_day_register';
    public const TYPE_INVITE_DAY_FIRST_PURCHASE = 'invite_day_first_purchase';
    public const TYPE_REDEEM_CASH = 'redeem_cash';

    protected $fillable = [
        'user_id',
        'direction',
        'type',
        'points',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'remarks',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}