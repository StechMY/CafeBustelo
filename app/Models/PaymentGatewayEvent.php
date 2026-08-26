<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentGatewayEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'merchant_order_id',
        'recharge_request_id',
        'withdraw_request_id',
        'withdrawal_payout_attempt_id',
        'actor_admin_user_id',
        'source',
        'reason',
        'event_type',
        'payment_status',
        'signature_valid',
        'payload',
        'received_at',
        'processed_at',
        'processing_result',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Payment gateway events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Payment gateway events are immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function setPayloadAttribute(array $payload): void
    {
        $this->attributes['payload'] = json_encode(
            $this->withoutCredentials($payload),
            JSON_THROW_ON_ERROR,
        );
    }

    public function rechargeRequest(): BelongsTo
    {
        return $this->belongsTo(RechargeRequest::class);
    }

    public function withdrawRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawRequest::class);
    }

    public function withdrawalPayoutAttempt(): BelongsTo
    {
        return $this->belongsTo(WithdrawalPayoutAttempt::class);
    }

    public function actorAdminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'actor_admin_user_id');
    }

    private function withoutCredentials(array $payload): array
    {
        $safe = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = is_string($key)
                ? strtolower(str_replace(['_', ' '], '-', $key))
                : null;

            if ($normalizedKey !== null && (
                str_contains($normalizedKey, 'secret')
                || in_array($normalizedKey, ['oc-key', 'qc168-oc-key', 'authorization'], true)
            )) {
                continue;
            }

            $safe[$key] = is_array($value)
                ? $this->withoutCredentials($value)
                : $value;
        }

        return $safe;
    }
}
