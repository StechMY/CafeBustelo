<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\PaymentGatewayEvent;
use App\Models\RechargeRequest;
use App\Models\WithdrawalPayoutAttempt;
use App\Services\PaymentGateway\YdPayGateway;
use App\Services\PaymentGateway\YdPayWithdrawalService;
use App\Services\Recharge\YdPayRechargeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class YdPayCallbackController extends Controller
{
    public function __invoke(Request $request, YdPayGateway $gateway, YdPayRechargeService $deposits, YdPayWithdrawalService $withdrawals): Response
    {
        // Use query parameters exactly as delivered, before Laravel trims strings.
        $payload = $request->query();
        $valid = $gateway->verify($payload);
        $isWithdrawal = $request->routeIs('payment-gateway.ydpay.withdraw.callback');
        $result = 'invalid_signature';
        $accepted = false;
        try {
            if (in_array($request->ip(), config('services.ydpay.callback_ips', []), true) && $valid) {
                $result = $isWithdrawal
                    ? $withdrawals->process($payload, 'callback')
                    : $deposits->process($payload, 'callback');
                $accepted = true;
            }
        } catch (Throwable) {
            $result = 'processing_error';
        }
        $merchantOrderId = is_string($payload['mchOrderNo'] ?? null) ? $payload['mchOrderNo'] : null;
        $attempt = $valid && $isWithdrawal && $merchantOrderId
            ? WithdrawalPayoutAttempt::query()->where('provider', 'ydpay')->where('merchant_order_id', $merchantOrderId)->first() : null;
        $deposit = $valid && ! $isWithdrawal && $merchantOrderId
            ? RechargeRequest::query()->where('gateway_provider', 'ydpay')->where('merchant_order_id', $merchantOrderId)->first() : null;
        PaymentGatewayEvent::query()->create([
            'provider' => 'ydpay', 'event_type' => 'ydpay.callback.'.$result,
            'source' => 'ydpay_callback', 'merchant_order_id' => $merchantOrderId,
            'recharge_request_id' => $deposit?->id, 'withdraw_request_id' => $attempt?->withdraw_request_id,
            'withdrawal_payout_attempt_id' => $attempt?->id,
            'signature_valid' => $valid, 'payload' => $payload, 'processing_result' => $result,
            'received_at' => now(), 'processed_at' => now(),
        ]);

        return response($accepted ? 'success' : 'fail', $accepted ? 200 : 422)->header('Content-Type', 'text/plain');
    }
}
