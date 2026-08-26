<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccountReviewRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'bank_account_name',
        'bank_name',
        'bank_code',
        'bank_account_no',
        'phone',
        'phone_verified_at',
        'id_photo_paths',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'admin_remark',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'id_photo_paths' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function maskedAccountNo(): string
    {
        $number = preg_replace('/\D+/', '', $this->bank_account_no);

        if ($number === '') {
            return $this->bank_account_no;
        }

        return str_repeat('*', max(strlen($number) - 4, 0)).substr($number, -4);
    }

    public function idPhotoUrl(int $index): string
    {
        return route('admin.bank-account-review-requests.id-photo', [
            'bankAccountReviewRequest' => $this,
            'photo' => $index,
        ]);
    }
}
