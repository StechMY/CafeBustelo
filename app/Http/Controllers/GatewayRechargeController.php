<?php

namespace App\Http\Controllers;

use App\Exceptions\Qc168GatewayException;
use App\Models\RechargeRequest;
use App\Services\Recharge\GatewayRechargeService;
use App\Support\RechargeAmountRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class GatewayRechargeController extends Controller
{
    public function store(Request $request, GatewayRechargeService $service): RedirectResponse
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

            return back()->withInput()->withErrors([
                'gateway' => __('recharge.gateway_unavailable'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'gateway' => __('recharge.gateway_unavailable'),
            ]);
        }

        return redirect()->away((string) $rechargeRequest->gateway_payment_url);
    }

    public function showReturn(Request $request, RechargeRequest $rechargeRequest): View
    {
        $this->authorizeOwner($request, $rechargeRequest);
        abort_unless($rechargeRequest->isGatewayDeposit(), 404);

        return view('recharge.gateway-return', [
            'rechargeRequest' => $rechargeRequest,
        ]);
    }

    public function status(Request $request, RechargeRequest $rechargeRequest): JsonResponse
    {
        $this->authorizeOwner($request, $rechargeRequest);
        abort_unless($rechargeRequest->isGatewayDeposit(), 404);

        $rechargeRequest->refresh();

        return response()->json([
            'status' => $rechargeRequest->status,
            'gateway_status' => $rechargeRequest->gateway_payment_status,
            'approved' => $rechargeRequest->isApproved(),
        ]);
    }

    private function authorizeOwner(Request $request, RechargeRequest $rechargeRequest): void
    {
        abort_unless((int) $rechargeRequest->user_id === (int) $request->user()->id, 403);
    }
}
