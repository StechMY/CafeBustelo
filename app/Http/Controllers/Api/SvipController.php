<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PurchaseRequest;
use App\Models\SvipPackage;
use App\Models\SvipPurchase;
use App\Services\SvipService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class SvipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'packages' => SvipPackage::query()->active()->orderBy('sort_order')->orderBy('id')->get()->map(fn (SvipPackage $package): array => ApiFormatter::svipPackage($package))->values(),
            'active_purchase' => ApiFormatter::svipPurchase($request->user()->activeSvipPurchase()->with('package')->first()),
            'history' => SvipPurchase::query()->with('package')->where('user_id', $request->user()->id)->latest()->limit(20)->get()->map(fn (SvipPurchase $purchase): ?array => ApiFormatter::svipPurchase($purchase))->values(),
        ]);
    }

    public function purchase(PurchaseRequest $request, SvipPackage $svipPackage, SvipService $service): JsonResponse
    {
        try {
            $purchase = $service->purchase($request->user(), $svipPackage, $request->validated('wallet_type'));

            return ApiResponse::success([
                'purchase' => ApiFormatter::svipPurchase($purchase->load('package')),
            ], __('svip.purchase_success'), 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(__('svip.purchase_failed'), [], 500);
        }
    }
}
