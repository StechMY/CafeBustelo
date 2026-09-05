<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGatewayEvent;
use App\Models\RechargeRequest;
use App\Services\RechargeApprovalService;
use Illuminate\Support\Facades\DB;
use Throwable;

class Qc168CallbackService
{
    private const STATUSES = [
        'none',
        'pending',
        'preparing',
        'processing',
        'completed',
        'failed',
        'reject',
        'refund',
        'overdue',
    ];

    private const FINAL_STATUSES = [
        'completed',
        'failed',
        'reject',
        'refund',
        'overdue',
    ];

    public function __construct(
        private readonly Qc168Gateway $gateway,
        private readonly RechargeApprovalService $approvalService,
        private readonly Qc168BalanceService $balanceService,
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
                'gateway.callback.invalid_signature',
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
                'gateway.callback.missing_merchant_order_id',
                'missing_merchant_order_id',
                $receivedAt,
            );

            return $this->outcome(false, 'missing_merchant_order_id', 422);
        }

        $rechargeRequest = RechargeRequest::query()
            ->where('merchant_order_id', $merchantOrderId)
            ->first();

        if (! $rechargeRequest) {
            $this->recordEvent(
                $payload,
                $merchantOrderId,
                $paymentStatus,
                true,
                'gateway.callback.unknown_order',
                'unknown_order',
                $receivedAt,
            );

            return $this->outcome(false, 'unknown_order', 404);
        }

        $createRejectedContradiction = data_get(
            $rechargeRequest->gateway_meta,
            'order_create_result'
        ) === 'rejected';

        try {
            $result = $this->processProviderState($rechargeRequest, $payload, 'callback');
        } catch (Throwable $exception) {
            $this->recordEvent(
                $payload,
                $merchantOrderId,
                $paymentStatus,
                true,
                'gateway.callback.processing_error',
                'processing_error',
                $receivedAt,
                $rechargeRequest,
            );
            throw $exception;
        }

        $this->recordEvent(
            $createRejectedContradiction
                ? [...$payload, 'audit_priority' => 'high']
                : $payload,
            $merchantOrderId,
            $paymentStatus,
            true,
            $createRejectedContradiction
                ? 'gateway.callback.create_rejected_contradiction'
                : $this->eventType($result['result']),
            $result['result'],
            $receivedAt,
            $rechargeRequest,
        );

        return $result['accepted']
            ? $this->outcome(true, $result['result'], 200)
            : $this->outcome(false, $result['result'], 422);
    }

    public function sync(RechargeRequest $rechargeRequest): array
    {
        $rechargeRequest->forceFill(['gateway_last_synced_at' => now()])->save();

        $response = $this->gateway->getOrder((string) $rechargeRequest->merchant_order_id);
        $data = data_get($response, 'data');

        if (! is_array($data)) {
            return ['accepted' => false, 'result' => 'invalid_order_response'];
        }

        return $this->processProviderState($rechargeRequest, $data, 'sync');
    }

    public function processProviderState(
        RechargeRequest $rechargeRequest,
        array $payload,
        string $source,
    ): array {
        return DB::transaction(function () use ($rechargeRequest, $payload, $source): array {
            $lockedRequest = RechargeRequest::query()
                ->whereKey($rechargeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            $createRejectedContradiction = $lockedRequest->status === 'rejected'
                && data_get($lockedRequest->gateway_meta, 'order_create_result') === 'rejected';

            $timeField = $source === 'callback'
                ? 'gateway_last_callback_at'
                : 'gateway_last_synced_at';
            $lockedRequest->forceFill([$timeField => now()])->save();

            if ($error = $this->validateProviderState($lockedRequest, $payload)) {
                return ['accepted' => false, 'result' => $error];
            }

            $incomingStatus = strtolower((string) $payload['payment_status']);

            if (! $this->shouldAcceptStatus($lockedRequest->gateway_payment_status, $incomingStatus)) {
                return ['accepted' => true, 'result' => 'stale_status_ignored'];
            }

            $updates = [
                'gateway_payment_status' => $incomingStatus,
                'gateway_order_uid' => $this->nullableString($payload['order_uid'] ?? null)
                    ?: $lockedRequest->gateway_order_uid,
                'gateway_payment_uid' => $this->nullableString($payload['payment_uid'] ?? null)
                    ?: $lockedRequest->gateway_payment_uid,
                'gateway_final_amount' => array_key_exists('final_amount', $payload)
                    ? $this->normalizeAmount($payload['final_amount'])
                    : $lockedRequest->gateway_final_amount,
                'gateway_currency' => strtolower((string) $payload['currency']),
                'gateway_channel' => $this->nullableString($payload['channel'] ?? null)
                    ?: $lockedRequest->gateway_channel,
                'gateway_fail_reason' => $this->nullableString($payload['payment_fail_reason'] ?? null)
                    ?: $lockedRequest->gateway_fail_reason,
                'gateway_meta' => array_merge($lockedRequest->gateway_meta ?? [], [
                    'latest_state_source' => $source,
                    'latest_state_received_at' => now()->toAtomString(),
                ]),
            ];

            if ($incomingStatus === 'completed' && ! $lockedRequest->gateway_completed_at) {
                $updates['gateway_completed_at'] = now();
            }

            $lockedRequest->forceFill($updates)->save();

            if ($incomingStatus === 'refund') {
                return ['accepted' => true, 'result' => 'refund_received'];
            }

            if ($incomingStatus !== 'completed') {
                return ['accepted' => true, 'result' => 'status_updated'];
            }

            if ($lockedRequest->isApproved()) {
                return ['accepted' => true, 'result' => 'duplicate_completed'];
            }

            if (! $lockedRequest->isPending() && ! $createRejectedContradiction) {
                return ['accepted' => false, 'result' => 'local_status_conflict'];
            }

            if ($createRejectedContradiction) {
                $lockedRequest->forceFill(['status' => 'pending'])->save();
            }

            $this->approvalService->approve(
                rechargeRequest: $lockedRequest,
                adminUser: null,
                adminRemark: 'Automatically approved from verified QC168 payment.',
                meta: [
                    'approval_actor' => 'system',
                    'gateway_provider' => RechargeRequest::GATEWAY_QC168,
                    'merchant_order_id' => $lockedRequest->merchant_order_id,
                ],
            );

            DB::afterCommit(fn () => $this->balanceService->markStale());

            return ['accepted' => true, 'result' => 'auto_approved'];
        });
    }

    private function validateProviderState(RechargeRequest $rechargeRequest, array $payload): ?string
    {
        if (! $rechargeRequest->isGatewayDeposit()
            || $rechargeRequest->gateway_provider !== RechargeRequest::GATEWAY_QC168) {
            return 'local_order_mismatch';
        }

        if (! isset($payload['merchant_order_id'])
            || ! hash_equals((string) $rechargeRequest->merchant_order_id, (string) $payload['merchant_order_id'])) {
            return 'merchant_order_mismatch';
        }

        if (! isset($payload['merchant_uid'])
            || ! hash_equals((string) config('services.qc168.merchant_uid'), (string) $payload['merchant_uid'])) {
            return 'merchant_mismatch';
        }

        if (($payload['transaction_type'] ?? null) !== 'income') {
            return 'transaction_type_mismatch';
        }

        if (isset($payload['accounting_type']) && $payload['accounting_type'] !== 'recharge') {
            return 'accounting_type_mismatch';
        }

        if (isset($payload['payment'])
            && ! hash_equals((string) config('services.qc168.payment'), (string) $payload['payment'])) {
            return 'payment_method_mismatch';
        }

        if (! isset($payload['currency'])
            || strtolower((string) $payload['currency']) !== strtolower((string) config('services.qc168.currency', 'vnd'))) {
            return 'currency_mismatch';
        }

        $localAmount = $this->normalizeAmount($rechargeRequest->amount);
        $providerAmount = $this->normalizeAmount($payload['amount'] ?? null);

        if ($localAmount === null || $providerAmount === null || ! hash_equals($localAmount, $providerAmount)) {
            return 'amount_mismatch';
        }

        if (array_key_exists('final_amount', $payload)
            && $this->normalizeAmount($payload['final_amount']) === null) {
            return 'invalid_final_amount';
        }

        $paymentStatus = strtolower((string) ($payload['payment_status'] ?? ''));

        if (! in_array($paymentStatus, self::STATUSES, true)) {
            return 'unknown_payment_status';
        }

        return null;
    }

    private function normalizeAmount(mixed $amount): ?string
    {
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            return null;
        }

        $value = trim((string) $amount);

        if (! preg_match('/^\+?(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return null;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = $matches[2] ?? '';

        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            return null;
        }

        return $whole.'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function shouldAcceptStatus(?string $currentStatus, string $incomingStatus): bool
    {
        if (! $currentStatus
            || in_array($currentStatus, ['create_pending', 'create_failed'], true)
            || $currentStatus === $incomingStatus) {
            return true;
        }

        if ($currentStatus === 'refund') {
            return false;
        }

        if ($incomingStatus === 'refund') {
            return true;
        }

        if (in_array($currentStatus, self::FINAL_STATUSES, true)) {
            return false;
        }

        $precedence = [
            'none' => 0,
            'pending' => 1,
            'preparing' => 2,
            'processing' => 3,
            'completed' => 4,
            'failed' => 4,
            'reject' => 4,
            'overdue' => 4,
        ];

        return ($precedence[$incomingStatus] ?? -1) >= ($precedence[$currentStatus] ?? -1);
    }

    private function recordEvent(
        array $payload,
        ?string $merchantOrderId,
        ?string $paymentStatus,
        bool $signatureValid,
        string $eventType,
        string $result,
        mixed $receivedAt,
        ?RechargeRequest $rechargeRequest = null,
    ): void {
        PaymentGatewayEvent::query()->create([
            'provider' => RechargeRequest::GATEWAY_QC168,
            'merchant_order_id' => $merchantOrderId,
            'recharge_request_id' => $rechargeRequest?->id,
            'event_type' => $eventType,
            'payment_status' => $paymentStatus,
            'signature_valid' => $signatureValid,
            'payload' => $payload,
            'received_at' => $receivedAt,
            'processed_at' => now(),
            'processing_result' => $result,
        ]);
    }

    private function eventType(string $result): string
    {
        return match ($result) {
            'auto_approved' => 'recharge.gateway_auto_approved',
            'duplicate_completed' => 'gateway.callback.duplicate',
            'amount_mismatch' => 'gateway.callback.amount_mismatch',
            'refund_received' => 'gateway.callback.refund_received',
            default => 'gateway.callback.'.$result,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== ''
            ? (string) $value
            : null;
    }

    private function outcome(bool $accepted, string $result, int $httpStatus): array
    {
        return compact('accepted', 'result', 'httpStatus');
    }
}
