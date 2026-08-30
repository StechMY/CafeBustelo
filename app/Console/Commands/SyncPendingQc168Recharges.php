<?php

namespace App\Console\Commands;

use App\Models\RechargeRequest;
use App\Services\PaymentGateway\Qc168CallbackService;
use App\Services\PaymentGateway\Qc168Gateway;
use Illuminate\Console\Command;
use Throwable;

class SyncPendingQc168Recharges extends Command
{
    protected $signature = 'qc168:sync-pending-recharges {--limit=50}';

    protected $description = 'Reconcile pending QC168 gateway recharge requests';

    public function handle(Qc168Gateway $gateway, Qc168CallbackService $callbackService): int
    {
        if (! $gateway->isAvailable()) {
            $this->warn('QC168 is disabled or incompletely configured.');

            return self::SUCCESS;
        }

        $limit = max(1, min((int) $this->option('limit'), 200));
        $requests = RechargeRequest::query()
            ->where('deposit_method', RechargeRequest::METHOD_GATEWAY)
            ->where('gateway_provider', RechargeRequest::GATEWAY_QC168)
            ->where('status', 'pending')
            ->whereIn('gateway_payment_status', ['create_pending', 'none', 'pending', 'preparing', 'processing', 'completed'])
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where(function ($query): void {
                $query->whereNull('gateway_last_synced_at')
                    ->orWhere('gateway_last_synced_at', '<=', now()->subMinutes(5));
            })
            ->oldest('id')
            ->limit($limit)
            ->get();

        foreach ($requests as $rechargeRequest) {
            try {
                $result = $callbackService->sync($rechargeRequest);
                $this->line("{$rechargeRequest->merchant_order_id}: {$result['result']}");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$rechargeRequest->merchant_order_id}: sync failed");
            }
        }

        return self::SUCCESS;
    }
}
