<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BonusCode extends Model
{
    public const TYPE_INVITE = 'invite';
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_ADMIN = 'admin';
    public const TYPE_COMPENSATION = 'compensation';

    public const STATUS_UNUSED = 'unused';
    public const STATUS_USED = 'used';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'code',
        'owner_user_id',
        'issued_by_admin_user_id',
        'type',
        'amount',
        'status',
        'used_at',
        'expires_at',
        'remark',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function issuedByAdminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'issued_by_admin_user_id');
    }

    public function claim(): HasOne
    {
        return $this->hasOne(BonusClaim::class);
    }

    public function isUnused(): bool
    {
        return $this->status === self::STATUS_UNUSED;
    }

    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpiredByTime(): bool
    {
        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }

    public function canBeClaimedBy(User $user): bool
    {
        return $this->isUnused()
            && ! $this->isExpiredByTime()
            && (int) $this->owner_user_id === (int) $user->id;
    }
}