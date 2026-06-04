<?php

namespace App\Http\Controllers;

use App\Models\BonusCode;
use App\Services\BonusCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class BonusCodeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $status = $request->query('status', 'all');
        $type = $request->query('type', 'all');

        $bonusCodesQuery = BonusCode::query()
            ->with(['owner', 'issuedByAdminUser', 'claim'])
            ->where('owner_user_id', $user->id)
            ->latest();

        if ($status !== 'all') {
            $bonusCodesQuery->where('status', $status);
        }

        if ($type !== 'all') {
            $bonusCodesQuery->where('type', $type);
        }

        $bonusCodes = $bonusCodesQuery
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'unused_count' => BonusCode::query()
                ->where('owner_user_id', $user->id)
                ->where('status', BonusCode::STATUS_UNUSED)
                ->count(),

            'unused_amount' => BonusCode::query()
                ->where('owner_user_id', $user->id)
                ->where('status', BonusCode::STATUS_UNUSED)
                ->sum('amount'),

            'used_count' => BonusCode::query()
                ->where('owner_user_id', $user->id)
                ->where('status', BonusCode::STATUS_USED)
                ->count(),

            'used_amount' => BonusCode::query()
                ->where('owner_user_id', $user->id)
                ->where('status', BonusCode::STATUS_USED)
                ->sum('amount'),
        ];

        return view('bonus-codes.index', [
            'user' => $user,
            'bonusCodes' => $bonusCodes,
            'stats' => $stats,
            'status' => $status,
            'type' => $type,
        ]);
    }

    public function claim(
        Request $request,
        BonusCode $bonusCode,
        BonusCodeService $bonusCodeService,
    ): RedirectResponse {
        abort_unless((int) $bonusCode->owner_user_id === (int) $request->user()->id, 403);

        try {
            $bonusCodeService->claim(
                user: $request->user(),
                bonusCode: $bonusCode,
            );

            return back()->with('success', __('bonus.claim_success'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('bonus.claim_failed'));
        }
    }
}