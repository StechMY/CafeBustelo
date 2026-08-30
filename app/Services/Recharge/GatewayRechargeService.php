<?php

namespace App\Services\Recharge;

use App\Exceptions\Qc168GatewayException;
use App\Exceptions\Qc168UncertainSubmissionException;
use App\Models\RechargeRequest;
use App\Models\User;
use App\Services\PaymentGateway\Qc168Gateway;
use Illuminate\Support\Str;
use Throwable;

class GatewayRechargeService
{
    public function __construct(
        private readonly Qc168Gateway $gateway,
    ) {}

    public function create(User $user, float|string $amount): RechargeRequest
    {
        if (! $this->gateway->isAvailable()) {
            throw new Qc168GatewayException(__('recharge.gateway_unavailable'));
        }

        $rechargeRequest = RechargeRequest::query()->create([
            'user_id' => $user->id,
            'amount' => $this->gateway->formatAmount($amount),
            'deposit_method' => RechargeRequest::METHOD_GATEWAY,
            'gateway_provider' => RechargeRequest::GATEWAY_QC168,
            'merchant_order_id' => $this->merchantOrderId(),
            'gateway_payment_status' => 'pending',
            'gateway_currency' => strtolower((string) config('services.qc168.currency', 'vnd')),
            'payment_method_name' => 'QC168',
            'status' => 'pending',
        ]);

        try {
            $response = $this->gateway->createDepositOrder($rechargeRequest);
            $payment = data_get($response, 'data.payment', []);
            $payment = is_array($payment) ? $payment : [];
            $finalAmount = $payment['final_amount'] ?? null;

            $rechargeRequest->forceFill([
                'gateway_payment_url' => data_get($response, 'data.payment_url'),
                'gateway_order_uid' => $payment['order_uid'] ?? null,
                'gateway_payment_uid' => $payment['payment_uid'] ?? null,
                'gateway_payment_status' => strtolower((string) ($payment['payment_status'] ?? 'pending')),
                'gateway_final_amount' => is_numeric($finalAmount)
                    ? $this->gateway->formatAmount($finalAmount)
                    : null,
                'gateway_currency' => strtolower((string) ($payment['currency'] ?? config('services.qc168.currency', 'vnd'))),
                'gateway_channel' => $payment['channel'] ?? null,
                'gateway_fail_reason' => $payment['payment_fail_reason'] ?? null,
                'gateway_meta' => [
                    'order_create_result' => 'success',
                    'order_created_at' => now()->toAtomString(),
                ],
            ])->save();
        } catch (Qc168UncertainSubmissionException $exception) {
            $rechargeRequest->forceFill([
                'gateway_payment_status' => 'create_pending',
                'gateway_fail_reason' => mb_substr($exception->getMessage(), 0, 1000),
                'gateway_meta' => [
                    'order_create_result' => 'uncertain',
                    'order_create_uncertain_at' => now()->toAtomString(),
                ],
            ])->save();

            throw $exception;
        } catch (Throwable $exception) {
            $rechargeRequest->forceFill([
                'gateway_payment_status' => 'create_failed',
                'gateway_fail_reason' => mb_substr($exception->getMessage(), 0, 1000),
                'gateway_meta' => [
                    'order_create_result' => 'failed',
                    'order_create_failed_at' => now()->toAtomString(),
                ],
            ])->save();

            throw $exception;
        }

        return $rechargeRequest->fresh();
    }

    private function merchantOrderId(): string
    {
        return 'DEP'.now()->format('Ymd').Str::ulid();
    }
}
