<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class PhoneOtp extends Model
{
    protected $fillable = [
        'phone',
        'purpose',
        'code_hash',
        'code_encrypted',
        'attempts',
        'expires_at',
        'verified_at',
        'sms_uid',
        'sms_code',
        'sms_desc',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function getDecryptedCodeAttribute(): ?string
    {
        if (blank($this->code_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->code_encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->verified_at) {
            return 'Used';
        }

        if ($this->expires_at && now()->greaterThan($this->expires_at)) {
            return 'Expired';
        }

        return 'Valid';
    }
}