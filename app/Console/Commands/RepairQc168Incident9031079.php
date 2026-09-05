<?php

namespace App\Console\Commands;

use App\Models\PaymentGatewayEvent;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\PaymentGatewayEventRecorder;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RepairQc168Incident9031079 extends Command
{
    private const MERCHANT_ORDER_ID = 'WD1079-A1-01M1P8PJRQX3R814QM7QRX4Y87';

    protected $signature = 'qc168:repair-incident-903-1079
                            {--dry-run : Validate and report without changing data}
                            {--apply : Apply the guarded one-time repair}';

    protected $description = 'Guarded one-time local reconciliation for QC168 withdrawals #903 and #1079';

    public function handle(
        Qc168WithdrawalPayoutService $payoutService,
        PaymentGatewayEventRecorder $eventRecorder,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Choose exactly one mode: --dry-run or --apply.');

            return self::FAILURE;
        }

        $this->info('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));

        try {
            $report = DB::transaction(function () use ($apply, $payoutService, $eventRecorder): array {
                $withdrawals = WithdrawRequest::query()
                    ->whereIn('id', [903, 1079, 1113])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $withdrawal903 = $withdrawals->get(903);
                $withdrawal1079 = $withdrawals->get(1079);
                $withdrawal1113 = $withdrawals->get(1113);

                $this->expect($withdrawal903 instanceof WithdrawRequest, 'withdrawal #903 exists', true, false);
                $this->expect($withdrawal1079 instanceof WithdrawRequest, 'withdrawal #1079 exists', true, false);
                $this->expect($withdrawal1113 instanceof WithdrawRequest, 'withdrawal #1113 exists', true, false);

                $attempt = WithdrawalPayoutAttempt::query()
                    ->where('withdraw_request_id', 1079)
                    ->where('merchant_order_id', self::MERCHANT_ORDER_ID)
                    ->lockForUpdate()
                    ->first();
                $this->expect($attempt instanceof WithdrawalPayoutAttempt, '#1079 payout attempt exists', true, false);

                $wallet = Wallet::query()
                    ->whereKey(1110)
                    ->lockForUpdate()
                    ->first();
                $this->expect($wallet instanceof Wallet, 'wallet #1110 exists', true, false);

                $cancellationLedgers = WalletTransaction::query()
                    ->where('type', 'withdraw_gateway_cancelled')
                    ->where('reference_type', WithdrawRequest::class)
                    ->where('reference_id', 903)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $completedEvent = PaymentGatewayEvent::query()
                    ->where('provider', WithdrawRequest::GATEWAY_QC168)
                    ->where('withdraw_request_id', 1079)
                    ->where('withdrawal_payout_attempt_id', $attempt->id)
                    ->where('merchant_order_id', self::MERCHANT_ORDER_ID)
                    ->where('payment_status', WithdrawalPayoutAttempt::STATUS_COMPLETED)
                    ->where('signature_valid', true)
                    ->oldest('id')
                    ->first();

                $this->assertPreconditions(
                    $wallet,
                    $withdrawal903,
                    $withdrawal1079,
                    $withdrawal1113,
                    $attempt,
                    $cancellationLedgers,
                    $completedEvent,
                );

                $attemptCount = WithdrawalPayoutAttempt::query()
                    ->where('withdraw_request_id', 1079)
                    ->count();
                $walletLedgerCount = WalletTransaction::query()
                    ->where('wallet_id', 1110)
                    ->count();
                $before = $this->snapshot(
                    $wallet,
                    $withdrawal903,
                    $withdrawal1079,
                    $withdrawal1113,
                    $cancellationLedgers,
                    $completedEvent,
                    $attemptCount,
                    $walletLedgerCount,
                );

                if (! $apply) {
                    return ['before' => $before, 'after' => null];
                }

                $eventRecorder->recordWithdrawal(
                    'withdrawal.financial_incident_reconciled',
                    $withdrawal903,
                    source: 'artisan_repair',
                    reason: 'Withdrawal #903 was cancelled twice, releasing 180000 twice and causing a 180000 locked-reserve shortfall for withdrawal #1079.',
                    paymentStatus: WithdrawRequest::PAYOUT_CANCELLED,
                    payload: [
                        'repair_guard' => 'qc168_incident_903_1079_v1',
                        'duplicate_cancellation_withdraw_request_id' => 903,
                        'affected_withdraw_request_id' => 1079,
                        'protected_withdraw_request_id' => 1113,
                        'locked_reserve_adjustment' => '180000.00',
                        'available_balance_adjustment' => '0.00',
                        'wallet_before' => [
                            'id' => $wallet->id,
                            'balance' => $wallet->balance,
                            'locked_balance' => $wallet->locked_balance,
                        ],
                    ],
                    processingResult: 'locked_reserve_reconciled',
                );

                $wallet->forceFill([
                    'locked_balance' => $this->money((float) $wallet->locked_balance + 180000),
                ])->save();

                $result = $payoutService->processProviderState(
                    $attempt,
                    $completedEvent->payload,
                    'artisan_incident_repair',
                );
                $this->expect($result['result'] ?? null, '#1079 local finalization result', 'payout_completed');

                $wallet->refresh();
                $withdrawal1079->refresh();
                $withdrawal1113->refresh();

                $this->expectMoney($wallet->balance, 'final wallet balance', 18455);
                $this->expectMoney($wallet->locked_balance, 'final wallet locked_balance', 120000);
                $this->expect($withdrawal1079->payout_status, '#1079 final payout_status', WithdrawRequest::PAYOUT_COMPLETED);
                $this->expect($withdrawal1079->gateway_payment_status, '#1079 final gateway_payment_status', WithdrawalPayoutAttempt::STATUS_COMPLETED);
                $this->expect($withdrawal1113->payout_status, '#1113 final payout_status', WithdrawRequest::PAYOUT_WAITING_RELEASE);
                $this->expect(
                    WithdrawalPayoutAttempt::query()->where('withdraw_request_id', 1079)->count(),
                    'final #1079 payout attempt count',
                    $attemptCount,
                );
                $this->expect(
                    WalletTransaction::query()->where('wallet_id', 1110)->count(),
                    'final wallet ledger count',
                    $walletLedgerCount,
                );

                $remainingReserved = WithdrawRequest::query()
                    ->where('user_id', 555)
                    ->withReservedFunds()
                    ->orderBy('id')
                    ->get(['id', 'amount']);
                $this->expect($remainingReserved->pluck('id')->all(), 'remaining reserved withdrawal IDs', [1113]);
                $this->expectMoney($remainingReserved->sum('amount'), 'remaining reserved withdrawal total', 120000);

                return [
                    'before' => $before,
                    'after' => $this->snapshot(
                        $wallet,
                        $withdrawal903->fresh(),
                        $withdrawal1079,
                        $withdrawal1113,
                        $cancellationLedgers,
                        $completedEvent,
                        $attemptCount,
                        $walletLedgerCount,
                    ),
                ];
            });
        } catch (Throwable $exception) {
            $this->error('Precondition failed; transaction rolled back.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->renderReport('Before', $report['before']);

        if (! $apply) {
            $this->warn('No changes were made. Re-run with --apply only after reviewing this report.');

            return self::SUCCESS;
        }

        $this->renderReport('After', $report['after']);
        $this->info('Repair committed. No available balance was changed and QC168 was not contacted.');

        return self::SUCCESS;
    }

    private function assertPreconditions(
        Wallet $wallet,
        WithdrawRequest $withdrawal903,
        WithdrawRequest $withdrawal1079,
        WithdrawRequest $withdrawal1113,
        WithdrawalPayoutAttempt $attempt,
        $cancellationLedgers,
        ?PaymentGatewayEvent $completedEvent,
    ): void {
        $this->expect($wallet->id, 'wallet ID', 1110);
        $this->expect($wallet->user_id, 'wallet user ID', 555);
        $this->expect($wallet->type, 'wallet type', Wallet::TYPE_WITHDRAWABLE);
        $this->expectMoney($wallet->balance, 'wallet balance', 18455);
        $this->expectMoney($wallet->locked_balance, 'wallet locked_balance', 240000);

        $this->expect($withdrawal903->user_id, '#903 user ID', 555);
        $this->expectMoney($withdrawal903->amount, '#903 amount', 180000);
        $this->expect($withdrawal903->payout_status, '#903 payout_status', WithdrawRequest::PAYOUT_CANCELLED);
        $this->expect($cancellationLedgers->count(), '#903 cancellation ledger count', 2);
        $this->expectMoney($cancellationLedgers->sum('amount'), '#903 total cancellation credits', 360000);
        foreach ($cancellationLedgers as $ledger) {
            $this->expect($ledger->user_id, "#903 cancellation ledger {$ledger->id} user ID", 555);
            $this->expect($ledger->wallet_id, "#903 cancellation ledger {$ledger->id} wallet ID", 1110);
            $this->expect($ledger->direction, "#903 cancellation ledger {$ledger->id} direction", WalletTransaction::DIRECTION_CREDIT);
            $this->expectMoney($ledger->amount, "#903 cancellation ledger {$ledger->id} amount", 180000);
        }

        $this->expect($withdrawal1079->user_id, '#1079 user ID', 555);
        $this->expectMoney($withdrawal1079->amount, '#1079 amount', 300000);
        $this->expectMoney($withdrawal1079->net_amount, '#1079 net_amount', 279000);
        $this->expect($withdrawal1079->gateway_current_merchant_order_id, '#1079 merchant_order_id', self::MERCHANT_ORDER_ID);
        $this->expect($withdrawal1079->status, '#1079 status', WithdrawRequest::STATUS_APPROVED);
        $this->expect($attempt->merchant_order_id, '#1079 payout attempt merchant_order_id', self::MERCHANT_ORDER_ID);
        $this->expectMoney($attempt->amount, '#1079 payout attempt amount', 279000);
        $this->expect($attempt->provider, '#1079 payout attempt provider', WithdrawRequest::GATEWAY_QC168);
        $this->expect(strtolower($attempt->currency), '#1079 payout attempt currency', 'vnd');
        $this->expect($attempt->payment_channel, '#1079 payout attempt payment', 'vn02');

        $this->expect($completedEvent instanceof PaymentGatewayEvent, '#1079 signed completed QC168 event exists', true, false);
        $payload = $completedEvent->payload;
        $this->expect(is_array($payload), '#1079 completed event payload is an array', true, false);
        $this->expect($payload['payment_status'] ?? null, '#1079 event payload payment_status', WithdrawalPayoutAttempt::STATUS_COMPLETED);
        $this->expect($payload['merchant_order_id'] ?? null, '#1079 event payload merchant_order_id', self::MERCHANT_ORDER_ID);
        $this->expect($payload['merchant_uid'] ?? null, '#1079 event payload merchant_uid', (string) config('services.qc168.merchant_uid'));
        $this->expectMoney($payload['amount'] ?? null, '#1079 event payload amount', 279000);
        $this->expect($payload['transaction_type'] ?? null, '#1079 event transaction_type', 'expense');
        $this->expect($payload['accounting_type'] ?? null, '#1079 event accounting_type', 'pay');
        $this->expect(strtolower((string) ($payload['currency'] ?? '')), '#1079 event currency', 'vnd');
        $this->expect($payload['payment'] ?? null, '#1079 event payment', 'vn02');

        $this->expect($withdrawal1113->user_id, '#1113 user ID', 555);
        $this->expectMoney($withdrawal1113->amount, '#1113 amount', 120000);
        $this->expect($withdrawal1113->status, '#1113 status', WithdrawRequest::STATUS_APPROVED);
        $this->expect($withdrawal1113->payout_status, '#1113 payout_status', WithdrawRequest::PAYOUT_WAITING_RELEASE);

        $reserved = WithdrawRequest::query()
            ->where('user_id', 555)
            ->withReservedFunds()
            ->orderBy('id')
            ->get(['id', 'amount']);
        $this->expect($reserved->pluck('id')->all(), 'expected reserved withdrawal IDs', [1079, 1113]);
        $this->expectMoney($reserved->sum('amount'), 'expected reserved withdrawal total', 420000);

        $alreadyRepaired = PaymentGatewayEvent::query()
            ->where('withdraw_request_id', 903)
            ->where('event_type', 'withdrawal.financial_incident_reconciled')
            ->where('source', 'artisan_repair')
            ->exists();
        $this->expect($alreadyRepaired, 'existing incident repair event', false);
    }

    private function snapshot(
        Wallet $wallet,
        WithdrawRequest $withdrawal903,
        WithdrawRequest $withdrawal1079,
        WithdrawRequest $withdrawal1113,
        $cancellationLedgers,
        PaymentGatewayEvent $completedEvent,
        int $attemptCount,
        int $walletLedgerCount,
    ): array {
        return [
            ['wallet ID / user ID', "{$wallet->id} / {$wallet->user_id}"],
            ['wallet #1110 balance', $wallet->balance],
            ['wallet #1110 locked_balance', $wallet->locked_balance],
            ['#903 amount', $withdrawal903->amount],
            ['#903 payout_status', $withdrawal903->payout_status],
            ['#903 cancellation ledger rows', (string) $cancellationLedgers->count()],
            ['#903 cancellation credits', $this->money($cancellationLedgers->sum('amount'))],
            ['#1079 status', $withdrawal1079->status],
            ['#1079 amount / net_amount', "{$withdrawal1079->amount} / {$withdrawal1079->net_amount}"],
            ['#1079 merchant_order_id', (string) $withdrawal1079->gateway_current_merchant_order_id],
            ['#1079 payout_status', (string) $withdrawal1079->payout_status],
            ['#1079 gateway_payment_status', (string) $withdrawal1079->gateway_payment_status],
            ['#1079 completed event ID', (string) $completedEvent->id],
            ['#1079 completed event payment_status', (string) $completedEvent->payment_status],
            ['#1079 completed event signature_valid', $completedEvent->signature_valid ? '1' : '0'],
            ['#1079 payout attempt rows', (string) $attemptCount],
            ['wallet #1110 ledger rows', (string) $walletLedgerCount],
            ['#1113 status', $withdrawal1113->status],
            ['#1113 payout_status', $withdrawal1113->payout_status],
            ['#1113 reserved amount', $withdrawal1113->amount],
        ];
    }

    private function renderReport(string $heading, array $rows): void
    {
        $this->newLine();
        $this->info($heading);
        $this->table(['Field', 'Value'], $rows);
    }

    private function expect(mixed $actual, string $label, mixed $expected, mixed $missing = null): void
    {
        if ($actual !== $expected) {
            $actual = $actual === $missing ? 'missing' : json_encode($actual, JSON_UNESCAPED_SLASHES);
            $expected = json_encode($expected, JSON_UNESCAPED_SLASHES);

            throw new RuntimeException("{$label} mismatch: expected {$expected}, got {$actual}.");
        }
    }

    private function expectMoney(mixed $actual, string $label, float|int $expected): void
    {
        $this->expect($this->money($actual), $label, $this->money($expected));
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
