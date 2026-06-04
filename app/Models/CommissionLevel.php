<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionLevel extends Model
{
    protected $fillable = [
        'level',
        'name',
        'rate_percent',
        'is_active',
        'sort_order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'rate_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function rateDecimal(): float
    {
        return (float) $this->rate_percent / 100;
    }
}