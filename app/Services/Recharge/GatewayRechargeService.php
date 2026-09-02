<?php

namespace App\Services\Recharge;

use App\Exceptions\Qc168GatewayException;
use App\Exceptions\Qc168OrderRejectedException;
use App\Exceptions\Qc168UncertainSubmissionException;
use App\Models\RechargeRequest;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayEventRecorder;
use App\Services\PaymentGateway\Qc168Gateway;
use Illuminate\Support\Str;
use Throwable;

class GatewayRechargeService
{
    public function __construct(
        private readonly Qc168Gateway $gateway,
        private readonly PaymentGatewayEventRecorder $eventRecorder,
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
        } catch (Qc168OrderRejectedException $exception) {
            $rechargeRequest->forceFill([
                'gateway_payment_status' => 'create_failed',
                'gateway_fail_reason' => mb_substr($exception->getMessage(), 0, 1000),
                'gateway_meta' => [
                    'order_create_result' => 'rejected',
                    'order_created' => false,
                    'order_create_failed_at' => now()->toAtomString(),
                ],
                'status' => 'rejected',
                'reviewed_at' => now(),
            ])->save();

            $this->eventRecorder->recordRecharge(
                'gateway.order.create_rejected',
                $rechargeRequest,
                $rechargeRequest->gateway_fail_reason,
                ['order_created' => false],
                'create_failed',
            );

            throw $exception;
        } catch (Qc168UncertainSubmissionException $exception) {
            $rechargeRequest->forceFill([
                'gateway_payment_status' => 'create_pending',
                'gateway_fail_reason' => mb_substr($exception->getMessage(), 0, 1000),
                'gateway_meta' => [
                    'order_create_result' => 'uncertain',
                    'order_created' => null,
                    'order_create_uncertain_at' => now()->toAtomString(),
                ],
            ])->save();

            $this->eventRecorder->recordRecharge(
                'gateway.order.submission_uncertain',
                $rechargeRequest,
                $rechargeRequest->gateway_fail_reason,
                ['order_created' => null],
                'submission_uncertain',
            );

            throw $exception;
        } catch (Throwable $exception) {
            $rechargeRequest->forceFill([
                'gateway_payment_status' => 'create_failed',
                'gateway_fail_reason' => mb_substr($exception->getMessage(), 0, 1000),
                'gateway_meta' => [
                    'order_create_result' => 'failed',
                    'order_created' => false,
                    'order_create_failed_at' => now()->toAtomString(),
                ],
                'status' => 'rejected',
                'reviewed_at' => now(),
            ])->save();

            $this->eventRecorder->recordRecharge(
                'gateway.order.create_failed',
                $rechargeRequest,
                $rechargeRequest->gateway_fail_reason,
                ['order_created' => false],
                'create_failed',
            );

            throw $exception;
        }

        $payment = data_get($response, 'data.payment', []);
        $payment = is_array($payment) ? $payment : [];
        $finalAmount = $payment['final_amount'] ?? null;
        $paymentUrl = data_get($response, 'data.payment_url');

        $rechargeRequest->forceFill([
            'gateway_payment_url' => $paymentUrl,
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
                'order_created' => true,
                'order_created_at' => now()->toAtomString(),
            ],
        ])->save();

        $this->eventRecorder->recordRecharge(
            'gateway.order.created',
            $rechargeRequest,
            payload: ['order_created' => true],
            processingResult: 'created',
        );

        if (! is_string($paymentUrl) || ! filter_var($paymentUrl, FILTER_VALIDATE_URL)) {
            throw new Qc168GatewayException('QC168 did not return a valid payment URL.');
        }

        if (app()->environment('production') && parse_url($paymentUrl, PHP_URL_SCHEME) !== 'https') {
            throw new Qc168GatewayException('QC168 returned a non-HTTPS payment URL.');
        }

        return $rechargeRequest->fresh();
    }

    private function merchantOrderId(): string
    {
        return 'DEP'.now()->format('Ymd').Str::ulid();
    }
}
