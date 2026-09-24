<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalPayoutAttempt extends Model
{
    public const STATUS_CREATE_PENDING = 'create_pending';

    public const STATUS_CREATE_FAILED = 'create_failed';

    public const STATUS_NONE = 'none';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REJECT = 'reject';

    public const STATUS_REFUND = 'refund';

    public const STATUS_OVERDUE = 'overdue';

    public const PROVIDER_FINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_REJECT,
        self::STATUS_REFUND,
        self::STATUS_OVERDUE,
    ];

    protected $fillable = [
        'withdraw_request_id',
        'attempt_no',
        'provider',
        'merchant_order_id',
        'amount',
        'currency',
        'payment_channel',
        'bank_name_snapshot',
        'bank_code_snapshot',
        'bank_account_name_snapshot',
        'bank_account_no_snapshot',
        'gateway_order_uid',
        'gateway_payment_uid',
        'gateway_status',
        'gateway_final_amount',
        'submitted_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'provider_response',
        'provider_meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_final_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'provider_response' => 'array',
            'provider_meta' => 'array',
        ];
    }

    public function withdrawRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawRequest::class);
    }

    public function isCompleted(): bool
    {
        return $this->gateway_status === self::STATUS_COMPLETED;
    }

    public function isSafeFinalFailure(): bool
    {
        return in_array($this->gateway_status, [
            self::STATUS_CREATE_FAILED,
            self::STATUS_FAILED,
            self::STATUS_REJECT,
            self::STATUS_OVERDUE,
        ], true);
    }

    public function isManuallyConfirmedUnpaid(): bool
    {
        return $this->gateway_status === self::STATUS_FAILED
            && data_get($this->provider_meta, 'latest_state_source') === 'manual_unpaid_confirmation'
            && filled(data_get($this->provider_meta, 'manual_unpaid_confirmation.confirmed_at'));
    }
}
