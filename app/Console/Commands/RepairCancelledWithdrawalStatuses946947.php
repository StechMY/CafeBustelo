<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\PaymentGatewayEventRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RepairCancelledWithdrawalStatuses946947 extends Command
{
    private const INCIDENTS = [
        946 => ['user_id' => 723, 'amount' => 200000],
        947 => ['user_id' => 700, 'amount' => 223000],
    ];

    protected $signature = 'withdrawals:repair-cancelled-status-946-947
                            {--dry-run : Validate and report without changing data}
                            {--apply : Apply the guarded status-only repair}';

    protected $description = 'Guarded one-time status repair for cancelled withdrawals #946 and #947';

    public function handle(PaymentGatewayEventRecorder $eventRecorder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Choose exactly one mode: --dry-run or --apply.');

            return self::FAILURE;
        }

        $this->info('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));

        try {
            $report = DB::transaction(function () use ($apply, $eventRecorder): array {
                $withdrawals = WithdrawRequest::query()
                    ->whereIn('id', array_keys(self::INCIDENTS))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $walletsBefore = Wallet::query()
                    ->whereIn('user_id', array_column(self::INCIDENTS, 'user_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id', 'user_id', 'balance', 'locked_balance'])
                    ->toArray();
                $before = [];

                foreach (self::INCIDENTS as $withdrawalId => $expected) {
                    $withdrawal = $withdrawals->get($withdrawalId);
                    $this->expect($withdrawal instanceof WithdrawRequest, "withdrawal #{$withdrawalId} exists", true, false);

                    $ledgers = WalletTransaction::query()
                        ->where('type', 'withdraw_gateway_cancelled')
                        ->where('reference_type', WithdrawRequest::class)
                        ->where('reference_id', $withdrawalId)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $this->expect($withdrawal->user_id, "#{$withdrawalId} user ID", $expected['user_id']);
                    $this->expect($this->money($withdrawal->amount), "#{$withdrawalId} amount", $this->money($expected['amount']));
                    $this->expect($withdrawal->payout_status, "#{$withdrawalId} payout_status", WithdrawRequest::PAYOUT_REJECTED);
                    $this->expect($ledgers->count(), "#{$withdrawalId} cancellation ledger count", 1);
                    $this->expect($this->money($ledgers->sum('amount')), "#{$withdrawalId} cancellation ledger total", $this->money($expected['amount']));
                    $this->expect($ledgers->first()->user_id, "#{$withdrawalId} cancellation ledger user ID", $expected['user_id']);
                    $this->expect($ledgers->first()->direction, "#{$withdrawalId} cancellation ledger direction", WalletTransaction::DIRECTION_CREDIT);

                    $before[] = ["#{$withdrawalId}", $withdrawal->payout_status, $this->money($ledgers->sum('amount'))];
                }

                if (! $apply) {
                    return ['before' => $before, 'after' => null];
                }

                foreach (self::INCIDENTS as $withdrawalId => $expected) {
                    $withdrawal = $withdrawals->get($withdrawalId);
                    $withdrawal->forceFill(['payout_status' => WithdrawRequest::PAYOUT_CANCELLED])->save();

                    $eventRecorder->recordWithdrawal(
                        'withdrawal.cancelled_status_reconciled',
                        $withdrawal,
                        source: 'artisan_repair',
                        reason: "The existing cancellation ledger proves withdrawal #{$withdrawalId} was already refunded; restoring regressed payout_status only.",
                        paymentStatus: WithdrawRequest::PAYOUT_CANCELLED,
                        payload: [
                            'repair_guard' => 'cancelled_status_946_947_v1',
                            'previous_payout_status' => WithdrawRequest::PAYOUT_REJECTED,
                            'new_payout_status' => WithdrawRequest::PAYOUT_CANCELLED,
                            'cancellation_credit' => $this->money($expected['amount']),
                            'wallet_adjustment' => '0.00',
                        ],
                        processingResult: 'payout_status_reconciled',
                    );
                }

                $walletsAfter = Wallet::query()
                    ->whereIn('user_id', array_column(self::INCIDENTS, 'user_id'))
                    ->orderBy('id')
                    ->get(['id', 'user_id', 'balance', 'locked_balance'])
                    ->toArray();
                $this->expect($walletsAfter, 'wallet rows after status-only repair', $walletsBefore);

                return [
                    'before' => $before,
                    'after' => $withdrawals->map(
                        fn (WithdrawRequest $withdrawal): array => [
                            "#{$withdrawal->id}",
                            $withdrawal->fresh()->payout_status,
                            '0.00',
                        ]
                    )->values()->all(),
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
        $this->info('Status-only repair committed. No wallet values were changed.');

        return self::SUCCESS;
    }

    private function renderReport(string $heading, array $rows): void
    {
        $this->newLine();
        $this->info($heading);
        $this->table(['Withdrawal', 'Payout status', 'Cancellation credit / wallet adjustment'], $rows);
    }

    private function expect(mixed $actual, string $label, mixed $expected, mixed $missing = null): void
    {
        if ($actual !== $expected) {
            $actual = $actual === $missing ? 'missing' : json_encode($actual, JSON_UNESCAPED_SLASHES);
            $expected = json_encode($expected, JSON_UNESCAPED_SLASHES);

            throw new RuntimeException("{$label} mismatch: expected {$expected}, got {$actual}.");
        }
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
