<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $validated = $request->validate([
            'wallet_type' => ['nullable', 'string', 'in:all,' . Wallet::TYPE_RECHARGE . ',' . Wallet::TYPE_WITHDRAWABLE],
            'direction' => ['nullable', 'string', 'in:all,' . WalletTransaction::DIRECTION_CREDIT . ',' . WalletTransaction::DIRECTION_DEBIT],
            'type' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $walletType = $validated['wallet_type'] ?? 'all';
        $direction = $validated['direction'] ?? 'all';
        $type = $validated['type'] ?? 'all';
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $rechargeWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->first();

        $withdrawableWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->first();

        $transactionsQuery = WalletTransaction::query()
            ->where('user_id', $user->id);

        $this->applyFilters(
            query: $transactionsQuery,
            walletType: $walletType,
            direction: $direction,
            type: $type,
            startDate: $startDate,
            endDate: $endDate,
        );

        $allFilteredCredit = (clone $transactionsQuery)
            ->where('direction', WalletTransaction::DIRECTION_CREDIT)
            ->sum('amount');

        $allFilteredDebit = (clone $transactionsQuery)
            ->where('direction', WalletTransaction::DIRECTION_DEBIT)
            ->sum('amount');

        $allFilteredTotal = (clone $transactionsQuery)->sum('amount');
        $allFilteredNet = (float) $allFilteredCredit - (float) $allFilteredDebit;

        $transactions = $transactionsQuery
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $pageCredit = $transactions
            ->getCollection()
            ->where('direction', WalletTransaction::DIRECTION_CREDIT)
            ->sum('amount');

        $pageDebit = $transactions
            ->getCollection()
            ->where('direction', WalletTransaction::DIRECTION_DEBIT)
            ->sum('amount');

        $pageTotal = $transactions
            ->getCollection()
            ->sum('amount');

        $pageNet = (float) $pageCredit - (float) $pageDebit;

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

            'all_filtered_total' => $allFilteredTotal,
            'all_filtered_credit' => $allFilteredCredit,
            'all_filtered_debit' => $allFilteredDebit,
            'all_filtered_net' => $allFilteredNet,

            'page_total' => $pageTotal,
            'page_credit' => $pageCredit,
            'page_debit' => $pageDebit,
            'page_net' => $pageNet,
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
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function show(Request $request, WalletTransaction $walletTransaction): View
    {
        abort_unless((int) $walletTransaction->user_id === (int) $request->user()->id, 403);

        return view('wallet.show', [
            'walletTransaction' => $walletTransaction,
        ]);
    }

    private function applyFilters(
        mixed $query,
        string $walletType,
        string $direction,
        string $type,
        ?string $startDate,
        ?string $endDate,
    ): void {
        if ($walletType !== 'all') {
            $query->where('wallet_type', $walletType);
        }

        if ($direction !== 'all') {
            $query->where('direction', $direction);
        }

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }
    }

    private function displayOverride($user, string $field, mixed $realValue): mixed
    {
        $value = $user->getAttribute($field);

        return $value !== null && $value !== ''
            ? $value
            : $realValue;
    }
}