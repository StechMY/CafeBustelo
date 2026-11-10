<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGatewayEvent;
use App\Models\RechargeRequest;
use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;
use Illuminate\Support\Facades\DB;

class YdPayDiagnostics
{
    public function record(?string $merchantOrderId, array $diagnostic): void
    {
        if ($merchantOrderId === null) {
            return; // Balance lookups have no order or financial lifecycle to annotate.
        }
        DB::transaction(function () use ($merchantOrderId, $diagnostic): void {
            $payout = str_starts_with($diagnostic['endpoint'], '/api/agentpay/');
            $attempt = $merchantOrderId && $payout ? WithdrawalPayoutAttempt::query()
                ->where('provider', 'ydpay')->where('merchant_order_id', $merchantOrderId)->first() : null;
            $order = $attempt ? WithdrawRequest::query()->whereKey($attempt->withdraw_request_id)->lockForUpdate()->first()
                : ($merchantOrderId && ! $payout ? RechargeRequest::query()->where('gateway_provider', 'ydpay')
                    ->where('merchant_order_id', $merchantOrderId)->lockForUpdate()->first() : null);
            $attempt = $attempt ? WithdrawalPayoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first() : null;
            $current = $order && ($order instanceof RechargeRequest || ($order->gateway_provider === 'ydpay'
                && $order->gateway_current_merchant_order_id === $merchantOrderId));
            if ($current) {
                $meta = $order->gateway_meta ?? [];
                $meta['ydpay_last_error'] = $diagnostic;
                if (in_array($diagnostic['endpoint'], ['/api/agentpay/apply', '/api/pay/create_order'], true)) {
                    $meta['ydpay_submission_error'] ??= $diagnostic;
                }
                $updates = ['gateway_meta' => $meta];
                // Diagnostics never decide payment status, release reserves, or replace terminal reasons.
                $unresolved = $order instanceof RechargeRequest
                    ? $order->isPending() && ! $order->requiresGatewayReview()
                    : ! in_array($order->payout_status, ['completed', 'cancelled', 'failed', 'refund_review'], true);
                if ($unresolved) {
                    $updates['gateway_fail_reason'] = $diagnostic['message'];
                }
                $order->forceFill($updates)->save();
                if ($attempt && $attempt->gateway_status === 'create_pending') {
                    $attempt->forceFill(['failure_reason' => $meta['ydpay_submission_error']['message'] ?? $diagnostic['message']])->save();
                }
            }
            PaymentGatewayEvent::query()->create([
                'provider' => 'ydpay', 'merchant_order_id' => $merchantOrderId,
                'withdraw_request_id' => $attempt?->withdraw_request_id,
                'withdrawal_payout_attempt_id' => $attempt?->id,
                'recharge_request_id' => $order instanceof RechargeRequest ? $order->id : null,
                'event_type' => 'ydpay.request.error', 'source' => 'ydpay_http',
                'reason' => $diagnostic['message'], 'payload' => $diagnostic,
                'signature_valid' => $diagnostic['signature_valid'],
                'received_at' => now(), 'processed_at' => now(), 'processing_result' => 'diagnostic_only',
            ]);
        });
    }
}
