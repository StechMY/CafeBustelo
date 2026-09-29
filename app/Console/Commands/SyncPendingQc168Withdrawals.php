<?php

namespace App\Console\Commands;

use App\Models\WithdrawalPayoutAttempt;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use Illuminate\Console\Command;
use Throwable;

class SyncPendingQc168Withdrawals extends Command
{
    protected $signature = 'qc168:sync-pending-withdrawals {--limit=50}';

    protected $description = 'Reconcile unresolved QC168 withdrawal payout attempts';

    public function handle(Qc168Gateway $gateway, Qc168WithdrawalPayoutService $payoutService): int
    {
        if (! $gateway->isQueryAvailable()) {
            $this->warn('QC168 order inquiry is disabled or incompletely configured.');

            return self::SUCCESS;
        }

        // Finish the current inquiry, then yield well before the five-minute scheduler lock expires.
        $stopAt = now()->addSeconds(45);
        $limit = max(1, min((int) $this->option('limit'), 200));
        $attempts = WithdrawalPayoutAttempt::query()
            ->where('provider', 'qc168')
            ->whereIn('gateway_status', [
                WithdrawalPayoutAttempt::STATUS_CREATE_PENDING,
                WithdrawalPayoutAttempt::STATUS_NONE,
                WithdrawalPayoutAttempt::STATUS_PENDING,
                WithdrawalPayoutAttempt::STATUS_PREPARING,
                WithdrawalPayoutAttempt::STATUS_PROCESSING,
            ])
            ->whereHas('withdrawRequest', function ($query): void {
                $query->whereNotIn('payout_status', ['completed', 'cancelled']);
            })
            ->where(function ($query): void {
                $query->whereNull('submitted_at')
                    ->orWhere('submitted_at', '<=', now()->subMinutes(2));
            })
            ->whereHas('withdrawRequest', function ($query): void {
                $query->whereNull('gateway_last_synced_at')
                    ->orWhere('gateway_last_synced_at', '<=', now()->subMinutes(5));
            })
            ->oldest('id')
            ->limit($limit)
            ->get();

        foreach ($attempts as $attempt) {
            if (now()->greaterThanOrEqualTo($stopAt)) {
                $this->line('Time budget reached; remaining attempts will be picked up by the next run.');

                break;
            }

            try {
                $result = $payoutService->syncAttempt($attempt, 'qc168_scheduler_sync');
                $this->line("{$attempt->merchant_order_id}: {$result['result']}");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$attempt->merchant_order_id}: sync failed");
            }
        }

        return self::SUCCESS;
    }
}
