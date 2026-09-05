<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WithdrawRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const APPROVAL_MODE_MANUAL = 'manual';

    public const APPROVAL_MODE_GATEWAY_SCHEDULED = 'gateway_scheduled';

    public const APPROVAL_MODE_GATEWAY_INSTANT = 'gateway_instant';

    public const GATEWAY_QC168 = 'qc168';

    public const PAYOUT_WAITING_RELEASE = 'waiting_release';

    public const PAYOUT_SUBMITTED = 'submitted';

    public const PAYOUT_PENDING = 'pending';

    public const PAYOUT_PREPARING = 'preparing';

    public const PAYOUT_PROCESSING = 'processing';

    public const PAYOUT_COMPLETED = 'completed';

    public const PAYOUT_CREATE_FAILED = 'create_failed';

    public const PAYOUT_FAILED = 'failed';

    public const PAYOUT_REJECTED = 'rejected';

    public const PAYOUT_OVERDUE = 'overdue';

    public const PAYOUT_REFUND_REVIEW = 'refund_review';

    public const PAYOUT_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'user_bank_account_id',

        'amount',
        'fee_amount',
        'net_amount',

        'vip_level_snapshot',
        'min_withdraw_amount_snapshot',
        'withdraw_fee_percent_snapshot',

        'bank_name',
        'bank_code',
        'bank_account_name',
        'bank_account_no',

        'status',
        'approval_mode',
        'reviewed_by',
        'reviewed_at',
        'admin_remark',
        'gateway_provider',
        'payout_status',
        'gateway_release_at',
        'gateway_release_delay_hours_snapshot',
        'gateway_submitted_at',
        'gateway_completed_at',
        'gateway_last_callback_at',
        'gateway_last_synced_at',
        'gateway_current_merchant_order_id',
        'gateway_payment_status',
        'gateway_final_amount',
        'gateway_fail_reason',
        'gateway_meta',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $withdrawRequest): void {
            if ($withdrawRequest->isDirty('approval_mode')
                && filled($withdrawRequest->getOriginal('approval_mode'))) {
                throw new \LogicException('Withdrawal approval mode is immutable after approval.');
            }
        });

        static::deleting(function (): never {
            throw new \LogicException('Withdrawal requests cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vip_level_snapshot' => 'integer',
            'min_withdraw_amount_snapshot' => 'decimal:2',
            'withdraw_fee_percent_snapshot' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'gateway_release_at' => 'datetime',
            'gateway_release_delay_hours_snapshot' => 'integer',
            'gateway_submitted_at' => 'datetime',
            'gateway_completed_at' => 'datetime',
            'gateway_last_callback_at' => 'datetime',
            'gateway_last_synced_at' => 'datetime',
            'gateway_final_amount' => 'decimal:2',
            'gateway_meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(UserBankAccount::class, 'user_bank_account_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }

    public function payoutAttempts(): HasMany
    {
        return $this->hasMany(WithdrawalPayoutAttempt::class);
    }

    public function latestPayoutAttempt(): HasOne
    {
        return $this->hasOne(WithdrawalPayoutAttempt::class)->latestOfMany('attempt_no');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function payoutAmount(): string
    {
        return (string) $this->net_amount;
    }

    public function isPayoutCompleted(): bool
    {
        return $this->payout_status === self::PAYOUT_COMPLETED;
    }

    public function canRetryPayout(): bool
    {
        return $this->isApproved()
            && ! $this->hasGatewayCancellationLedger()
            && in_array($this->payout_status, [
                self::PAYOUT_CREATE_FAILED,
                self::PAYOUT_FAILED,
                self::PAYOUT_REJECTED,
                self::PAYOUT_OVERDUE,
            ], true);
    }

    public function canCancelPayout(): bool
    {
        return $this->canRetryPayout();
    }

    public function hasGatewayCancellationLedger(): bool
    {
        return WalletTransaction::query()
            ->where('type', 'withdraw_gateway_cancelled')
            ->where('reference_type', self::class)
            ->where('reference_id', $this->getKey())
            ->exists();
    }

    public function canSyncPayout(): bool
    {
        return $this->isApproved()
            && filled($this->gateway_current_merchant_order_id)
            && ! in_array($this->payout_status, [self::PAYOUT_COMPLETED, self::PAYOUT_CANCELLED], true);
    }

    public function payoutStatusLabel(): string
    {
        return __('withdraw.payout_statuses.'.($this->payout_status ?: 'legacy'));
    }

    public function approvalModeLabel(): string
    {
        $mode = $this->approval_mode;

        if (! $mode
            && $this->isApproved()
            && $this->payout_status === self::PAYOUT_COMPLETED
            && ! $this->gateway_provider) {
            $mode = self::APPROVAL_MODE_MANUAL;
        }

        return __('withdraw.approval_modes.'.($mode ?: 'legacy'));
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('payout_status', self::PAYOUT_COMPLETED)
                ->orWhere(function (Builder $query): void {
                    $query->where('status', self::STATUS_APPROVED)
                        ->whereNull('payout_status')
                        ->whereNull('gateway_provider');
                });
        });
    }

    public function scopeWithReservedFunds(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->where('status', self::STATUS_PENDING)
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', self::STATUS_APPROVED)
                            ->whereNotIn('payout_status', [self::PAYOUT_COMPLETED, self::PAYOUT_CANCELLED]);
                    });
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('wallet_transactions')
                    ->whereColumn('wallet_transactions.reference_id', 'withdraw_requests.id')
                    ->where('wallet_transactions.reference_type', self::class)
                    ->where('wallet_transactions.type', 'withdraw_gateway_cancelled');
            });
    }
}
