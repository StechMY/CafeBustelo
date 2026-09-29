<?php

namespace App\Console\Commands;

use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use Illuminate\Console\Command;
use Throwable;

class DispatchApprovedQc168Withdrawals extends Command
{
    protected $signature = 'qc168:dispatch-approved-withdrawals {--limit=50}';

    protected $description = 'Submit approved QC168 withdrawals after their configured hold';

    public function handle(Qc168Gateway $gateway, Qc168WithdrawalPayoutService $payoutService): int
    {
        if (! $gateway->isWithdrawalAvailable()) {
            $this->warn('QC168 withdrawal is disabled or incompletely configured.');

            return self::SUCCESS;
        }

        // Finish the current order, then yield well before the five-minute scheduler lock expires.
        $stopAt = now()->addSeconds(45);
        $limit = max(1, min((int) $this->option('limit'), 200));
        $ids = WithdrawRequest::query()
            ->where('status', WithdrawRequest::STATUS_APPROVED)
            ->where('gateway_provider', WithdrawRequest::GATEWAY_QC168)
            ->whereIn('approval_mode', [
                WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED,
                WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT,
            ])
            ->where('payout_status', WithdrawRequest::PAYOUT_WAITING_RELEASE)
            ->where('gateway_release_at', '<=', now())
            ->oldest('gateway_release_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            if (now()->greaterThanOrEqualTo($stopAt)) {
                $this->line('Time budget reached; remaining withdrawals will be picked up by the next run.');

                break;
            }

            try {
                $withdrawRequest = WithdrawRequest::query()->find($id);
                $attempt = $withdrawRequest ? $payoutService->dispatch($withdrawRequest) : null;

                if ($attempt) {
                    $this->line("{$attempt->merchant_order_id}: submitted");
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Withdrawal {$id}: dispatch failed");
            }
        }

        return self::SUCCESS;
    }
}
