<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGatewayEvent;
use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;
use Throwable;

class Qc168WithdrawalCallbackService
{
    public function __construct(
        private readonly Qc168Gateway $gateway,
        private readonly Qc168WithdrawalPayoutService $payoutService,
    ) {}

    public function handleCallback(array $payload, ?string $signature): array
    {
        $signatureValid = $this->gateway->verifySignature($payload, $signature);
        $merchantOrderId = is_scalar($payload['merchant_order_id'] ?? null)
            ? (string) $payload['merchant_order_id']
            : null;
        $paymentStatus = is_scalar($payload['payment_status'] ?? null)
            ? strtolower((string) $payload['payment_status'])
            : null;
        $receivedAt = now();

        if (! $signatureValid) {
            $this->recordEvent(
                $payload,
                $merchantOrderId,
                $paymentStatus,
                false,
                'gateway.withdrawal.callback.invalid_signature',
                'invalid_signature',
                $receivedAt,
            );

            return $this->outcome(false, 'invalid_signature', 401);
        }

        if (! $merchantOrderId) {
            $this->recordEvent(
                $payload,
                null,
                $paymentStatus,
                true,
                'gateway.withdrawal.callback.missing_merchant_order_id',
                'missing_merchant_order_id',
                $receivedAt,
            );

            return $this->outcome(false, 'missing_merchant_order_id', 422);
        }

        $attempt = WithdrawalPayoutAttempt::query()
            ->where('merchant_order_id', $merchantOrderId)
            ->first();

        if (! $attempt) {
            $this->recordEvent(
                $payload,
                $merchantOrderId,
                $paymentStatus,
                true,
                'gateway.withdrawal.callback.unknown_order',
                'unknown_order',
                $receivedAt,
            );

            return $this->outcome(false, 'unknown_order', 404);
        }

        try {
            $result = $this->payoutService->processProviderState(
                $attempt,
                $payload,
                'qc168_callback',
            );
        } catch (Throwable $exception) {
            $this->recordEvent(
                $payload,
                $merchantOrderId,
                $paymentStatus,
                true,
                'gateway.withdrawal.callback.processing_error',
                'processing_error',
                $receivedAt,
                $attempt,
            );
            throw $exception;
        }

        $this->recordEvent(
            $payload,
            $merchantOrderId,
            $paymentStatus,
            true,
            'gateway.withdrawal.callback.'.$result['result'],
            $result['result'],
            $receivedAt,
            $attempt,
        );

        return $result['accepted']
            ? $this->outcome(true, $result['result'], 200)
            : $this->outcome(false, $result['result'], 422);
    }

    private function recordEvent(
        array $payload,
        ?string $merchantOrderId,
        ?string $paymentStatus,
        bool $signatureValid,
        string $eventType,
        string $result,
        mixed $receivedAt,
        ?WithdrawalPayoutAttempt $attempt = null,
    ): void {
        PaymentGatewayEvent::query()->create([
            'provider' => WithdrawRequest::GATEWAY_QC168,
            'merchant_order_id' => $merchantOrderId,
            'withdraw_request_id' => $attempt?->withdraw_request_id,
            'withdrawal_payout_attempt_id' => $attempt?->id,
            'source' => 'qc168_callback',
            'event_type' => $eventType,
            'payment_status' => $paymentStatus,
            'signature_valid' => $signatureValid,
            'payload' => $payload,
            'received_at' => $receivedAt,
            'processed_at' => now(),
            'processing_result' => $result,
        ]);
    }

    private function outcome(bool $accepted, string $result, int $httpStatus): array
    {
        return compact('accepted', 'result', 'httpStatus');
    }
}
