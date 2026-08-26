<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RechargeRequest extends Model
{
    public const METHOD_MANUAL = 'manual';

    public const METHOD_GATEWAY = 'gateway';

    public const GATEWAY_QC168 = 'qc168';

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

        'deposit_method',
        'gateway_provider',
        'merchant_order_id',
        'gateway_order_uid',
        'gateway_payment_uid',
        'gateway_payment_status',
        'gateway_payment_url',
        'gateway_final_amount',
        'gateway_currency',
        'gateway_channel',
        'gateway_fail_reason',
        'gateway_completed_at',
        'gateway_last_callback_at',
        'gateway_last_synced_at',
        'gateway_meta',

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
            'gateway_final_amount' => 'decimal:2',
            'gateway_completed_at' => 'datetime',
            'gateway_last_callback_at' => 'datetime',
            'gateway_last_synced_at' => 'datetime',
            'gateway_meta' => 'array',
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

    public function isManualDeposit(): bool
    {
        return $this->deposit_method === self::METHOD_MANUAL;
    }

    public function isGatewayDeposit(): bool
    {
        return $this->deposit_method === self::METHOD_GATEWAY;
    }
}
