<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PointsRedeemRequest;
use App\Models\PointTransaction;
use App\Models\Setting;
use App\Services\PointService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class PointsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $rate = (float) Setting::decimal(PointService::SETTING_POINTS_TO_CASH_RATE, 100);
        $transactions = PointTransaction::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success([
            'points_balance' => ApiFormatter::money($user->points_balance),
            'exchange_rate' => $rate,
            'estimated_cash_value' => ApiFormatter::money((float) $user->points_balance * $rate),
            'minimum_redeem_points' => ApiFormatter::money(Setting::decimal(PointService::SETTING_MINIMUM_REDEEM_POINTS, 0)),
            'points_enabled' => Setting::bool(PointService::SETTING_POINTS_ENABLED, false),
            'redeem_enabled' => Setting::bool(PointService::SETTING_REDEEM_ENABLED, false),
            'formula' => 'cash_amount = points * exchange_rate',
            'transactions' => [
                'items' => $transactions->getCollection()->map(fn (PointTransaction $item): array => ApiFormatter::pointTransaction($item))->values(),
                'pagination' => ['current_page' => $transactions->currentPage(), 'per_page' => $transactions->perPage(), 'total' => $transactions->total(), 'last_page' => $transactions->lastPage()],
            ],
        ]);
    }

    public function redeem(PointsRedeemRequest $request, PointService $service): JsonResponse
    {
        try {
            $service->redeemToWithdrawableWallet($request->user(), (float) $request->validated('points'));

            return ApiResponse::success([
                'user' => ApiFormatter::user($request->user()->fresh()),
            ], __('points.redeem_success'));
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(__('points.redeem_failed'), [], 500);
        }
    }
}
