<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BonusRedeemRequest;
use App\Models\BonusCode;
use App\Services\BonusCodeService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class BonusCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = BonusCode::query()->where('owner_user_id', $user->id)->latest();

        if (($status = $request->query('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($type = $request->query('type')) && $type !== 'all') {
            $query->where('type', $type);
        }

        $items = $query->paginate((int) $request->query('per_page', 12));

        return ApiResponse::success([
            'stats' => [
                'unused_count' => BonusCode::query()->where('owner_user_id', $user->id)->where('status', BonusCode::STATUS_UNUSED)->count(),
                'unused_amount' => ApiFormatter::money(BonusCode::query()->where('owner_user_id', $user->id)->where('status', BonusCode::STATUS_UNUSED)->sum('amount')),
                'used_count' => BonusCode::query()->where('owner_user_id', $user->id)->where('status', BonusCode::STATUS_USED)->count(),
                'used_amount' => ApiFormatter::money(BonusCode::query()->where('owner_user_id', $user->id)->where('status', BonusCode::STATUS_USED)->sum('amount')),
            ],
            'items' => $items->getCollection()->map(fn (BonusCode $code): array => ApiFormatter::bonusCode($code))->values(),
            'pagination' => ['current_page' => $items->currentPage(), 'per_page' => $items->perPage(), 'total' => $items->total(), 'last_page' => $items->lastPage()],
        ]);
    }

    public function redeem(BonusRedeemRequest $request, BonusCodeService $service): JsonResponse
    {
        $bonusCode = BonusCode::query()->where('code', strtoupper($request->validated('code')))->first();

        if (! $bonusCode) {
            return ApiResponse::error(__('bonus.code_not_available'), [], 404);
        }

        try {
            $claim = $service->claim($request->user(), $bonusCode);

            return ApiResponse::success([
                'bonus_code' => ApiFormatter::bonusCode($bonusCode->fresh()),
                'claim' => [
                    'id' => (int) $claim->id,
                    'amount' => ApiFormatter::money($claim->amount),
                    'claimed_at' => ApiFormatter::dateTime($claim->claimed_at),
                ],
            ], __('bonus.claim_success'), 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(__('bonus.claim_failed'), [], 500);
        }
    }
}
