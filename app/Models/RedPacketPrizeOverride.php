<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedPacketPrizeOverride extends Model
{
    protected $fillable = ['user_id', 'prize_id', 'admin_user_id', 'draw_id', 'used_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('used_at')->whereNull('cancelled_at');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id')->withTrashed();
    }
}
