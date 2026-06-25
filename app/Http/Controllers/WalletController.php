<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletStatementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request, WalletStatementService $walletStatementService): View
    {
        $user = $request->user();

        $validated = $request->validate([
            'wallet_type' => ['nullable', 'string', 'in:all,'.Wallet::TYPE_RECHARGE.','.Wallet::TYPE_WITHDRAWABLE],
            'direction' => ['nullable', 'string', 'in:all,'.WalletTransaction::DIRECTION_CREDIT.','.WalletTransaction::DIRECTION_DEBIT],
            'type' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $statement = $walletStatementService->statement($user, $validated);
        $filters = $statement['filters'];
        $statementSummary = $statement['summary'];
        $rechargeWallet = $statement['wallets'][Wallet::TYPE_RECHARGE];
        $withdrawableWallet = $statement['wallets'][Wallet::TYPE_WITHDRAWABLE];

        $summary = [
            'recharge_balance' => $rechargeWallet?->balance ?? 0,
            'recharge_locked' => $rechargeWallet?->locked_balance ?? 0,

            'withdrawable_balance' => $withdrawableWallet?->balance ?? 0,
            'withdrawable_locked' => $withdrawableWallet?->locked_balance ?? 0,

            'total_credit' => $statementSummary['lifetime']['display_total_credit'],
            'total_debit' => $statementSummary['lifetime']['display_total_debit'],

            'all_filtered_total' => $statementSummary['all_filtered']['total'],
            'all_filtered_credit' => $statementSummary['all_filtered']['credit'],
            'all_filtered_debit' => $statementSummary['all_filtered']['debit'],
            'all_filtered_net' => $statementSummary['all_filtered']['net'],

            'page_total' => $statementSummary['current_page']['total'],
            'page_credit' => $statementSummary['current_page']['credit'],
            'page_debit' => $statementSummary['current_page']['debit'],
            'page_net' => $statementSummary['current_page']['net'],
        ];

        return view('wallet.index', [
            'user' => $user,
            'summary' => $summary,
            'transactions' => $statement['transactions'],
            'transactionTypes' => $statement['transaction_types'],
            'walletType' => $filters['wallet_type'],
            'direction' => $filters['direction'],
            'type' => $filters['type'],
            'startDate' => $filters['start_date'],
            'endDate' => $filters['end_date'],
        ]);
    }

    public function show(Request $request, WalletTransaction $walletTransaction): View
    {
        abort_unless((int) $walletTransaction->user_id === (int) $request->user()->id, 403);

        return view('wallet.show', [
            'walletTransaction' => $walletTransaction,
        ]);
    }
}
