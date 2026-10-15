<?php

namespace App\Http\Controllers;

use App\Models\GoldenEggDraw;
use App\Models\GoldenEggPrize;
use App\Models\PurchaseCoupon;
use App\Models\Setting;
use App\Services\GoldenEggService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class GoldenEggController extends Controller
{
    public function index(Request $request, GoldenEggService $service): View|JsonResponse
    {
        $user = $request->user();
        $data = [
            'enabled' => Setting::bool(GoldenEggService::SETTING_ENABLED),
            'is_virtual' => (bool) $user->is_virtual,
            'remaining_chances' => $service->remainingChances($user),
            'invite_url' => url('/register?ref='.$user->referral_code),
            'prizes' => GoldenEggPrize::query()->orderBy('slot')->get()->map(fn (GoldenEggPrize $prize): array => [
                'id' => $prize->id, 'name' => $prize->name, 'type' => $prize->type, 'amount' => $prize->amount,
                'discount_percent' => $prize->discount_percent,
                'available' => $user->is_virtual || ($prize->weight > 0 && ($prize->stock === null || $prize->stock > 0)),
            ])->all(),
            'history' => GoldenEggDraw::query()->where('user_id', $user->id)->latest('id')->limit(30)->get()
                ->map(fn (GoldenEggDraw $draw): array => $service->formatDraw($draw))->all(),
            'coupons' => PurchaseCoupon::query()->availableTo($user)->orderByDesc('discount_percent')
                ->get(['id', 'discount_percent'])->toArray(),
        ];

        if ($request->is('api/*')) {
            return ApiResponse::success($data);
        }

        return view('golden-egg.index', ['activity' => $data]);
    }

    public function draw(Request $request, GoldenEggService $service): JsonResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'egg_number' => ['required', 'integer', 'between:1,9'],
        ]);

        try {
            $draw = $service->draw($request->user(), $data['request_id'], (int) $data['egg_number']);

            return ApiResponse::success([
                'prize' => $service->formatDraw($draw),
                'remaining_chances' => $service->remainingChances($request->user()),
            ]);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage());
        }
    }
}
