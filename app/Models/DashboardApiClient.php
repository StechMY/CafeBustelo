<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class DashboardApiClient extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'immutable_datetime',
        ];
    }
}
