<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $rechargeWallet = Wallet::query()->where('user_id', $user->id)->where('type', Wallet::TYPE_RECHARGE)->first();
        $withdrawableWallet = Wallet::query()->where('user_id', $user->id)->where('type', Wallet::TYPE_WITHDRAWABLE)->first();

        return ApiResponse::success([
            'wallets' => [
                'recharge' => ApiFormatter::wallet($rechargeWallet, Wallet::TYPE_RECHARGE),
                'withdrawable' => ApiFormatter::wallet($withdrawableWallet, Wallet::TYPE_WITHDRAWABLE),
            ],
            'summary' => [
                'total_credit' => ApiFormatter::money(WalletTransaction::query()->where('user_id', $user->id)->where('direction', WalletTransaction::DIRECTION_CREDIT)->sum('amount')),
                'total_debit' => ApiFormatter::money(WalletTransaction::query()->where('user_id', $user->id)->where('direction', WalletTransaction::DIRECTION_DEBIT)->sum('amount')),
            ],
            'transaction_types' => WalletTransaction::query()
                ->where('user_id', $user->id)
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->values(),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $query = WalletTransaction::query()
            ->where('user_id', $request->user()->id)
            ->latest();

        foreach (['wallet_type', 'direction', 'type'] as $field) {
            $value = $request->query($field);
            if ($value && $value !== 'all') {
                $query->where($field, $value);
            }
        }

        return ApiResponse::paginated(
            $query->paginate((int) $request->query('per_page', 15)),
            fn (WalletTransaction $transaction): array => ApiFormatter::walletTransaction($transaction),
        );
    }
}
