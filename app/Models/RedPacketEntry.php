<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedPacketEntry extends Model
{
    protected $fillable = ['user_id', 'invited_user_id', 'user_product_id', 'source', 'award_key', 'earned_on', 'used_at'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(UserProduct::class, 'user_product_id')->withTrashed();
    }
}
