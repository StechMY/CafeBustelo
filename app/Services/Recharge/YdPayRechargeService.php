<?php

namespace App\Services\Recharge;

use App\Models\RechargeRequest;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayEventRecorder;
use App\Services\PaymentGateway\YdPayGateway;
use App\Services\RechargeApprovalService;
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
        ]);

        try {
            $response = $this->gateway->createDeposit(array_filter([
                'gatewayId' => $order->gateway_channel, 'mchOrderNo' => $order->merchant_order_id,
                'amount' => $minor, 'currency' => 'VND', 'subject' => 'Wallet recharge',
                'body' => 'Recharge '.$order->merchant_order_id,
                'notifyUrl' => $notifyUrl,
                'returnUrl' => $this->gateway->publicUrl('recharge.gateway.return', $order), 'clientIp' => $clientIp,
            ], fn ($value) => $value !== null));
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
                $locked->forceFill(['gateway_order_uid' => (string) $response['payOrderId'], 'gateway_payment_url' => $url])->save();
                $this->events->recordRecharge('ydpay.deposit.submitted', $locked, payload: $response);
            });
        } catch (Throwable $exception) {
            // The provider may have accepted the order, including when a callback raced the response.
            $this->events->recordRecharge('ydpay.deposit.submission_uncertain', $order, processingResult: 'query_required');
            throw $exception;
        }

        return $order->fresh();
    }

    public function process(array $payload, string $source): string
    {
        if (! $this->gateway->verify($payload)) {
            throw new RuntimeException('Invalid YDPAY signature.');
        }

        return DB::transaction(function () use ($payload, $source): string {
            $order = RechargeRequest::query()->where('gateway_provider', 'ydpay')
                ->where('merchant_order_id', $payload['mchOrderNo'] ?? '')->lockForUpdate()->firstOrFail();
            $status = (string) ($payload['status'] ?? '');
            if (($payload['mchId'] ?? null) !== (string) config('services.ydpay.merchant_id')
                || (string) ($payload['gatewayId'] ?? '') !== $order->gateway_channel
                || (string) ($payload['amount'] ?? '') !== $this->gateway->minorAmount($order->amount)
                || ! is_scalar($payload['payOrderId'] ?? null) || (string) $payload['payOrderId'] === ''
                || ($order->gateway_order_uid && $order->gateway_order_uid !== (string) $payload['payOrderId'])
                || (isset($payload['currency']) && strtoupper($payload['currency']) !== 'VND')
                || ! in_array($status, $source === 'callback' ? ['2', '-1', '-2'] : ['0', '1', '2', '3', '4', '-1', '-2'], true)
                || isset($payload['sandbox'])
                || ($source === 'callback' && (string) ($payload['backType'] ?? '') !== '2')
                || (in_array($status, ['2', '3'], true) && isset($payload['income'])
                    && (string) $payload['income'] !== $this->gateway->minorAmount($order->amount))) {
                throw new RuntimeException('YDPAY deposit evidence does not match the local order.');
            }
            $completed = in_array($status, ['2', '3'], true);
            $state = match ($status) {
                '2', '3' => 'completed', '4' => 'refund_review', '-1', '-2' => 'failed', default => 'processing'
            };
            if (! $order->isApproved()) {
                if (! $order->isPending()) {
                    throw new RuntimeException('YDPAY local deposit is not pending.');
                }
                $order->forceFill([
                    'gateway_order_uid' => (string) $payload['payOrderId'],
                    'gateway_payment_status' => $state,
                    $source === 'callback' ? 'gateway_last_callback_at' : 'gateway_last_synced_at' => now(),
                    'gateway_completed_at' => $completed ? now() : null,
                ])->save();
                if ($completed) {
                    $this->approval->approve($order, null, 'Verified YDPAY payment.', ['gateway_provider' => 'ydpay']);
                }
            }
            $this->events->recordRecharge('ydpay.deposit.'.$state, $order, payload: $payload, processingResult: $source);

            return $state;
        });
    }
}
