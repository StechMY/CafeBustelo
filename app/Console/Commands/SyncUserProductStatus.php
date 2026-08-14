<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncUserProductStatus extends Command
{
    protected $signature = 'products:sync-status';

    protected $description = 'Expire completed products and mark missed daily income days.';

    public function handle(): int
    {
        $now = now();

        UserProduct::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', $now)
            ->chunkById(500, function ($products) use ($now): void {
                foreach ($products as $product) {
                    DB::transaction(function () use ($product, $now): void {
                        $locked = UserProduct::query()
                            ->whereKey($product->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $locked || $locked->status !== 'active') {
                            return;
                        }

                        if ($locked->effectiveExpiresAt()->gt($now)) {
                            return;
                        }

                        $locked->forceFill([
                            'status' => 'expired',
                        ])->save();

                        $this->deactivateUserIfNoActiveProducts($locked->user_id);
                    });
                }
            });

        UserProduct::query()
            ->where('status', 'active')
            ->where('next_claim_at', '<', $now->copy()->startOfDay())
            ->chunkById(500, function ($products): void {
                foreach ($products as $product) {
                    DB::transaction(function () use ($product): void {
                        $locked = UserProduct::query()
                            ->whereKey($product->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $locked || $locked->status !== 'active') {
                            return;
                        }

                        while (
                            $locked->next_claim_at
                            && $locked->next_claim_at->lt(now()->startOfDay())
                            && ($locked->claimed_days + $locked->missed_days) < $locked->duration_days_snapshot
                        ) {
                            $locked->missed_days++;
                            $locked->next_claim_at = $locked->next_claim_at->copy()->addDay();
                        }

                        if (($locked->claimed_days + $locked->missed_days) >= $locked->duration_days_snapshot) {
                            $locked->status = 'expired';
                        }

                        $locked->save();

                        if ($locked->status === 'expired') {
                            $this->deactivateUserIfNoActiveProducts($locked->user_id);
                        }
                    });
                }
            });

        $this->info('User product status synced.');

        return self::SUCCESS;
    }

    private function deactivateUserIfNoActiveProducts(int $userId): void
    {
        $hasActiveProduct = UserProduct::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveProduct) {
            User::query()
                ->whereKey($userId)
                ->update([
                    'status' => 'inactive',
                ]);
        }
    }
}
