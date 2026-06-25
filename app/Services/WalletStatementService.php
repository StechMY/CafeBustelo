<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Api\ApiFormatter;
use App\Support\Api\DisplayValue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WalletStatementService
{
    public function statement(User $user, array $filters = [], int $perPage = 15): array
    {
        $filters = $this->normalizeFilters($filters);

        $rechargeWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->first();

        $withdrawableWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->first();

        $transactionsQuery = $this->transactionsQuery($user, $filters);

        $allFilteredCredit = (clone $transactionsQuery)
            ->where('direction', WalletTransaction::DIRECTION_CREDIT)
            ->sum('amount');

        $allFilteredDebit = (clone $transactionsQuery)
            ->where('direction', WalletTransaction::DIRECTION_DEBIT)
            ->sum('amount');

        $allFilteredTotal = (clone $transactionsQuery)->sum('amount');

        $transactions = $transactionsQuery
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $pageSummary = $this->summaryFromTransactions($transactions->getCollection());

        $totalCredit = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('direction', WalletTransaction::DIRECTION_CREDIT)
            ->sum('amount');

        $totalDebit = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('direction', WalletTransaction::DIRECTION_DEBIT)
            ->sum('amount');

        return [
            'wallets' => [
                Wallet::TYPE_RECHARGE => $rechargeWallet,
                Wallet::TYPE_WITHDRAWABLE => $withdrawableWallet,
            ],
            'summary' => [
                'lifetime' => [
                    ...DisplayValue::moneyFields($user, 'total_credit', 'display_wallet_total_credit', $totalCredit),
                    ...DisplayValue::moneyFields($user, 'total_debit', 'display_wallet_total_debit', $totalDebit),
                ],
                'all_filtered' => $this->moneySummary($allFilteredTotal, $allFilteredCredit, $allFilteredDebit),
                'current_page' => $this->moneySummary(
                    $pageSummary['total'],
                    $pageSummary['credit'],
                    $pageSummary['debit'],
                ),
            ],
            'filters' => $filters,
            'transactions' => $transactions,
            'transaction_types' => WalletTransaction::query()
                ->where('user_id', $user->id)
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->values(),
        ];
    }

    public function transactionsQuery(User $user, array $filters = []): Builder
    {
        $filters = $this->normalizeFilters($filters);

        $query = WalletTransaction::query()
            ->where('user_id', $user->id);

        if ($filters['wallet_type'] !== 'all') {
            $query->where('wallet_type', $filters['wallet_type']);
        }

        if ($filters['direction'] !== 'all') {
            $query->where('direction', $filters['direction']);
        }

        if ($filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if ($filters['start_date']) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if ($filters['end_date']) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        return $query;
    }

    public function normalizeFilters(array $filters): array
    {
        return [
            'wallet_type' => $filters['wallet_type'] ?? 'all',
            'direction' => $filters['direction'] ?? 'all',
            'type' => $filters['type'] ?? 'all',
            'start_date' => $filters['start_date'] ?? null,
            'end_date' => $filters['end_date'] ?? null,
        ];
    }

    private function summaryFromTransactions(Collection $transactions): array
    {
        $credit = $transactions
            ->where('direction', WalletTransaction::DIRECTION_CREDIT)
            ->sum('amount');

        $debit = $transactions
            ->where('direction', WalletTransaction::DIRECTION_DEBIT)
            ->sum('amount');

        return [
            'total' => $transactions->sum('amount'),
            'credit' => $credit,
            'debit' => $debit,
        ];
    }

    private function moneySummary(mixed $total, mixed $credit, mixed $debit): array
    {
        $total = ApiFormatter::money($total);
        $credit = ApiFormatter::money($credit);
        $debit = ApiFormatter::money($debit);
        $net = ApiFormatter::money($credit - $debit);

        return [
            'total' => $total,
            'credit' => $credit,
            'debit' => $debit,
            'net' => $net,
            'formatted_total' => ApiFormatter::formattedMoney($total),
            'formatted_credit' => ApiFormatter::formattedMoney($credit),
            'formatted_debit' => ApiFormatter::formattedMoney($debit),
            'formatted_net' => ApiFormatter::formattedMoney($net),
        ];
    }
}
