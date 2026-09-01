<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VipLevelAchievement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'level',
        'reached_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'reached_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
