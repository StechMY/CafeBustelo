<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckInRecord;
use App\Models\VipLevel;
use App\Services\CheckInService;
use App\Services\SalaryService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class CheckInController extends Controller
{
    public function index(Request $request, CheckInService $checkInService, SalaryService $salaryService): JsonResponse
    {
        $user = $request->user();
        $records = CheckInRecord::query()->with('vipLevel')->where('user_id', $user->id)->latest('claimed_at')->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success([
            'current_vip_level' => ApiFormatter::vipLevel($salaryService->currentVipLevel($user)),
            'has_claimed_today' => $checkInService->hasClaimedToday($user),
            'today_record' => ($record = $checkInService->todayRecord($user)) ? ApiFormatter::checkInRecord($record) : null,
            'vip_levels' => VipLevel::query()->where('status', 'active')->orderBy('level')->get()->map(fn (VipLevel $level): ?array => ApiFormatter::vipLevel($level))->values(),
            'total_claimed' => ApiFormatter::money(CheckInRecord::query()->where('user_id', $user->id)->sum('amount')),
            'this_month_claimed' => ApiFormatter::money(CheckInRecord::query()->where('user_id', $user->id)->whereBetween('claimed_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')),
            'records' => [
                'items' => $records->getCollection()->map(fn (CheckInRecord $item): array => ApiFormatter::checkInRecord($item))->values(),
                'pagination' => ['current_page' => $records->currentPage(), 'per_page' => $records->perPage(), 'total' => $records->total(), 'last_page' => $records->lastPage()],
            ],
        ]);
    }

    public function claim(Request $request, CheckInService $service): JsonResponse
    {
        try {
            $record = $service->claim($request->user(), $request->ip(), $request->userAgent());

            return ApiResponse::success(['record' => ApiFormatter::checkInRecord($record)], __('checkin.claim_success'), 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(__('checkin.claim_failed'), [], 500);
        }
    }
}
