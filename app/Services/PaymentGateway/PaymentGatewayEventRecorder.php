<?php

namespace App\Services\PaymentGateway;

use App\Models\AdminUser;
use App\Models\PaymentGatewayEvent;
use App\Models\RechargeRequest;
use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;

class PaymentGatewayEventRecorder
{
    public function recordRecharge(
        string $eventType,
        RechargeRequest $rechargeRequest,
        ?string $reason = null,
        array $payload = [],
        string $processingResult = 'recorded',
    ): PaymentGatewayEvent {
        return PaymentGatewayEvent::query()->create([
            'provider' => $rechargeRequest->gateway_provider ?: RechargeRequest::GATEWAY_QC168,
            'merchant_order_id' => $rechargeRequest->merchant_order_id,
            'recharge_request_id' => $rechargeRequest->id,
            'source' => 'system',
            'reason' => $reason,
            'event_type' => $eventType,
            'payment_status' => $rechargeRequest->gateway_payment_status,
            'signature_valid' => null,
            'payload' => $payload,
            'received_at' => now(),
            'processed_at' => now(),
            'processing_result' => $processingResult,
        ]);
    }

    public function recordSettingsChange(
        AdminUser $actor,
        ?string $ip,
        array $oldValues,
        array $newValues,
    ): PaymentGatewayEvent {
        return PaymentGatewayEvent::query()->create([
            'provider' => WithdrawRequest::GATEWAY_QC168,
            'actor_admin_user_id' => $actor->id,
            'source' => 'filament',
            'event_type' => 'qc168.settings.updated',
            'signature_valid' => null,
            'payload' => array_filter([
                'ip' => $ip,
                'old' => $oldValues,
                'new' => $newValues,
            ], fn (mixed $value): bool => $value !== null),
            'received_at' => now(),
            'processed_at' => now(),
            'processing_result' => 'saved',
        ]);
    }

    public function recordWithdrawal(
        string $eventType,
        WithdrawRequest $withdrawRequest,
        ?WithdrawalPayoutAttempt $attempt = null,
        ?AdminUser $actor = null,
        string $source = 'system',
        ?string $reason = null,
        ?string $paymentStatus = null,
        array $payload = [],
        ?bool $signatureValid = null,
        string $processingResult = 'recorded',
    ): PaymentGatewayEvent {
        return PaymentGatewayEvent::query()->create([
            'provider' => $attempt?->provider
                ?: ($withdrawRequest->gateway_provider ?: 'internal'),
            'merchant_order_id' => $attempt?->merchant_order_id
                ?: $withdrawRequest->gateway_current_merchant_order_id,
            'withdraw_request_id' => $withdrawRequest->id,
            'withdrawal_payout_attempt_id' => $attempt?->id,
            'actor_admin_user_id' => $actor?->id,
            'source' => $source,
            'reason' => $reason,
            'event_type' => $eventType,
            'payment_status' => $paymentStatus,
            'signature_valid' => $signatureValid,
            'payload' => $payload,
            'received_at' => now(),
            'processed_at' => now(),
            'processing_result' => $processingResult,
        ]);
    }
}
