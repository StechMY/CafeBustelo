<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RechargeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'recharge_method_id',

        'amount',
        'receipt_path',
        'receipt_disk',

        'payment_method_name',
        'payment_bank_name',
        'payment_account_name',
        'payment_account_no',

        // keep these for backward compatibility
        'bank_name',
        'reference_no',

        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'admin_remark',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rechargeMethod(): BelongsTo
    {
        return $this->belongsTo(RechargeMethod::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }

    public function getReceiptUrlAttribute(): ?string
    {
        if (! $this->receipt_path) {
            return null;
        }

        return Storage::disk($this->receipt_disk ?: 'do_spaces')
            ->url($this->receipt_path);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}