<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RechargeStoreRequest;
use App\Models\RechargeMethod;
use App\Models\RechargeRequest;
use App\Models\Wallet;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RechargeController extends Controller
{
    public function index(Request $request, Qc168Gateway $gateway): JsonResponse
    {
        $user = $request->user();
        $wallet = Wallet::query()->where('user_id', $user->id)->where('type', Wallet::TYPE_RECHARGE)->first();

        $requests = RechargeRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate((int) $request->query('per_page', 10));

        $methods = RechargeMethod::query()
            ->where('status', RechargeMethod::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success([
            'wallet' => ApiFormatter::wallet($wallet, Wallet::TYPE_RECHARGE),
            'deposit_methods' => [
                [
                    'key' => RechargeRequest::METHOD_MANUAL,
                    'label' => __('recharge.manual_deposit'),
                    'enabled' => $methods->isNotEmpty(),
                ],
                [
                    'key' => RechargeRequest::METHOD_GATEWAY,
                    'label' => __('recharge.payment_gateway'),
                    'enabled' => $gateway->isAvailable(),
                    'provider' => RechargeRequest::GATEWAY_QC168,
                ],
                [
                    'key' => RechargeRequest::METHOD_GATEWAY,
                    'label' => 'YDPAY',
                    'enabled' => app(\App\Services\PaymentGateway\YdPayGateway::class)->isAvailable(),
                    'provider' => 'ydpay',
                ],
            ],
            'methods' => $methods
                ->map(fn (RechargeMethod $method): array => ApiFormatter::rechargeMethod($method))
                ->values(),
            'requests' => [
                'items' => $requests->getCollection()->map(fn (RechargeRequest $item): array => ApiFormatter::rechargeRequest($item))->values(),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                    'last_page' => $requests->lastPage(),
                ],
            ],
        ]);
    }

    public function store(RechargeStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $rechargeMethod = RechargeMethod::query()
            ->whereKey($data['recharge_method_id'])
            ->where('status', RechargeMethod::STATUS_ACTIVE)
            ->firstOrFail();

        $receiptDisk = 'do_spaces';
        $receiptPath = $request->file('receipt')
            ->storePublicly('recharge-receipts/'.now()->format('Y/m'), $receiptDisk);

        $rechargeRequest = RechargeRequest::query()->create([
            'user_id' => $request->user()->id,
            'recharge_method_id' => $rechargeMethod->id,
            'amount' => $data['amount'],
            'deposit_method' => RechargeRequest::METHOD_MANUAL,
            'receipt_path' => $receiptPath,
            'receipt_disk' => $receiptDisk,
            'payment_method_name' => $rechargeMethod->name,
            'payment_bank_name' => $rechargeMethod->bank_name,
            'payment_account_name' => $rechargeMethod->account_name,
            'payment_account_no' => $rechargeMethod->account_no,
            'bank_name' => $rechargeMethod->bank_name,
            'reference_no' => $data['reference_no'] ?? null,
            'status' => 'pending',
        ]);

        return ApiResponse::success([
            'recharge_request' => ApiFormatter::rechargeRequest($rechargeRequest),
        ], __('recharge.submitted_successfully'), 201);
    }
}
