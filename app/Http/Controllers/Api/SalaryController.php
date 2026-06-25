<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SalaryClaimRequest;
use App\Models\SalaryClaim;
use App\Models\VipLevel;
use App\Services\SalaryService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use App\Support\Api\DisplayValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class SalaryController extends Controller
{
    public function index(Request $request, SalaryService $salaryService): JsonResponse
    {
        $user = $request->user();
        $types = [SalaryClaim::TYPE_WEEKLY, SalaryClaim::TYPE_MONTHLY, SalaryClaim::TYPE_ANNUAL];
        $claimStates = [];

        foreach ($types as $type) {
            $claimStates[$type] = [
                'period_key' => $salaryService->periodKey($type),
                'claimed' => $salaryService->hasClaimed($user, $type),
                'enabled' => match ($type) {
                    SalaryClaim::TYPE_WEEKLY => (bool) $user->weekly_salary_status,
                    SalaryClaim::TYPE_MONTHLY => (bool) $user->monthly_salary_status,
                    SalaryClaim::TYPE_ANNUAL => (bool) $user->annual_salary_status,
                    default => false,
                },
                'within_window' => $salaryService->isWithinClaimWindow($type),
                'claim_window' => $salaryService->claimWindowLabel($type),
            ];
        }

        $claims = SalaryClaim::query()->where('user_id', $user->id)->latest('claimed_at')->paginate((int) $request->query('per_page', 10));
        $activeTeamSize = $user->activeTeamSize();
        $realCurrentVipLevel = VipLevel::query()
            ->where('status', 'active')
            ->where('required_accumulated_recharge', '<=', $user->accumulated_recharge)
            ->where('required_active_team_size', '<=', $activeTeamSize)
            ->orderByDesc('level')
            ->first();
        $vipLevelValue = DisplayValue::resolve($user, 'display_vip_level', $realCurrentVipLevel?->level ?? 1);

        return ApiResponse::success([
            'current_vip_level' => ApiFormatter::vipLevel($realCurrentVipLevel),
            'display_current_vip_level' => ApiFormatter::vipLevel($salaryService->currentVipLevel($user) ?: $realCurrentVipLevel),
            'is_vip_level_overridden' => $vipLevelValue['is_overridden'],
            ...DisplayValue::integerFields($user, 'active_team_size', 'display_active_team_count', $activeTeamSize),
            ...DisplayValue::moneyFields($user, 'accumulated_recharge', 'display_accumulated_recharge', $user->accumulated_recharge),
            'vip_levels' => VipLevel::query()->where('status', 'active')->orderBy('level')->get()->map(fn (VipLevel $level): ?array => ApiFormatter::vipLevel($level))->values(),
            'claim_states' => $claimStates,
            'claims' => [
                'items' => $claims->getCollection()->map(fn (SalaryClaim $claim): array => ApiFormatter::salaryClaim($claim))->values(),
                'pagination' => ['current_page' => $claims->currentPage(), 'per_page' => $claims->perPage(), 'total' => $claims->total(), 'last_page' => $claims->lastPage()],
            ],
        ]);
    }

    public function claim(SalaryClaimRequest $request, SalaryService $service): JsonResponse
    {
        try {
            $claim = $service->claim($request->user(), $request->validated('type'), $request->ip(), $request->userAgent());

            return ApiResponse::success(['claim' => ApiFormatter::salaryClaim($claim)], __('salary.claim_success'), 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(__('salary.claim_failed'), [], 500);
        }
    }
}
