<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recharge_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('ydpay_deposit_vnd_per_usd')->nullable();
        });

        $orders = DB::table('recharge_requests')
            ->where('deposit_method', 'gateway')
            ->where('gateway_provider', 'ydpay')
            ->where('gateway_currency', 'vnd')
            ->where('status', 'approved')
            ->whereNotNull('gateway_completed_at')
            ->whereNull('ydpay_deposit_vnd_per_usd');
        $events = DB::table('payment_gateway_events')
            ->where('provider', 'ydpay')
            ->where('event_type', 'ydpay.settings.updated')
            ->whereNotNull('processed_at')
            ->orderBy('processed_at')
            ->orderBy('id')
            ->get(['id', 'processed_at', 'payload']);

        $rate = null;
        foreach ($events as $event) {
            $payload = json_decode($event->payload ?? '{}', true);
            $nextRate = $this->validRate(data_get($payload, 'new.deposit_vnd_per_usd'));
            if ($nextRate === null) {
                continue;
            }

            $rate ??= $this->validRate(data_get($payload, 'old.deposit_vnd_per_usd')) ?? 27300;
            if ($rate === $nextRate) {
                continue;
            }

            (clone $orders)
                ->where(function (Builder $query) use ($event): void {
                    $query->where('gateway_completed_at', '<', $event->processed_at)
                        ->orWhere(function (Builder $query) use ($event): void {
                            // The completion event orders confirmations and rate changes within the same second.
                            $query->where('gateway_completed_at', $event->processed_at)
                                ->whereExists(fn (Builder $events) => $events->selectRaw('1')
                                    ->from('payment_gateway_events')
                                    ->whereColumn('recharge_request_id', 'recharge_requests.id')
                                    ->where('event_type', 'ydpay.deposit.completed')
                                    ->where('id', '<', $event->id));
                        });
                })
                ->update(['ydpay_deposit_vnd_per_usd' => $rate]);
            $rate = $nextRate;
        }

        $orders->update(['ydpay_deposit_vnd_per_usd' => $rate ?? 27300]);
    }

    public function down(): void
    {
        Schema::table('recharge_requests', function (Blueprint $table): void {
            $table->dropColumn('ydpay_deposit_vnd_per_usd');
        });
    }

    private function validRate(mixed $rate): ?int
    {
        $rate = filter_var($rate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $rate === false ? null : $rate;
    }
};
