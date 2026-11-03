<?php

namespace App\Http\Controllers;

use App\Services\TreasureChestService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TreasureChestController extends Controller
{
    public function index(Request $request, TreasureChestService $service): View|JsonResponse
    {
        $activity = $service->activity($request->user());

        return $request->is('api/*') ? ApiResponse::success($activity) : view('treasure-chest.index', compact('activity'));
    }

    public function claim(Request $request, TreasureChestService $service): JsonResponse
    {
        $data = $request->validate(['chest_number' => ['required', 'integer', 'between:1,9']]);
        try {
            $claim = $service->claim($request->user(), (int) $data['chest_number']);

            return ApiResponse::success(['claim' => $claim, 'activity' => $service->activity($request->user())]);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage());
        }
    }

    public function history(Request $request, TreasureChestService $service): View|JsonResponse
    {
        $history = $service->keyHistory($request->user());

        return $request->is('api/*') ? ApiResponse::paginated($history, fn (array $entry) => $entry) : view('treasure-chest.history', compact('history'));
    }
}
