<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBankAccount extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_SUSPENDED,
    ];
    protected $fillable = [
        'user_id',
        'bank_account_name',
        'bank_name',
        'bank_account_no',
        'status',
        'is_locked',
        'bound_at',
        'meta',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'bound_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maskedAccountNo(): string
    {
        $number = preg_replace('/\D+/', '', (string) $this->bank_account_no);

        if ($number === '') {
            return $this->bank_account_no;
        }

        return str_repeat('*', max(strlen($number) - 4, 0)) . substr($number, -4);
    }

     public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => __('filament.active'),
            self::STATUS_INACTIVE => __('filament.inactive'),
            self::STATUS_SUSPENDED => __('filament.suspended'),
        ];
    }
}