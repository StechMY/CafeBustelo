<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'price',
        'image_path',
        'daily_income',
        'duration_days',
        'upgrade_bonus',
        'invite_bonus',
        'status',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'daily_income' => 'decimal:2',
            'upgrade_bonus' => 'decimal:2',
            'invite_bonus' => 'decimal:2',
            'duration_days' => 'integer',
            'sort_order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function userProducts(): HasMany
    {
        return $this->hasMany(UserProduct::class);
    }

    public function dailyIncomeClaims(): HasMany
    {
        return $this->hasMany(DailyIncomeClaim::class);
    }

    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return Storage::disk('do_spaces')->url($this->image_path);
    }
}