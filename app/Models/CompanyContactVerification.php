<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyContactVerification extends Model
{
    protected $fillable = [
        'visitor_id',
        'fingerprint_hash',
        'remember_token_hash',
        'ip',
        'user_agent',
        'risk_score',
        'decision',
        'reasons',
        'signals',
        'verified_at',
        'expires_at',
        'remember_expires_at',
    ];

    protected $casts = [
        'reasons' => 'array',
        'signals' => 'array',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'remember_expires_at' => 'datetime',
    ];
}