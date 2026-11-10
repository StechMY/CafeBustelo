<?php

namespace App\Http\Controllers;

use App\Models\RedPacketDraw;
use App\Models\RedPacketEntry;
use App\Services\RedPacketService;
use App\Support\Api\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RedPacketController extends Controller
{
    public function index(Request $request, RedPacketService $service): View|JsonResponse
    {
        $activity = $service->activity($request->user());

        return $request->is('api/*') ? ApiResponse::success($activity) : view('red-packet.index', compact('activity'));
    }

    public function draw(Request $request, RedPacketService $service): JsonResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid']]);
        try {
            $draw = $service->draw($request->user(), $data['request_id']);

            return ApiResponse::success([
                'prize' => $draw->only(['id', 'prize_name', 'prize_type', 'amount', 'discount_percent', 'created_at']),
                'remaining_chances' => $service->remainingChances($request->user()),
            ]);
        } catch (DomainException $exception) {
            return ApiResponse::error($exception->getMessage());
        }
    }

    public function history(Request $request): View|JsonResponse
    {
        $draws = RedPacketDraw::query()->where('user_id', $request->user()->id)->latest('id')->paginate(15, ['prize_name', 'prize_type', 'amount', 'discount_percent', 'created_at'], 'draws_page');
        $entries = RedPacketEntry::query()->where('user_id', $request->user()->id)->with(['invitee:id,username', 'purchase:id,meta'])
            ->latest('id')->paginate(15, ['id', 'invited_user_id', 'user_product_id', 'source', 'earned_on', 'used_at', 'created_at'], 'entries_page');

        if ($request->is('api/*')) {
            $entries->through(fn (RedPacketEntry $entry): array => [
                ...$entry->only(['id', 'source', 'earned_on', 'used_at', 'created_at']),
                'username' => $entry->invitee?->username,
                'product_code' => $entry->purchase?->meta['product_code_snapshot'] ?? null,
            ]);

            return ApiResponse::success(compact('draws', 'entries'));
        }

        return view('red-packet.history', compact('draws', 'entries'));
    }
}
