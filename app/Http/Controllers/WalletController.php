<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $walletType = $request->query('wallet_type', 'all');
        $direction = $request->query('direction', 'all');
        $type = $request->query('type', 'all');

        $rechargeWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->first();

        $withdrawableWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->first();

        $transactionsQuery = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->latest();

        if ($walletType !== 'all') {
            $transactionsQuery->where('wallet_type', $walletType);
        }

        if ($direction !== 'all') {
            $transactionsQuery->where('direction', $direction);
        }

        if ($type !== 'all') {
            $transactionsQuery->where('type', $type);
        }

        $transactions = $transactionsQuery
            ->paginate(15)
            ->withQueryString();

        $transactionTypes = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->values();

        $summary = [
            'recharge_balance' => $rechargeWallet?->balance ?? 0,
            'recharge_locked' => $rechargeWallet?->locked_balance ?? 0,

            'withdrawable_balance' => $withdrawableWallet?->balance ?? 0,
            'withdrawable_locked' => $withdrawableWallet?->locked_balance ?? 0,

            'total_credit' => WalletTransaction::query()
                ->where('user_id', $user->id)
                ->where('direction', WalletTransaction::DIRECTION_CREDIT)
                ->sum('amount'),

            'total_debit' => WalletTransaction::query()
                ->where('user_id', $user->id)
                ->where('direction', WalletTransaction::DIRECTION_DEBIT)
                ->sum('amount'),
        ];

        $summary['total_credit'] = (float) $this->displayOverride(
            $user,
            'display_wallet_total_credit',
            $summary['total_credit']
        );

        $summary['total_debit'] = (float) $this->displayOverride(
            $user,
            'display_wallet_total_debit',
            $summary['total_debit']
        );

        return view('wallet.index', [
            'user' => $user,
            'summary' => $summary,
            'transactions' => $transactions,
            'transactionTypes' => $transactionTypes,
            'walletType' => $walletType,
            'direction' => $direction,
            'type' => $type,
        ]);
    }

    public function show(Request $request, WalletTransaction $walletTransaction): View
    {
        abort_unless((int) $walletTransaction->user_id === (int) $request->user()->id, 403);

        return view('wallet.show', [
            'walletTransaction' => $walletTransaction,
        ]);
    }

    private function displayOverride($user, string $field, mixed $realValue): mixed
    {
        $value = $user->getAttribute($field);

        return $value !== null && $value !== ''
            ? $value
            : $realValue;
    }
}