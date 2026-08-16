<?php

namespace App\Models;

use App\Support\BankAccountNameMatcher;
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
        'phone',
        'phone_verified_at',
        'status',
        'is_locked',
        'bound_at',
        'meta',
    ];

    protected $hidden = [
        'bank_account_name_match_hash',
        'bank_account_name_unique_hash',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'bound_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (UserBankAccount $account): void {
            $matchHash = BankAccountNameMatcher::matchHash($account->bank_account_name);

            $account->bank_account_name_match_hash = $matchHash;
            $account->bank_account_name_unique_hash = $matchHash;
        });

        static::updating(function (UserBankAccount $account): void {
            if (! $account->isDirty('bank_account_name')) {
                return;
            }

            $matchHash = BankAccountNameMatcher::matchHash($account->bank_account_name);

            if (! hash_equals((string) $account->getOriginal('bank_account_name_match_hash'), $matchHash)) {
                $account->bank_account_name_unique_hash = $matchHash;
            }

            $account->bank_account_name_match_hash = $matchHash;
        });
    }

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

        return str_repeat('*', max(strlen($number) - 4, 0)).substr($number, -4);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasSimilarNameOnAnotherUser(): bool
    {
        return self::query()
            ->where('bank_account_name_match_hash', $this->bank_account_name_match_hash)
            ->where('user_id', '!=', $this->user_id)
            ->exists();
    }

    public static function isNameUsedByAnotherUser(string $name, int $userId): bool
    {
        return self::query()
            ->where('bank_account_name_match_hash', BankAccountNameMatcher::matchHash($name))
            ->where('user_id', '!=', $userId)
            ->exists();
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
