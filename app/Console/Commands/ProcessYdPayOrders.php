<?php

namespace App\Console\Commands;

use App\Models\RechargeRequest;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\YdPayGateway;
use App\Services\PaymentGateway\YdPayWithdrawalService;
use App\Services\Recharge\YdPayRechargeService;
use Illuminate\Console\Command;
use Throwable;

class ProcessYdPayOrders extends Command
{
    protected $signature = 'ydpay:process-orders {business : deposit or withdraw} {--limit=30}';

    protected $description = 'Dispatch released YDPAY withdrawals and query unresolved original orders';

    public function handle(YdPayGateway $gateway, YdPayRechargeService $deposits, YdPayWithdrawalService $withdrawals): int
    {
        $business = $this->argument('business');
        if (! in_array($business, ['deposit', 'withdraw'], true)) {
            $this->error('Business must be deposit or withdraw.');

            return self::FAILURE;
        }
        if (! config('services.ydpay.enabled')) {
            return self::SUCCESS;
        }
        $limit = max(1, min(100, (int) $this->option('limit')));
        $stopAt = now()->addSeconds(45);
        $orders = RechargeRequest::query()->where('gateway_provider', 'ydpay')->where('status', 'pending')
            ->whereRaw($business === 'deposit' ? '1 = 1' : '1 = 0')
            ->orderBy('gateway_last_synced_at')->limit($limit)->get();
        foreach ($orders as $order) {
            if (now()->gte($stopAt)) {
                return self::SUCCESS;
            }
            $order->update(['gateway_last_synced_at' => now()]);
            try {
                $deposits->process($gateway->query($order->merchant_order_id, 'deposit'), 'sync');
            } catch (Throwable) {
                $this->warn("YDPAY deposit {$order->id}: query unresolved.");
            }
        }
        $requests = WithdrawRequest::query()->where('gateway_provider', 'ydpay')->where('status', 'approved')
            ->whereNotIn('payout_status', ['completed', 'cancelled', 'refund_review'])
            ->whereRaw($business === 'withdraw' ? '1 = 1' : '1 = 0')
            ->orderBy('gateway_last_synced_at')->limit($limit)->get();
        foreach ($requests as $request) {
            if (now()->gte($stopAt)) {
                break;
            }
            $request->update(['gateway_last_synced_at' => now()]);
            try {
                if ($request->payout_status === 'waiting_release') {
                    $withdrawals->dispatch($request);
                } else {
                    $withdrawals->sync($request);
                }
            } catch (Throwable) {
                $this->warn("YDPAY withdrawal {$request->id}: result unresolved; reserve retained.");
            }
        }

        return self::SUCCESS;
    }
}
