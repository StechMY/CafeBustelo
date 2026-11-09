<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Qc168GatewayException;
use App\Http\Controllers\Controller;
use App\Models\RechargeRequest;
use App\Services\Recharge\GatewayRechargeService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use App\Support\RechargeAmountRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GatewayRechargeController extends Controller
{
    public function store(Request $request, GatewayRechargeService $service): JsonResponse
    {
        $data = $request->validate([
            'amount' => RechargeAmountRules::rules(),
            'provider' => ['nullable', 'in:qc168,ydpay'],
        ]);

        try {
            $rechargeRequest = ($data['provider'] ?? 'qc168') === 'ydpay'
                ? app(\App\Services\Recharge\YdPayRechargeService::class)->create($request->user(), (string) $data['amount'], $request->ip())
                : $service->create($request->user(), $data['amount']);
        } catch (Qc168GatewayException $exception) {
            report($exception);

            return ApiResponse::error(__('recharge.gateway_unavailable'), [], 503);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(__('recharge.gateway_unavailable'), [], 503);
        }

        return ApiResponse::success([
            'recharge_request' => ApiFormatter::rechargeRequest($rechargeRequest),
            'payment_url' => $rechargeRequest->gateway_payment_url,
        ], status: 201);
    }

    public function status(Request $request, RechargeRequest $rechargeRequest): JsonResponse
    {
        if ((int) $rechargeRequest->user_id !== (int) $request->user()->id) {
            throw new AuthorizationException;
        }

        abort_unless($rechargeRequest->isGatewayDeposit(), 404);

        return ApiResponse::success([
            'recharge_request' => ApiFormatter::rechargeRequest($rechargeRequest->fresh()),
        ]);
    }
}
