<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletStatementService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request, WalletStatementService $walletStatementService): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate($this->rules());
        $statement = $walletStatementService->statement(
            $user,
            $validated,
            (int) ($validated['per_page'] ?? 15),
        );
        $transactions = $statement['transactions'];

        return ApiResponse::success([
            'wallets' => [
                'recharge' => ApiFormatter::wallet($statement['wallets'][Wallet::TYPE_RECHARGE], Wallet::TYPE_RECHARGE),
                'withdrawable' => ApiFormatter::wallet($statement['wallets'][Wallet::TYPE_WITHDRAWABLE], Wallet::TYPE_WITHDRAWABLE),
            ],
            'summary' => $statement['summary'],
            'filters' => $statement['filters'],
            'transaction_types' => $statement['transaction_types'],
            'transactions' => $transactions
                ->getCollection()
                ->map(fn (WalletTransaction $transaction): array => ApiFormatter::walletTransaction($transaction))
                ->values(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    public function transactions(Request $request, WalletStatementService $walletStatementService): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $query = $walletStatementService
            ->transactionsQuery($request->user(), $validated)
            ->latest();

        return ApiResponse::paginated(
            $query->paginate((int) ($validated['per_page'] ?? 15)),
            fn (WalletTransaction $transaction): array => ApiFormatter::walletTransaction($transaction),
        );
    }

    private function rules(): array
    {
        return [
            'wallet_type' => ['nullable', 'string', 'in:all,'.Wallet::TYPE_RECHARGE.','.Wallet::TYPE_WITHDRAWABLE],
            'direction' => ['nullable', 'string', 'in:all,'.WalletTransaction::DIRECTION_CREDIT.','.WalletTransaction::DIRECTION_DEBIT],
            'type' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
