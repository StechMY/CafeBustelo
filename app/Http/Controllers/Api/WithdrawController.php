<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WithdrawStoreRequest;
use App\Models\Wallet;
use App\Models\WithdrawRequest;
use App\Services\WithdrawService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class WithdrawController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = Wallet::query()->where('user_id', $user->id)->where('type', Wallet::TYPE_WITHDRAWABLE)->first();
        $bankAccount = $user->bankAccount()->where('status', 'active')->first();
        $vipLevel = $user->currentVipLevel();
        $requests = WithdrawRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate((int) $request->query('per_page', 10));

        return ApiResponse::success([
            'wallet' => ApiFormatter::wallet($wallet, Wallet::TYPE_WITHDRAWABLE),
            'bank_account' => ApiFormatter::bankAccount($bankAccount),
            'vip_level' => ApiFormatter::vipLevel($vipLevel),
            'min_withdraw_amount' => ApiFormatter::money($vipLevel?->min_withdraw_amount),
            'withdraw_fee_percent' => ApiFormatter::money($vipLevel?->withdraw_fee_percent),
            'can_withdraw' => (bool) $user->canWithdraw() && $user->status === 'active' && $bankAccount !== null,
            'requests' => [
                'items' => $requests->getCollection()->map(fn (WithdrawRequest $item): array => ApiFormatter::withdrawRequest($item))->values(),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                    'last_page' => $requests->lastPage(),
                ],
            ],
        ]);
    }

    public function store(WithdrawStoreRequest $request, WithdrawService $service): JsonResponse
    {
        try {
            $withdrawRequest = $service->submit($request->user(), $request->validated('amount'));

            return ApiResponse::success([
                'withdraw_request' => ApiFormatter::withdrawRequest($withdrawRequest),
            ], __('withdraw.submitted_successfully'), 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(__('withdraw.submit_failed'), [], 500);
        }
    }
}
