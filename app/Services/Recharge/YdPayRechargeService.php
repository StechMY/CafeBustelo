<?php

namespace App\Services\Recharge;

use App\Models\RechargeRequest;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayEventRecorder;
use App\Services\PaymentGateway\YdPayGateway;
use App\Services\RechargeApprovalService;
use App\Services\Settings\YdPaySettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class YdPayRechargeService
{
    public function __construct(
        private readonly YdPayGateway $gateway,
        private readonly PaymentGatewayEventRecorder $events,
        private readonly RechargeApprovalService $approval,
    ) {}

    public function create(User $user, string $amount, ?string $clientIp = null): RechargeRequest
    {
        if (! $this->gateway->isAvailable()) {
            throw new RuntimeException('YDPAY deposit is unavailable.');
        }
        $minor = $this->gateway->minorAmount($amount);
        $notifyUrl = $this->gateway->publicUrl('payment-gateway.ydpay.deposit.callback');
        $order = RechargeRequest::query()->create([
            'user_id' => $user->id, 'amount' => $amount,
            'deposit_method' => RechargeRequest::METHOD_GATEWAY,
            'gateway_provider' => 'ydpay',
            'merchant_order_id' => now()->format('YmdHis').random_int(100000000000, 999999999999),
            'gateway_currency' => 'vnd', 'gateway_payment_status' => 'create_pending',
            'gateway_channel' => (string) config('services.ydpay.deposit_gateway_id'),
            'payment_method_name' => 'YDPAY', 'status' => 'pending',
            'gateway_meta' => ['deposit_expires_at' => now()->addMinutes(app(YdPaySettings::class)->depositExpiryMinutes())->toAtomString()],
        ]);

        try {
            $response = $this->gateway->createDeposit(array_filter([
                'gatewayId' => $order->gateway_channel, 'mchOrderNo' => $order->merchant_order_id,
                'amount' => $minor, 'currency' => 'VND', 'subject' => 'Wallet recharge',
                'body' => 'Recharge '.$order->merchant_order_id,
                'notifyUrl' => $notifyUrl,
                'returnUrl' => $this->gateway->publicUrl('recharge.gateway.return', $order), 'clientIp' => $clientIp,
            ], fn ($value) => $value !== null));
            if ($this->gateway->isSandboxResponse($response)) {
                DB::transaction(function () use ($order, $response): void {
                    $locked = RechargeRequest::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                    $locked->forceFill(['gateway_payment_status' => 'sandbox_review',
                        'gateway_meta' => array_merge($locked->gateway_meta ?? [], ['environment_review' => 'sandbox_response'])])->save();
                    $this->events->recordRecharge('ydpay.deposit.sandbox_review', $locked, payload: $response);
                });
                throw new RuntimeException('YDPAY returned a sandbox payment. Financial review required.');
            }
            $url = $response['payJumpUrl'] ?? $response['payUrl'] ?? null;
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
                || parse_url($url, PHP_URL_SCHEME) !== 'https' || empty($response['payOrderId'])) {
                throw new RuntimeException('YDPAY payment link or order ID is missing. Query the original order.');
            }
            DB::transaction(function () use ($order, $response, $url): void {
                $locked = RechargeRequest::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($locked->gateway_order_uid && $locked->gateway_order_uid !== (string) $response['payOrderId']) {
                    throw new RuntimeException('YDPAY platform order mismatch.');
                }
                $updates = ['gateway_order_uid' => (string) $response['payOrderId'], 'gateway_payment_url' => $url];
                // A successful create only means payment can begin, never that money was received.
                if ($locked->gateway_payment_status === 'create_pending') {
                    $updates['gateway_payment_status'] = 'pending';
                }
                $locked->forceFill($updates)->save();
                $this->events->recordRecharge('ydpay.deposit.submitted', $locked, payload: $response);
            });
        } catch (Throwable $exception) {
            // The provider may have accepted the order, including when a callback raced the response.
            $this->events->recordRecharge('ydpay.deposit.submission_uncertain', $order, processingResult: 'query_required');
            throw $exception;
        }

        return $order->fresh();
    }

    public function sync(RechargeRequest $order): array
    {
        if ($order->gateway_provider !== 'ydpay') {
            throw new RuntimeException('YDPAY sync requires a YDPAY deposit.');
        }

        $payload = $this->gateway->query($order->merchant_order_id, 'deposit');
        if (($payload['mchOrderNo'] ?? null) !== $order->merchant_order_id) {
            throw new RuntimeException('YDPAY query returned a different deposit order.');
        }

        return ['accepted' => true, 'result' => $this->process($payload, 'sync')];
    }

    public function process(array $payload, string $source): string
    {
        if (! in_array($source, ['callback', 'sync'], true) || ! $this->gateway->verify($payload)
            || $this->gateway->isSandboxResponse($payload)) {
            throw new RuntimeException('Invalid YDPAY signature.');
        }

        return DB::transaction(function () use ($payload, $source): string {
            $order = RechargeRequest::query()->where('gateway_provider', 'ydpay')
                ->where('merchant_order_id', $payload['mchOrderNo'] ?? '')->lockForUpdate()->firstOrFail();
            $status = (string) ($payload['status'] ?? '');
            if (data_get($order->gateway_meta, 'environment_review')) {
                throw new RuntimeException('YDPAY deposit environment requires financial review.');
            }
            if (! $order->isGatewayDeposit() || $order->gateway_currency !== 'vnd'
                || ($payload['mchId'] ?? null) !== (string) config('services.ydpay.merchant_id')
                || (string) ($payload['gatewayId'] ?? '') !== $order->gateway_channel
                || (string) ($payload['amount'] ?? '') !== $this->gateway->minorAmount($order->amount)
                || ! is_scalar($payload['payOrderId'] ?? null) || (string) $payload['payOrderId'] === ''
                || ($order->gateway_order_uid && $order->gateway_order_uid !== (string) $payload['payOrderId'])
                || (isset($payload['currency']) && strtoupper($payload['currency']) !== 'VND')
                || ! in_array($status, $source === 'callback' ? ['2', '-1', '-2'] : ['0', '1', '2', '3', '4', '-1', '-2'], true)
                || isset($payload['sandbox'])
                || ($source === 'callback' && (string) ($payload['backType'] ?? '') !== '2')
                || ($source === 'sync' && ((string) ($payload['retCode'] ?? '') !== '0'
                    || strtoupper((string) ($payload['currency'] ?? '')) !== 'VND'))
                || ($source === 'callback' && $status === '2' && ! isset($payload['income']))
                || (in_array($status, ['2', '3'], true) && isset($payload['income'])
                    && (string) $payload['income'] !== $this->gateway->minorAmount($order->amount))) {
                throw new RuntimeException('YDPAY deposit evidence does not match the local order.');
            }
            $completed = in_array($status, ['2', '3'], true);
            $state = match ($status) {
                '0' => 'processing', '1' => 'pending', '2', '3' => 'completed',
                '4' => 'refund_review', '-1' => 'failed', '-2' => 'closed',
            };
            $timeField = $source === 'callback' ? 'gateway_last_callback_at' : 'gateway_last_synced_at';
            $order->forceFill([$timeField => now()])->save();

            // Refund/review is sticky; stale pending/failure messages cannot undo a paid order.
            $review = $order->gateway_payment_status === 'refund_review';
            if ($state === 'refund_review') {
                $order->forceFill(['gateway_payment_status' => 'refund_review', 'gateway_order_uid' => (string) $payload['payOrderId'],
                    'gateway_meta' => array_merge($order->gateway_meta ?? [], ['ydpay_status' => $status,
                        'financial_review_reason' => 'provider_refund', 'financial_review_detected_at' => now()->toAtomString()])])->save();
            } elseif ($review || $order->isApproved()
                || (in_array($order->gateway_payment_status, ['failed', 'closed', 'expired'], true) && in_array($status, ['0', '1'], true))) {
                $this->events->recordRecharge('ydpay.deposit.stale_status_ignored', $order, payload: $payload, processingResult: $source);

                return $review ? 'refund_review' : $order->gateway_payment_status;
            } else {
                if (! $order->isPending()) {
                    throw new RuntimeException('YDPAY local deposit is not pending.');
                }
                $meta = array_merge($order->gateway_meta ?? [], ['ydpay_status' => $status]);
                if ($source === 'sync' && in_array($status, ['0', '1'], true)) {
                    // Legacy orders use 60 minutes. New orders retain their snapshotted deadline.
                    $expiresAt = Carbon::parse($meta['deposit_expires_at'] ?? $order->created_at->copy()->addMinutes(60));
                    if (now()->gte($expiresAt)) {
                        // Local expiry is not a provider close: keep reconciling for verified late payments.
                        $state = 'expired';
                        $meta['deposit_expires_at'] = $expiresAt->toAtomString();
                        $meta['deposit_expired_at'] = now()->toAtomString();
                    }
                }
                $order->forceFill([
                    'gateway_order_uid' => (string) $payload['payOrderId'],
                    'gateway_payment_status' => $state,
                    'gateway_completed_at' => $completed ? now() : null,
                    'gateway_final_amount' => $completed ? $order->amount : null,
                    'gateway_meta' => $meta,
                ])->save();
                if ($completed) {
                    $this->approval->approve($order, null, 'Verified YDPAY payment.', ['gateway_provider' => 'ydpay']);
                }
            }
            $this->events->recordRecharge('ydpay.deposit.'.$state, $order,
                reason: $state === 'expired' ? 'Local payment deadline elapsed; verified query still unpaid. Provider order not closed; reconciliation continues.' : null,
                payload: $payload, processingResult: $source);

            return $state;
        });
    }
}
